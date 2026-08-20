<?php

namespace Kematjaya\CrudMakerBundle\Tests;

use Kematjaya\CrudMakerBundle\Maker\ApiCrudMaker;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * End-to-end smoke test for `make:kmj-api-crud` against a real, Doctrine-attribute-mapped
 * fixture entity (TestArticle, owned by TestOwner) — verifies every generated file is valid
 * PHP and contains the expected shape, and that the frontend spec sidecar is written.
 *
 * @package Kematjaya\CrudMakerBundle\Tests
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
#[AllowMockObjectsWithoutExpectations]
final class ApiCrudMakerTest extends WebTestCase
{
    public static function getKernelClass(): string
    {
        return AppKernelTest::class;
    }

    /** @var list<string> */
    private array $generatedFiles = [];

    /**
     * `make:kmj-api-crud` injects an export-query method into the entity's EXISTING repository
     * file (see {@see \Kematjaya\CrudMakerBundle\Util\RepositoryMethodWriter}) — unlike the
     * files tracked in $generatedFiles, these must be restored rather than deleted, otherwise
     * a second test run collides with the already-injected method.
     *
     * @var array<string, string>
     */
    private array $repositoryBackups = [];

    protected function setUp(): void
    {
        parent::setUp();

        $basePath = dirname(__DIR__).'/tests';
        foreach (['Repository/TestArticleRepository.php', 'Repository/TestLegacyArticleRepository.php', 'Repository/TestBrokenArticleRepository.php'] as $relativePath) {
            $path = $basePath.'/'.$relativePath;
            $this->repositoryBackups[$path] = (string) file_get_contents($path);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $filesystem = new Filesystem();
        foreach ($this->generatedFiles as $file) {
            if ($filesystem->exists($file)) {
                $filesystem->remove($file);
            }
        }
        $this->generatedFiles = [];

        foreach ($this->repositoryBackups as $path => $contents) {
            file_put_contents($path, $contents);
        }
        $this->repositoryBackups = [];

        $this->restoreExceptionHandler();
    }

    private function restoreExceptionHandler(): void
    {
        while (true) {
            $previousHandler = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previousHandler) {
                break;
            }
            restore_exception_handler();
        }
    }

    /**
     * @param array<string, mixed> $argumentMap entity-class, owner-property, permission-prefix, with-access-control, with-tests
     */
    private function invokeMaker(array $argumentMap): void
    {
        static::bootKernel([]);
        $container = static::$kernel->getContainer();

        $maker = new ApiCrudMaker(
            $container->get(\Kematjaya\CrudMakerBundle\Renderer\ApiCrudRenderer::class),
            $container->get(\Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper::class),
        );

        $input = $this->createMock(InputInterface::class);
        $input->method('getArgument')->willReturnCallback(
            static fn (string $name) => $argumentMap[$name] ?? null
        );

        $formatter = $this->createConfiguredMock(OutputFormatterInterface::class, []);
        $output = $this->createConfiguredMock(OutputInterface::class, ['getFormatter' => $formatter]);
        $io = new ConsoleStyle($input, $output);

        $maker->generate($input, $io, $container->get('generator'));
    }

    public function testGeneratesFullApiCrudWithOwnerAccessControlAndTests(): void
    {
        $this->invokeMaker([
            'entity-class' => 'TestArticle',
            'owner-property' => 'owner',
            'permission-prefix' => 'test-articles',
            'with-access-control' => true,
            'with-tests' => true,
            'searchable-fields' => 'title',
        ]);

        // This bundle's own test kernel roots the generator at
        // `Kematjaya\CrudMakerBundle\Tests` (-> tests/), unlike a real consuming app
        // where it's rooted at `App\` (-> src/). That's why paths land under tests/
        // here, and why the ServiceTest lands under tests/Tests/... (Tests\Unit\Service\
        // prefix appended on top of the already-`Tests`-rooted generator) — an artifact
        // of testing this maker against itself, not something a real consumer sees.
        $basePath = dirname(__DIR__).'/tests';

        $expected = [
            'Dto/TestArticleInput.php' => ['final readonly class TestArticleInput', "Assert\\NotBlank(normalizer: 'trim')", 'public string $title', 'public string $body'],
            'Service/TestArticleServiceInterface.php' => ['interface TestArticleServiceInterface', 'function create(TestOwner $owner, TestArticleInput $input): TestArticle'],
            'Service/TestArticleService.php' => ['final readonly class TestArticleService implements TestArticleServiceInterface', 'new TestArticle($owner, trim($input->title), trim($input->body))'],
            'State/TestArticleWriteProcessor.php' => ['final readonly class TestArticleWriteProcessor implements ProcessorInterface', "'test-articles.create'", "'test-articles.edit'"],
            'State/CurrentUserTestArticleExtension.php' => ['final readonly class CurrentUserTestArticleExtension', 'TestArticle::class !== $resourceClass'],
            'Tests/Unit/Service/TestArticleServiceTest.php' => ['final class TestArticleServiceTest extends TestCase'],
            'Controller/TestArticleExportDataController.php' => [
                'final readonly class TestArticleExportDataController',
                "#[Route('/api/test-articles/export-data', name: 'app_test_articles_export_data'",
                "'test-articles.export_all'",
                "'title' => \$item['title']",
                '->findExportDataForOwner($user, $search, self::MAX_ITEMS + 1)',
                'private function problem(',
            ],
            'Repository/TestArticleRepository.php' => [
                'public function findExportDataForOwner(TestOwner $owner, ?string $search, int $limit): array',
                "->select('e.title, e.body, e.createdAt')",
                "->andWhere('e.owner = :owner')->setParameter('owner', \$owner)",
                "\$builder->expr()->like('e.title', ':search')",
            ],
        ];

        foreach ($expected as $relativePath => $needles) {
            $file = $basePath.'/'.$relativePath;
            // Repository/TestArticleRepository.php is a pre-existing fixture file that gets a
            // method injected, not a freshly-generated one — it's restored via
            // $repositoryBackups in tearDown(), not deleted, so it's deliberately not added here.
            if (!str_starts_with($relativePath, 'Repository/')) {
                $this->generatedFiles[] = $file;
            }

            self::assertFileExists($file, sprintf('Expected "%s" to be generated', $relativePath));

            $lintProcess = new \Symfony\Component\Process\Process([\PHP_BINARY, '-l', $file]);
            $lintProcess->run();
            self::assertTrue($lintProcess->isSuccessful(), sprintf('"%s" is not valid PHP: %s', $relativePath, $lintProcess->getErrorOutput()));

            $contents = (string) file_get_contents($file);
            foreach ($needles as $needle) {
                if ('' === $needle) {
                    continue;
                }
                self::assertStringContainsString($needle, $contents, sprintf('"%s" should contain "%s"', $relativePath, $needle));
            }
        }

        $specFile = dirname(__DIR__).'/crud-specs/TestArticle.json';
        $this->generatedFiles[] = $specFile;
        self::assertFileExists($specFile);

        $spec = json_decode((string) file_get_contents($specFile), true);
        self::assertSame('TestArticle', $spec['entity']);
        self::assertSame('test-articles', $spec['permissionPrefix']);
        self::assertSame('owner', $spec['ownerProperty']);

        $fieldNames = array_column($spec['fields'], 'name');
        self::assertContains('title', $fieldNames);
        self::assertContains('body', $fieldNames);
        self::assertNotContains('createdAt', $fieldNames, 'datetime fields should be skipped from the spec/input');
        self::assertNotContains('id', $fieldNames);
        self::assertNotContains('owner', $fieldNames, 'owner is an association, handled separately from scalar fields');

        $fieldsByName = array_combine($fieldNames, $spec['fields']);
        self::assertTrue($fieldsByName['title']['searchable']);
        self::assertFalse($fieldsByName['body']['searchable']);
        self::assertSame('createdAt', $spec['timestampField']);
    }

    public function testGeneratesWithoutOwnerOrAccessControlWhenNotRequested(): void
    {
        $this->invokeMaker([
            'entity-class' => 'TestArticle',
            'owner-property' => '-',
            'permission-prefix' => '-',
            'with-access-control' => false,
            'with-tests' => false,
        ]);

        $basePath = dirname(__DIR__).'/tests';

        $this->generatedFiles[] = $basePath.'/Dto/TestArticleInput.php';
        $this->generatedFiles[] = $basePath.'/Service/TestArticleServiceInterface.php';
        $this->generatedFiles[] = $basePath.'/Service/TestArticleService.php';
        $this->generatedFiles[] = $basePath.'/State/TestArticleWriteProcessor.php';
        $this->generatedFiles[] = $basePath.'/Controller/TestArticleExportDataController.php';
        $this->generatedFiles[] = dirname(__DIR__).'/crud-specs/TestArticle.json';

        self::assertFileExists($basePath.'/Dto/TestArticleInput.php');
        self::assertFileDoesNotExist($basePath.'/State/CurrentUserTestArticleExtension.php');
        self::assertFileDoesNotExist($basePath.'/Tests/Unit/Service/TestArticleServiceTest.php');

        $processorContents = (string) file_get_contents($basePath.'/State/TestArticleWriteProcessor.php');
        self::assertStringNotContainsString('AuthorizationCheckerInterface', $processorContents);

        $serviceInterfaceContents = (string) file_get_contents($basePath.'/Service/TestArticleServiceInterface.php');
        self::assertStringContainsString('function create(TestArticleInput $input): TestArticle', $serviceInterfaceContents);
    }

    public function testGeneratesSetterBasedServiceForEntityWithoutConstructor(): void
    {
        $this->invokeMaker([
            'entity-class' => 'TestLegacyArticle',
            'owner-property' => '-',
            'permission-prefix' => 'test-legacy-articles',
            'with-access-control' => false,
            'with-tests' => true,
        ]);

        $basePath = dirname(__DIR__).'/tests';
        $this->generatedFiles[] = $basePath.'/Dto/TestLegacyArticleInput.php';
        $this->generatedFiles[] = $basePath.'/Service/TestLegacyArticleServiceInterface.php';
        $this->generatedFiles[] = $basePath.'/Service/TestLegacyArticleService.php';
        $this->generatedFiles[] = $basePath.'/State/TestLegacyArticleWriteProcessor.php';
        $this->generatedFiles[] = $basePath.'/Controller/TestLegacyArticleExportDataController.php';
        $this->generatedFiles[] = $basePath.'/Tests/Unit/Service/TestLegacyArticleServiceTest.php';
        $this->generatedFiles[] = dirname(__DIR__).'/crud-specs/TestLegacyArticle.json';

        $serviceContents = (string) file_get_contents($basePath.'/Service/TestLegacyArticleService.php');
        self::assertStringContainsString('new TestLegacyArticle()', $serviceContents);
        self::assertStringContainsString("setHeadline(trim(\$input->headline))", $serviceContents);

        $testContents = (string) file_get_contents($basePath.'/Tests/Unit/Service/TestLegacyArticleServiceTest.php');
        self::assertStringNotContainsString("method('update')", $testContents, 'setter-mode entities have no update() method to mock');
        self::assertStringContainsString('new TestLegacyArticle()', $testContents);
        self::assertStringContainsString("getHeadline()", $testContents);

        $lintProcess = new \Symfony\Component\Process\Process([\PHP_BINARY, '-l', $basePath.'/Tests/Unit/Service/TestLegacyArticleServiceTest.php']);
        $lintProcess->run();
        self::assertTrue($lintProcess->isSuccessful(), $lintProcess->getErrorOutput());
        self::assertStringNotContainsString('->update(', $serviceContents);

        $specFile = dirname(__DIR__).'/crud-specs/TestLegacyArticle.json';
        $spec = json_decode((string) file_get_contents($specFile), true);
        self::assertSame('int', $spec['idType']);
    }

    public function testWritesApiResourceAndApiFilterAttributesToEntityWhenRequested(): void
    {
        $entityPath = dirname(__DIR__).'/tests/Entity/TestLegacyArticle.php';
        $originalContents = (string) file_get_contents($entityPath);

        try {
            $this->invokeMaker([
                'entity-class' => 'TestLegacyArticle',
                'owner-property' => '-',
                'permission-prefix' => 'test-legacy-articles',
                'with-access-control' => true,
                'with-tests' => false,
                'searchable-fields' => 'headline',
                'write-entity-attributes' => true,
            ]);

            $basePath = dirname(__DIR__).'/tests';
            $this->generatedFiles[] = $basePath.'/Dto/TestLegacyArticleInput.php';
            $this->generatedFiles[] = $basePath.'/Service/TestLegacyArticleServiceInterface.php';
            $this->generatedFiles[] = $basePath.'/Service/TestLegacyArticleService.php';
            $this->generatedFiles[] = $basePath.'/State/TestLegacyArticleWriteProcessor.php';
            $this->generatedFiles[] = $basePath.'/Controller/TestLegacyArticleExportDataController.php';
            $this->generatedFiles[] = dirname(__DIR__).'/crud-specs/TestLegacyArticle.json';

            $newContents = (string) file_get_contents($entityPath);
            self::assertStringContainsString('use ApiPlatform\Metadata\ApiResource;', $newContents);
            self::assertStringContainsString('use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;', $newContents);
            self::assertStringContainsString('#[ApiResource(', $newContents);
            self::assertStringContainsString("#[ApiFilter(SearchFilter::class, properties: ['headline' => 'partial'])]", $newContents);
            self::assertStringContainsString('is_granted(\'test-legacy-articles.delete\')', $newContents);
            // pre-existing code must survive untouched
            self::assertStringContainsString('public function setHeadline(string $headline): static', $newContents);

            $lintProcess = new \Symfony\Component\Process\Process([\PHP_BINARY, '-l', $entityPath]);
            $lintProcess->run();
            self::assertTrue($lintProcess->isSuccessful(), $lintProcess->getErrorOutput());
        } finally {
            file_put_contents($entityPath, $originalContents);
        }
    }

    public function testThrowsWhenEntityHasNeitherConstructorNorSetters(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/setHeadline/');

        $this->invokeMaker([
            'entity-class' => 'TestBrokenArticle',
            'owner-property' => '-',
            'permission-prefix' => 'test-broken-articles',
            'with-access-control' => false,
            'with-tests' => false,
        ]);
    }
}
