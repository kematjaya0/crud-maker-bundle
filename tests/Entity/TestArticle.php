<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Kematjaya\CrudMakerBundle\Tests\Repository\TestArticleRepository;

/**
 * Fixture entity mirroring the shape of the boilerplate's own App\Entity\Note (owner +
 * a couple of scalar fields + a managed timestamp) — used to smoke-test make:kmj-api-crud.
 */
#[ORM\Entity(repositoryClass: TestArticleRepository::class)]
class TestArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private TestOwner $owner;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(TestOwner $owner, string $title, string $body)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): TestOwner
    {
        return $this->owner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function update(string $title, string $body): void
    {
        $this->title = $title;
        $this->body = $body;
    }
}
