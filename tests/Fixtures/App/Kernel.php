<?php

namespace Kematjaya\CrudMakerBundle\Tests\Fixtures\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Kematjaya\CrudMakerBundle\CrudMakerBundle;
use Spiriit\Bundle\FormFilterBundle\SpiriitFormFilterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
            new MakerBundle(),
            new SpiriitFormFilterBundle(),
            new CrudMakerBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/config.yaml');
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/crud-maker-bundle/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/crud-maker-bundle/log';
    }
}
