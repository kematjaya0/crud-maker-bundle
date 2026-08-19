<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kematjaya\CrudMakerBundle\Tests\Repository\TestBrokenArticleRepository;

/**
 * Fixture entity with NEITHER a compatible constructor+update() NOR a setter for every field —
 * used to assert make:kmj-api-crud fails loudly (ApiCrudRenderer::detectWriteMode()) instead of
 * silently generating a Service that would drop data / fatal at runtime.
 */
#[ORM\Entity(repositoryClass: TestBrokenArticleRepository::class)]
class TestBrokenArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $headline = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    // Deliberately no setHeadline(), no constructor, no update().
}
