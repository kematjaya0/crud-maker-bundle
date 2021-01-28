<?php

/**
 * This file is part of the crud-maker-bundle.
 */

namespace Kematjaya\CrudMakerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @package Kematjaya\CrudMakerBundle\DependencyInjection
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    
    public function getConfigTreeBuilder(): TreeBuilder 
    {
        $treeBuilder = new TreeBuilder('crud_maker');
        $rootNode = $treeBuilder->getRootNode();
        
        $this->addEntityConfiguration($rootNode->children());
        $this->addFilterConfiguration($rootNode->children());
        $this->addTemplateConfiguration($rootNode->children());
        
        return $treeBuilder;
    }

    public function addEntityConfiguration(NodeBuilder $node):void
    {
        $node
            ->arrayNode('entity')
                ->beforeNormalization()->castToArray()->end()
                ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('namespace_prefix')->defaultValue('Entity\\')->end()
                        ->scalarNode('suffix')->defaultValue('')->end()
                    ->end()
                ->end()
            ->end();
    }
    
    public function addFilterConfiguration(NodeBuilder $node):void
    {
        $node
            ->arrayNode('filter')
                ->beforeNormalization()->castToArray()->end()
                ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('namespace_prefix')->defaultValue('Filter\\')->end()
                        ->scalarNode('suffix')->defaultValue('FilterType')->end()
                    ->end()
                ->end()
            ->end();
    }
    
    public function addTemplateConfiguration(NodeBuilder $node):void
    {
        $node
            ->arrayNode('templates')
                ->beforeNormalization()->castToArray()->end()
                ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('path')->defaultValue(null)->end()
                    ->end()
                ->end()
            ->end();
    }
}
