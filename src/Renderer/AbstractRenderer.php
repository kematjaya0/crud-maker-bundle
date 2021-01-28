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
    /**
     * 
     * @var array
     */
    protected $basePath = [];
    
    public function __construct(ContainerBagInterface $bag) 
    {
        $configs = $bag->get('crud_maker');
        $path = (null !== $configs['templates']['path']) ? [$configs['templates']['path']] : [];
        $this->basePath = array_merge($path, [dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton']);
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
