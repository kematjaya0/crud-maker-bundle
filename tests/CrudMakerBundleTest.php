<?php

namespace Kematjaya\CrudMakerBundle\Tests;

use Kematjaya\CrudMakerBundle\Maker\FilterMaker;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @package Kematjaya\CrudMakerBundle\Tests
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class CrudMakerBundleTest extends WebTestCase
{
    public static function getKernelClass() :string
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

    public function testInstanceMakerFilter():FilterMaker
    {
        static::bootKernel([]);
        $container = static::$kernel->getContainer();
        $registry = $this->createConfiguredMock(ManagerRegistry::class, [
            'getManagers' => []
        ]);
        $entityHelper = new DoctrineHelper('Entity', $registry);
        
        $maker = new FilterMaker($entityHelper, $container->get('filter_type_renderer'));
        $this->assertTrue(true);

        return $maker;
    }

    #[Depends("testInstanceMakerFilter")]
    public function testGenerateFilter(FilterMaker $maker)
    {
        static::bootKernel([]);
        $container = static::$kernel->getContainer();
        $input = $this->createConfiguredMock(InputInterface::class, [
            'getArgument' => 'TestEntity'
        ]);
        $formatter = $this->createConfiguredMock(OutputFormatterInterface::class, []);
        $output = $this->createConfiguredMock(OutputInterface::class, [
            'getFormatter' => $formatter
        ]);
        $io = new ConsoleStyle($input, $output);
        
        $fileSystem = new Filesystem();
        $basePath = dirname(__DIR__);
        $arr = ['tests', 'Filter', 'TestEntityFilterType.php'];
        $file = $basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $arr);
        $maker->generate($input, $io, $container->get('generator'));
        $this->assertTrue($fileSystem->exists($file));
        $fileSystem->remove($file);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreExceptionHandler();
    }

    protected function restoreExceptionHandler(): void
    {
        while (true) {
            $previousHandler = set_exception_handler(static fn() => null);

            restore_exception_handler();

            if ($previousHandler === null) {
                break;
            }

            restore_exception_handler();
        }
    }
}
