<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity tanpa repository class
 */
#[ORM\Entity]
class Category
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 20)]
    private ?string $code = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $name = null;

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
