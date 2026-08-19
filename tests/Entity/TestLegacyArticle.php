<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kematjaya\CrudMakerBundle\Tests\Repository\TestLegacyArticleRepository;

/**
 * Fixture entity mirroring a plain make:entity-scaffolded entity (auto-increment int id, no
 * explicit constructor, setter-based writes, no update() method) — used to smoke-test
 * make:kmj-api-crud's setter-mode write detection (ApiCrudRenderer::detectWriteMode()).
 */
#[ORM\Entity(repositoryClass: TestLegacyArticleRepository::class)]
class TestLegacyArticle
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

    public function setHeadline(string $headline): static
    {
        $this->headline = $headline;

        return $this;
    }
}
