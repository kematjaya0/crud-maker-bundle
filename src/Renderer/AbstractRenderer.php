<?php

namespace Kematjaya\CrudMakerBundle\Renderer;

use Doctrine\Inflector\Inflector as LegacyInflector;
use Doctrine\Inflector\InflectorFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * @package Kematjaya\CrudMakerBundle\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
abstract class AbstractRenderer
{
    /** @var array<int, string> */
    protected array $basePath = [];

    private ?\Doctrine\Inflector\Inflector $inflector = null;

    public function __construct(ContainerBagInterface $bag)
    {
        $configs = $bag->get('crud_maker');
        $path = (null !== $configs['templates']['path']) ? [$configs['templates']['path']] : [];
        $this->basePath = array_merge($path, [dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton']);

        if (class_exists(InflectorFactory::class)) {
            $this->inflector = InflectorFactory::create()->build();
        }
    }

    protected function pluralize(string $word): string
    {
        if (null !== $this->inflector) {
            return $this->inflector->pluralize($word);
        }

        /** @phpstan-ignore method.staticCall */
        return LegacyInflector::pluralize($word);
    }

    protected function singularize(string $word): string
    {
        if (null !== $this->inflector) {
            return $this->inflector->singularize($word);
        }

        /** @phpstan-ignore method.staticCall */
        return LegacyInflector::singularize($word);
    }
    
    /**
     * @return array<int, string>
     */
    public function getBasePath():array
    {
        return $this->basePath;
    }
    /**
     * 
     * @param string $filename
     * @return string
     */
    protected function getPath(string $filename):string
    {
        foreach ($this->basePath as $path) {
            if (!file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
                continue;
            }
            
            return $path . DIRECTORY_SEPARATOR . $filename;
        }
        
        throw new \Exception(sprintf("cannot find template '%s'", $filename));
    }
}
