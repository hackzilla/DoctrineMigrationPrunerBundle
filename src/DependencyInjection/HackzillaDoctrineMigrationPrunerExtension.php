<?php

declare(strict_types=1);

namespace Hackzilla\DoctrineMigrationPrunerBundle\DependencyInjection;

use Hackzilla\DoctrineMigrationPrunerBundle\EventSubscriber\MigrationSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class HackzillaDoctrineMigrationPrunerExtension extends Extension {
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('hackzilla_doctrine_migration_pruner.remove_migrations_before', $config['remove_migrations_before']);

        $container->register(MigrationSubscriber::class)
            ->addArgument(new Reference('doctrine.migrations.configuration'))
            ->addArgument(new Reference('parameter_bag'))
            ->addArgument(new Reference('logger'))
            ->addArgument(new Reference('doctrine'))
            ->addArgument(new Reference('filesystem'))
            ->addTag('kernel.event_subscriber');
    }
}
