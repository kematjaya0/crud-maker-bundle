<?php

namespace Kematjaya\CrudMakerBundle\Tests;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Kematjaya\CrudMakerBundle\Renderer\ControllerRenderer;
use Kematjaya\CrudMakerBundle\Tests\Fixtures\App\Kernel;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdaterInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Menjalankan command make:kmj-* terhadap entity fixture dan memeriksa hasil generate
 */
class MakerTest extends KernelTestCase
{
    const GENERATED = ['src/Controller', 'src/Form', 'src/Filter', 'templates', 'tests'];

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        $this->cleanup();
        (new Filesystem())->dumpFile($this->path('templates/base.html.twig'), "<!DOCTYPE html>\n<html><body>{% block body %}{% endblock %}{% block javascripts %}{% endblock %}</body></html>\n");
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $dir = dirname(__DIR__) . '/tests/Fixtures/App/';
        foreach (self::GENERATED as $path) {
            (new Filesystem())->remove($dir . $path);
        }
    }

    private function path(string $file): string
    {
        return dirname(__DIR__) . '/tests/Fixtures/App/' . $file;
    }

    private function runMaker(string $command, array $arguments): CommandTester
    {
        $application = new Application(static::bootKernel());
        $tester = new CommandTester($application->find($command));
        $tester->execute($arguments, ['interactive' => false]);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        return $tester;
    }

    private function assertValidPhp(string $file): void
    {
        $this->assertFileExists($file);
        $process = new Process([PHP_BINARY, '-l', $file]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
    }

    private function assertValidTwig(string $file): void
    {
        $this->assertFileExists($file);
        $twig = new Environment(new ArrayLoader());
        $twig->registerUndefinedFunctionCallback(fn (string $name) => new TwigFunction($name, fn () => ''));
        $twig->registerUndefinedFilterCallback(fn (string $name) => new TwigFilter($name, fn ($value) => $value));
        $twig->parse($twig->tokenize(new Source(file_get_contents($file), basename($file))));
    }

    private function loadClass(string $file, string $class): void
    {
        require_once $file;
        $this->assertTrue(class_exists($class, false), $class);
    }

    public function testCrudWithRepositoryAndFilter()
    {
        $tester = $this->runMaker('make:kmj-crud', [
            'entity-class' => 'Product',
            'namespace' => '-',
            'include-filter' => 'yes',
            'modal-form' => 'no',
            'theme' => 'bootstrap-5',
        ]);
        $this->assertStringContainsString('Success', $tester->getDisplay());

        foreach (['src/Controller/ProductController.php', 'src/Form/ProductType.php', 'src/Filter/ProductFilterType.php'] as $file) {
            $this->assertValidPhp($this->path($file));
        }
        foreach (['index', 'form', 'show', '_delete_form', '_filters', '_max_per_page'] as $template) {
            $this->assertValidTwig($this->path('templates/product/' . $template . '.html.twig'));
        }

        $controller = file_get_contents($this->path('src/Controller/ProductController.php'));
        $this->assertStringContainsString('use Kematjaya\BaseControllerBundle\Controller\BaseLexikFilterController as BaseController;', $controller);
        $this->assertStringContainsString('ProductRepository $productRepository', $controller);

        $this->loadClass($this->path('src/Form/ProductType.php'), 'App\Form\ProductType');
        $this->loadClass($this->path('src/Filter/ProductFilterType.php'), 'App\Filter\ProductFilterType');
        $this->loadClass($this->path('src/Controller/ProductController.php'), 'App\Controller\ProductController');

        $routes = (new AttributeRouteControllerLoader())->load('App\Controller\ProductController');
        $this->assertSame(['app_product_index', 'app_product_create', 'app_product_show', 'app_product_edit', 'app_product_delete'], array_keys($routes->all()));
        $this->assertSame('/product.html', $routes->get('app_product_index')->getPath());
        $this->assertSame('/product/{id}/edit.html', $routes->get('app_product_edit')->getPath());
        $this->assertSame(['DELETE', 'POST'], $routes->get('app_product_delete')->getMethods());

        // filter type dari tipe doctrine: json dilewati, relasi pakai EntityFilterType
        $filter = file_get_contents($this->path('src/Filter/ProductFilterType.php'));
        $this->assertStringContainsString("->add('name', Filters\TextFilterType::class", $filter);
        $this->assertStringContainsString("->add('price', Filters\NumberFilterType::class)", $filter);
        $this->assertStringContainsString("->add('active', Filters\BooleanFilterType::class)", $filter);
        $this->assertStringContainsString("->add('createdAt', Filters\DateRangeFilterType::class", $filter);
        $this->assertStringContainsString("'class' => \App\Entity\Category::class", $filter);
        $this->assertStringNotContainsString("'tags'", $filter);
        $this->assertStringNotContainsString("'id'", $filter);

        // filter bisa dibangun dan diterapkan ke query
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $form = $container->get('test.form_factory')->create('App\Filter\ProductFilterType', null, ['csrf_protection' => false]);
        $form->submit(['name' => 'kopi', 'active' => 'y', 'price' => '10']);
        $qb = $em->getRepository(Product::class)->createQueryBuilder('t');
        $container->get('test.filter_builder_updater')->addFilterConditions($form, $qb);
        $sql = $qb->getQuery()->getSQL();
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('active', $sql);

        $index = file_get_contents($this->path('templates/product/index.html.twig'));
        $this->assertStringContainsString("{% extends 'base.html.twig' %}", $index);
        $this->assertStringContainsString('colspan="6"', $index);
        $this->assertStringContainsString("include('product/_filters.html.twig'", $index);
    }

    public function testCrudWithoutRepositoryModalAndNamespace()
    {
        $this->runMaker('make:kmj-crud', [
            'entity-class' => 'Category',
            'namespace' => 'Admin',
            'include-filter' => 'no',
            'modal-form' => 'yes',
            'theme' => 'bootstrap-4',
        ]);

        $file = $this->path('src/Controller/Admin/CategoryController.php');
        $this->assertValidPhp($file);
        $controller = file_get_contents($file);
        $this->assertStringContainsString('BasePaginationController as BaseController', $controller);
        $this->assertStringContainsString('$this->getDoctrine()->getRepository(Category::class)', $controller);
        $this->assertStringContainsString("['code' => \$category->getCode()]", $controller);
        $this->assertStringContainsString('processFormAjax', $controller);
        $this->assertFileDoesNotExist($this->path('src/Filter/CategoryFilterType.php'));
        $this->assertFileDoesNotExist($this->path('templates/admin/category/_filters.html.twig'));

        foreach (['index', 'form', 'show', '_delete_form', '_max_per_page'] as $template) {
            $this->assertValidTwig($this->path('templates/admin/category/' . $template . '.html.twig'));
        }

        $this->loadClass($this->path('src/Form/CategoryType.php'), 'App\Form\CategoryType');
        $this->loadClass($file, 'App\Controller\Admin\CategoryController');
        $routes = (new AttributeRouteControllerLoader())->load('App\Controller\Admin\CategoryController');
        $this->assertSame(['app_admin_category_index', 'app_admin_category_create', 'app_admin_category_show', 'app_admin_category_edit', 'app_admin_category_delete'], array_keys($routes->all()));
        $this->assertSame('/admin/category/{code}/show.html', $routes->get('app_admin_category_show')->getPath());
    }

    public function testExistingFormTypeIsNotRegenerated()
    {
        (new Filesystem())->dumpFile($this->path('src/Form/CategoryType.php'), "<?php\n\nnamespace App\Form;\n\nclass CategoryType extends \\Symfony\\Component\\Form\\AbstractType\n{\n}\n");

        $this->runMaker('make:kmj-crud', [
            'entity-class' => 'Category',
            'namespace' => '-',
            'include-filter' => 'no',
            'modal-form' => 'no',
        ]);

        $this->assertStringNotContainsString('buildForm', file_get_contents($this->path('src/Form/CategoryType.php')));
        $this->assertValidPhp($this->path('src/Controller/CategoryController.php'));
        // theme default dari konfigurasi (bootstrap-4)
        $this->assertStringContainsString('pull-right', file_get_contents($this->path('templates/category/index.html.twig')));
    }

    public function testUnknownTheme()
    {
        $application = new Application(static::bootKernel());
        $tester = new CommandTester($application->find('make:kmj-crud'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("theme 'bootstrap-9' not found");
        $tester->execute(['entity-class' => 'Product', 'namespace' => '-', 'include-filter' => 'no', 'modal-form' => 'no', 'theme' => 'bootstrap-9'], ['interactive' => false]);
    }

    public function testFilterMaker()
    {
        $this->runMaker('make:kmj-filter', ['name' => 'ProductSearch', 'bound-class' => 'Product']);

        $file = $this->path('src/Filter/ProductSearchFilterType.php');
        $this->assertValidPhp($file);
        $this->assertStringContainsString('class ProductSearchFilterType extends AbstractFilterType', file_get_contents($file));
    }

    public function testFunctionalTestMaker()
    {
        $this->runMaker('make:kmj-functional-test', ['entity-class' => 'Category', 'include-filter' => 'no', 'modal-form' => 'yes']);

        $file = $this->path('tests/Controller/CategoryControllerTest.php');
        $this->assertValidPhp($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString("generate('app_category_create')", $content);
        $this->assertStringContainsString("['code' => \$data->getCode()]", $content);
        $this->assertStringContainsString('parent::ajaxForm', $content);
    }

    public function testThemes()
    {
        $renderer = static::getContainer()->get('test.controller_renderer');

        $this->assertInstanceOf(ControllerRenderer::class, $renderer);
        $this->assertSame(['bootstrap-3', 'bootstrap-4', 'bootstrap-5'], $renderer->getAvailableThemes());
    }
}
