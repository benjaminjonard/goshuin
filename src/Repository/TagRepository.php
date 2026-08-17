<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 24;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * @return list<Tag>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        $builder = $this->createQueryBuilder('t')->orderBy('t.name', 'ASC');

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
        $builder = $this->createQueryBuilder('t')->select('COUNT(t.id)');

        if ($term !== null && $term !== '') {
            $this->named($builder, $term);
        }

        $total = (int) $builder->getQuery()->getSingleScalarResult();

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    public function namedExactly(string $name): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function named(QueryBuilder $builder, string $term): QueryBuilder
    {
        return $builder
            ->andWhere('LOWER(t.name) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
        ;
    }
}
