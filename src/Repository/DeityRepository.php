<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deity>
 */
class DeityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deity::class);
    }

    /**
     * @return list<Deity>
     */
    public function search(string $term, int $limit = 6): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('LOWER(d.name) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
            ->orderBy('d.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function namedExactly(string $name): ?Deity
    {
        return $this->createQueryBuilder('d')
            ->andWhere('LOWER(d.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
