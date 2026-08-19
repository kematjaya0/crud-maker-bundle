<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Util;

use Kematjaya\CrudMakerBundle\Util\EntityAttributeWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class EntityAttributeWriterTest extends TestCase
{
    private const SOURCE = <<<'PHP'
<?php

namespace App\Entity;

use App\Repository\NewsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsRepository::class)]
class News
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

PHP;

    private const ATTRIBUTE_CODE = <<<'PHP'
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]
PHP;

    public function testAddsClassAttributesAndUseStatementsWithoutTouchingExistingCode(): void
    {
        $writer = new EntityAttributeWriter();

        $result = $writer->addClassAttributes(self::SOURCE, self::ATTRIBUTE_CODE, [
            'ApiPlatform\Metadata\ApiResource',
            'ApiPlatform\Metadata\ApiFilter',
            'ApiPlatform\Metadata\GetCollection',
            'ApiPlatform\Metadata\Get',
            'ApiPlatform\Doctrine\Orm\Filter\SearchFilter',
        ]);

        // existing code untouched
        self::assertStringContainsString('#[ORM\Entity(repositoryClass: NewsRepository::class)]', $result);
        self::assertStringContainsString('use App\Repository\NewsRepository;', $result);
        self::assertStringContainsString('public function getId(): ?int', $result);

        // new attributes + use statements added
        self::assertStringContainsString('use ApiPlatform\Metadata\ApiResource;', $result);
        self::assertStringContainsString('use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;', $result);
        self::assertStringContainsString('#[ApiResource(', $result);
        self::assertStringContainsString("#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]", $result);

        $tmpFile = tempnam(sys_get_temp_dir(), 'entity_attr_writer_test_').'.php';
        file_put_contents($tmpFile, $result);
        $lint = new Process([\PHP_BINARY, '-l', $tmpFile]);
        $lint->run();
        unlink($tmpFile);
        self::assertTrue($lint->isSuccessful(), $lint->getErrorOutput());
    }

    public function testRefusesToAddADuplicateAttribute(): void
    {
        $writer = new EntityAttributeWriter();
        $alreadyDecorated = $writer->addClassAttributes(self::SOURCE, self::ATTRIBUTE_CODE, [
            'ApiPlatform\Metadata\ApiResource',
            'ApiPlatform\Metadata\ApiFilter',
            'ApiPlatform\Metadata\GetCollection',
            'ApiPlatform\Metadata\Get',
            'ApiPlatform\Doctrine\Orm\Filter\SearchFilter',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ApiResource/');

        $writer->addClassAttributes($alreadyDecorated, self::ATTRIBUTE_CODE, []);
    }
}
