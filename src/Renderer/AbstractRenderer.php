<?php

/**
 * This file is part of the crud-maker-bundle.
 */

namespace Kematjaya\CrudMakerBundle\Renderer;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * @package Kematjaya\CrudMakerBundle\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
abstract class AbstractRenderer
{
    protected readonly array $basePath;

    protected readonly array $config;

    public function __construct(ContainerBagInterface $bag)
    {
        $this->config = $bag->get('crud_maker');
        $path = (null !== $this->config['templates']['path']) ? [$this->config['templates']['path']] : [];
        $this->basePath = array_merge($path, [dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton']);
    }

    public function getBasePath(): array
    {
        return $this->basePath;
    }

    /**
     * crud_maker configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Available view themes (directory inside crud/views)
     */
    public function getAvailableThemes(): array
    {
        $themes = [];
        foreach ($this->getBasePath() as $path) {
            $dir = $path . DIRECTORY_SEPARATOR . 'crud' . DIRECTORY_SEPARATOR . 'views';
            if (!is_dir($dir)) {
                continue;
            }

            $themes = array_merge($themes, array_filter(scandir($dir), fn(string $name): bool => "." !== $name and ".." !== $name and is_dir($dir . DIRECTORY_SEPARATOR . $name)));
        }

        $themes = array_values(array_unique($themes));
        sort($themes);

        return $themes;
    }

    protected function getPath(string $filename): string
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
