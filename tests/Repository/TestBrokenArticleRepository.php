<?php

declare(strict_types=1);

namespace Kematjaya\CrudMakerBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Kematjaya\CrudMakerBundle\Tests\Entity\TestBrokenArticle;

/** @extends ServiceEntityRepository<TestBrokenArticle> */
class TestBrokenArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestBrokenArticle::class);
    }
}
