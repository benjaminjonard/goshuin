<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * @return list<Location>
     */
    public function search(string $term, int $limit = 8): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.romanizedName) LIKE :term OR LOWER(l.japaneseName) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
            ->orderBy('l.romanizedName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Location>
     */
    public function namedExactly(string $name): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.romanizedName) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->getQuery()
            ->getResult()
        ;
    }
}
