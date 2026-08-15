<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\Prefecture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 24;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * @return list<City>
     */
    public function browse(?string $term = null, int $page = 1, ?Prefecture $narrow = null): array
    {
        return $this->listing($term, $narrow)
            ->orderBy('c.name', 'ASC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE)
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null, ?Prefecture $narrow = null): int
    {
        $total = (int) $this->listing($term, $narrow)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    public function namedExactly(string $name): ?City
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countIn(Prefecture $prefecture): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.prefecture = :prefecture')
            ->setParameter('prefecture', $prefecture)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function listing(?string $term, ?Prefecture $narrow): QueryBuilder
    {
        $builder = $this->createQueryBuilder('c');

        if ($term !== null && $term !== '') {
            $builder
                ->andWhere('LOWER(c.name) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%')
            ;
        }

        if ($narrow !== null) {
            $builder
                ->andWhere('c.prefecture = :narrow')
                ->setParameter('narrow', $narrow)
            ;
        }

        return $builder;
    }
}
