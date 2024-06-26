<?php

namespace Angle\FileStorageBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('angle_file_storage');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('type')->defaultValue('local')->end()
            ->scalarNode('container')->defaultNull()->end()
            ->scalarNode('username')->defaultNull()->end()
            ->scalarNode('secret')->defaultNull()->end()
            ->scalarNode('aws_region')->defaultNull()->end()
        ;

        return $treeBuilder;
    }
}
