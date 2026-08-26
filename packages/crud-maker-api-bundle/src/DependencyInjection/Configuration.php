<?php

namespace Kematjaya\CrudMakerBundle\Api\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @package Kematjaya\CrudMakerBundle\Api\DependencyInjection
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('crud_maker_api');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('templates')
                    ->beforeNormalization()->castToArray()->end()
                    ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('path')->defaultValue(null)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
