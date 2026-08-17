<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deity;
use App\Repository\Trait\FindsByName;
use App\Repository\Trait\Paginates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deity>
 */
class DeityRepository extends ServiceEntityRepository
{
    use FindsByName;
    use Paginates;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deity::class);
    }

    /**
     * @return list<Deity>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        return $this->paginate($this->listing($term), $page)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null): int
    {
        return $this->pagesOf($this->listing($term));
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
        return $this->oneNamed($name);
    }

    private function listing(?string $term): QueryBuilder
    {
        $builder = $this->createQueryBuilder('d');

        if ($term !== null && $term !== '') {
            $this->named($builder, $term);
        }

        return $builder;
    }

    private function named(QueryBuilder $builder, string $term): QueryBuilder
    {
        return $builder
            ->andWhere('LOWER(d.name) LIKE :term OR LOWER(JSON_TEXT(d.additionalNames)) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
        ;
    }
}
