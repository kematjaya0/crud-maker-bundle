<?php

/**
 * This file is part of the crud-maker-bundle.
 */

namespace Kematjaya\CrudMakerBundle\Tests;

use Kematjaya\CrudMakerBundle\Maker\FilterMaker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @package Kematjaya\CrudMakerBundle\Tests
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class CrudMakerBundleTest extends WebTestCase
{
    public static function getKernelClass() 
    {
        return AppKernelTest::class;
    }
    
    public function testInstanceClass():ContainerInterface
    {
        $client = parent::createClient();
        $container = $client->getContainer();
        $this->assertInstanceOf(ContainerInterface::class, $container);
        
        return $container;
    }
    
    /**
     * @depends testInstanceClass
     * @param ContainerInterface $container
     * @return FilterMaker
     */
    public function testInstanceMakerFilter(ContainerInterface $container):FilterMaker
    {
        $registry = $this->createConfiguredMock(ManagerRegistry::class, [
            'getManagers' => []
        ]);
        $entityHelper = new DoctrineHelper('Entity', $registry);
        
        $maker = new FilterMaker($entityHelper, $container->get('filter_type_renderer'));
        $this->assertTrue(true);
        
        return $maker;
    }
    
    /**
     * @depends testInstanceClass
     * @depends testInstanceMakerFilter
     * @param FilterMaker $maker
     */
    public function testGenerateFilter(ContainerInterface $container, FilterMaker $maker)
    {
        $input = $this->createConfiguredMock(InputInterface::class, [
            'getArgument' => 'TestEntity'
        ]);
        $formatter = $this->createConfiguredMock(OutputFormatterInterface::class, []);
        $output = $this->createConfiguredMock(OutputInterface::class, [
            'getFormatter' => $formatter
        ]);
        $io = new ConsoleStyle($input, $output);
        
        $fileSystem = new \Symfony\Component\Filesystem\Filesystem();
        $basePath = dirname(__DIR__);
        $arr = ['tests', 'Filter', 'TestEntityFilterType.php'];
        $file = $basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $arr);
        $maker->generate($input, $io, $container->get('generator'));
        $this->assertTrue($fileSystem->exists($file));
        $fileSystem->remove($file);
    }
    
    
//    public function testInstanceCRUDMaker(ContainerInterface $container):CRUDMaker
//    {
//        $registry = $this->createConfiguredMock(ManagerRegistry::class, [
//            'getManagers' => []
//        ]);
//        $entityHelper = new DoctrineHelper('Entity', $registry);
//        
//        $maker = new CRUDMaker($container->get('controller_renderer'), $container->get('form_renderer'), $entityHelper);
//        dump($maker);exit;
//        $this->assertTrue(true);
//        
//        return $maker;
//    }
}
