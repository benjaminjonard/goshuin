<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deity;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 24;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * @return list<Location>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        $builder = $this->createQueryBuilder('l')->orderBy('l.romanizedName', 'ASC');

        if ($term !== null && $term !== '') {
            $this->named($builder, $term);
        }

        return $builder
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE)
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null): int
    {
        $builder = $this->createQueryBuilder('l')->select('COUNT(l.id)');

        if ($term !== null && $term !== '') {
            $this->named($builder, $term);
        }

        $total = (int) $builder->getQuery()->getSingleScalarResult();

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    /**
     * @return list<Location>
     */
    public function enshrining(Deity $deity): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.deities', 'd')
            ->andWhere('d = :deity')
            ->setParameter('deity', $deity)
            ->orderBy('l.romanizedName', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Location>
     */
    public function search(string $term, int $limit = 8): array
    {
        return $this->named($this->createQueryBuilder('l'), $term)
            ->orderBy('l.romanizedName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    private function named(QueryBuilder $builder, string $term): QueryBuilder
    {
        return $builder
            ->andWhere('LOWER(l.romanizedName) LIKE :term OR LOWER(l.japaneseName) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
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
