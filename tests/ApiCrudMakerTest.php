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
        ];

        foreach ($expected as $relativePath => $needles) {
            $file = $basePath.'/'.$relativePath;
            $this->generatedFiles[] = $file;

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
        $this->generatedFiles[] = dirname(__DIR__).'/crud-specs/TestArticle.json';

        self::assertFileExists($basePath.'/Dto/TestArticleInput.php');
        self::assertFileDoesNotExist($basePath.'/State/CurrentUserTestArticleExtension.php');
        self::assertFileDoesNotExist($basePath.'/Tests/Unit/Service/TestArticleServiceTest.php');

        $processorContents = (string) file_get_contents($basePath.'/State/TestArticleWriteProcessor.php');
        self::assertStringNotContainsString('AuthorizationCheckerInterface', $processorContents);

        $serviceInterfaceContents = (string) file_get_contents($basePath.'/Service/TestArticleServiceInterface.php');
        self::assertStringContainsString('function create(TestArticleInput $input): TestArticle', $serviceInterfaceContents);
    }
}
