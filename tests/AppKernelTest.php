<?php

namespace Kematjaya\CrudMakerBundle\Tests;

use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Kematjaya\CrudMakerBundle\CrudMakerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */
class AppKernelTest extends Kernel
{
    public function registerBundles():array
    {
        return [
            new TwigBundle(),
            //new MakerBundle(),
            new CrudMakerBundle(),
            new FrameworkBundle()
        ];
    }
    
    public function registerContainerConfiguration(LoaderInterface $loader):void
    {
        $loader->load(function (ContainerBuilder $container) use ($loader) {
            $loader->load(__DIR__ . DIRECTORY_SEPARATOR . 'config/config.yml');
            $loader->load(__DIR__ . DIRECTORY_SEPARATOR . 'config/services_test.yml');
            $loader->load(__DIR__ . DIRECTORY_SEPARATOR . 'config/bundle.yml');
            
            $container->addObjectResource($this);
        });
    }
}
