<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kematjaya\CrudMakerBundle\Tests\Entity\TestArticle;

/** @extends ServiceEntityRepository<TestArticle> */
class TestArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestArticle::class);
    }
}
