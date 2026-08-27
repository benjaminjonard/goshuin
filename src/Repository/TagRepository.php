<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use App\Repository\Trait\Paginates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    use Paginates;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * @return list<Tag>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        return $this->paginate($this->listing($term), $page)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null): int
    {
        return $this->pagesOf($this->listing($term));
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

    public function findById(string $id): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function listing(?string $term): QueryBuilder
    {
        $builder = $this->createQueryBuilder('t');

        if ($term !== null && $term !== '') {
            $this->named($builder, $term);
        }

        return $builder;
    }

    private function named(QueryBuilder $builder, string $term): QueryBuilder
    {
        return $builder
            ->andWhere('LOWER(t.name) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
        ;
    }
}
