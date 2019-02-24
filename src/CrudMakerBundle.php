<?php

/**
 * Description of MakerBundle
 *
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */

namespace Kematjaya\CrudMakerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CrudMakerBundle extends Bundle {
    
    public function build(ContainerBuilder $container)
    {
        //$container->addCompilerPass(new SerializerConfigurationPass());
    }
    
}
