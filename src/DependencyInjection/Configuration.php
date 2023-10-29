<?php

declare(strict_types=1);

namespace Hackzilla\DoctrineMigrationPrunerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('hackzilla_doctrine_migration_pruner');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
        ->children()
            ->scalarNode('remove_migrations_before')
                ->defaultNull()
                ->validate()
                    ->ifTrue(function ($value) {
                        return $value !== null && !self::isValidDateTime($value);
                    })
                    ->thenInvalid('Invalid date-time format.')
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }

    private static function isValidDateTime($dateTime)
    {
        try {
            new \DateTime($dateTime);
        } catch (\Exception) {
            return false;
        }

        return true;
    }
}
