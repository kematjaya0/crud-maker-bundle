<?php

/**
 * Description of KmjUserExtension
 *
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */

namespace Kematjaya\CrudMakerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class CrudMakerExtension extends Extension 
{
    
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources/config'));
        $loader->load('services.yaml');
        
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter($this->getAlias(), $config);
    }
}
//"pagerfanta/pagerfanta": "^2.0",
//"white-october/pagerfanta-bundle": "^1.2"