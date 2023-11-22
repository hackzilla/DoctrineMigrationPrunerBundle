<?php

declare(strict_types=1);

namespace Hackzilla\DoctrineMigrationPrunerBundle\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class MigrationSubscriber implements EventSubscriberInterface
{
    private \DateTimeImmutable|null $cutOff = null;

    protected readonly string $projectDirectory;

    private string $tableName = 'doctrine_migration_versions';

    public function __construct(
        private readonly Configuration $configuration,
        private readonly ParameterBagInterface $bag,
        private readonly LoggerInterface $logger,
        private readonly ManagerRegistry $registry,
        private readonly Filesystem $filesystem,
    ) {
        $migrationCutOffDate = $bag->get('hackzilla_doctrine_migration_pruner.remove_migrations_before');

        try {
            if (null !== $migrationCutOffDate) {
                $this->cutOff = new \DateTimeImmutable($migrationCutOffDate);
            }
        } catch (\Exception) {
            $logger->warning('Failed to parse remove_migrations_before date', [
                'remove_migrations_before' => $migrationCutOffDate,
            ]);
        }

        $this->projectDirectory = $bag->get('kernel.project_dir');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand'],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        if (!$event->getCommand() instanceof MigrateCommand || !$this->cutOff) {
            return;
        }

        if ($event->getInput()->getOption('dry-run')) {
            return;
        }

        $configFile = $event->getInput()->getOption('configuration');
        $connectionName = $event->getInput()->getOption('em') ?? $this->registry->getDefaultConnectionName();
        $migrationDirectories = $this->configuration->getMigrationDirectories();

        if ($configFile) {
            $migratorConfiguration = $this->fetchMigrationConfig($configFile);

            if ($migratorConfiguration['directories']) {
                $migrationDirectories = $migratorConfiguration['directories'];
            }

            if ($migratorConfiguration['em']) {
                $connectionName = $migratorConfiguration['em'];
            }
        }

        $formattedDate = $this->cutOff->format('Y-m-d H:i:s');

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->registry->getConnection($connectionName);
        $platformName = $connection->getDatabasePlatform()->getName();
        $migrationDirectory = $migrationDirectories['DoctrineMigrations'];

        $output = $event->getOutput();

        $finder = new Finder();
        $finder->files()->in($migrationDirectory)->name('/^Version\d{14}\.php$/');

        foreach ($finder as $file) {
            $filename = $file->getFilename();

            $dateString = substr($filename, 7, 14); // Extract '20210307203106'
            $migrationDate = \DateTime::createFromFormat('YmdHis', $dateString);

            if ($migrationDate && $migrationDate < $this->cutOff) {
                $this->logInfo($output, sprintf('Removing migration file: %s', $file->getPathname()));
                $this->filesystem->remove($file->getPathname());
            }
        }

        if ($this->isMigrationTableSetup($connection)) {
            if ($platformName === 'sqlite') {
                $sql = sprintf(
                    "DELETE FROM %s WHERE DATETIME(
                SUBSTR(version, 27, 4) || '-' ||
                SUBSTR(version, 31, 2) || '-' ||
                SUBSTR(version, 33, 2) || ' ' ||
                SUBSTR(version, 35, 2) || ':' ||
                SUBSTR(version, 37, 2) || ':' ||
                SUBSTR(version, 39, 2)
            ) < :date",
                    $this->tableName
                );
            } elseif ($platformName === 'mysql') {
                $sql = sprintf('DELETE FROM %s WHERE DATE(SUBSTRING(version, 27)) < :date', $this->tableName);
            }

            $stmt = $connection->prepare($sql);
            $affected = $stmt->executeStatement(['date' => $formattedDate]);
            $this->logInfo($output, sprintf('Removed migrations from the database: %d', $affected));
        }
    }

    private function fetchMigrationConfig(string $yamlPath): array
    {
        $config = Yaml::parseFile($yamlPath);

        if ($config['migrations_paths']) {
            foreach ($config['migrations_paths'] as &$path) {
                $path = $this->bag->resolveValue($path);
            }
        }

        return [
            'directories' => $config['migrations_paths'] ?? [],
            'em' => $config['em'] ?? null,
        ];
    }

    private function isMigrationTableSetup(Connection $connection): bool
    {
        $platformName = $connection->getDatabasePlatform()->getName();

        if ($platformName === 'sqlite') {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name = :table";
        } elseif ($platformName === 'mysql') {
            $sql = "SHOW TABLES LIKE :table";
        } else {
            throw new Exception('Unhandled platform type: ' . $platformName);
        }

        /** @var \Doctrine\DBAL\Statement $stmt */
        $stmt = $connection->prepare($sql);
        $stmt->bindValue('table', $this->tableName);
        $stmt->execute();

        return $stmt->executeQuery()->rowCount() > 0;
    }

    private function logInfo(OutputInterface $output, string $message): void
    {
        $this->logger->info($message);

        if ($output->isVerbose()) {
            $output->writeln($message);
        }
    }
}
