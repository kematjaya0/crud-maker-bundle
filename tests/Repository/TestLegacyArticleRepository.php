<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kematjaya\CrudMakerBundle\Tests\Entity\TestLegacyArticle;

/** @extends ServiceEntityRepository<TestLegacyArticle> */
class TestLegacyArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestLegacyArticle::class);
    }
}
