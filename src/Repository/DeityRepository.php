<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deity>
 */
class DeityRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 24;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deity::class);
    }

    /**
     * @return list<Deity>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        $builder = $this->createQueryBuilder('d')->orderBy('d.name', 'ASC');

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
        $builder = $this->createQueryBuilder('d')->select('COUNT(d.id)');

        if ($term !== null && $term !== '') {
            $this->named($builder, $term);
        }

        $total = (int) $builder->getQuery()->getSingleScalarResult();

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    /**
     * @return list<Deity>
     */
    public function search(string $term, int $limit = 6): array
    {
        return $this->named($this->createQueryBuilder('d'), $term)
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

    private function named(QueryBuilder $builder, string $term): QueryBuilder
    {
        return $builder
            ->andWhere('LOWER(d.name) LIKE :term OR LOWER(JSON_TEXT(d.additionalNames)) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
        ;
    }
}
