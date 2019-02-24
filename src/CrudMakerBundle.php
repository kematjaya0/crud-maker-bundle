<?php

/**
 * Description of MakerBundle
 *
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */

namespace Kematjaya\MakerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MakerBundle extends Bundle {
    
    public function build(ContainerBuilder $container)
    {
        //$container->addCompilerPass(new SerializerConfigurationPass());
    }
    
}
