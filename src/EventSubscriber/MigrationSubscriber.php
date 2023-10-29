<?php

declare(strict_types=1);

namespace Hackzilla\DoctrineMigrationPrunerBundle\EventSubscriber;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class MigrationSubscriber implements EventSubscriberInterface
{
    private \DateTimeImmutable|null $cutOff = null;

    protected readonly string $projectDirectory;

    private string $tableName = 'doctrine_migration_versions';

    public function __construct(
        private readonly Configuration $configuration,
        private readonly ParameterBagInterface $bag,
        LoggerInterface $logger,
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

        $this->cutOff = new \DateTimeImmutable('2007-01-01');
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

        $connection = $this->registry->getConnection($connectionName);
        $migrationDirectory = $migrationDirectories['DoctrineMigrations'];

        $sql    = sprintf('SELECT SUBSTRING(version, 20) as version FROM %s WHERE DATE(SUBSTRING(version, 27)) < :date', $this->tableName);
        $stmt   = $connection->prepare($sql);
        $result = $stmt->executeQuery(['date' => $formattedDate]);

        while ($version = $result->fetchOne()) {
            $filename = $migrationDirectory . '/' . $version . '.php';

            if ($this->filesystem->exists($filename)) {
                $this->filesystem->remove($filename);
            }
        }

        $sql  = sprintf('DELETE FROM %s WHERE DATE(SUBSTRING(version, 27)) < :date', $this->tableName);
        $stmt = $connection->prepare($sql);
        $stmt->executeStatement(['date' => $formattedDate]);
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
}
