<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deity;
use App\Entity\Location;
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
    public function browse(string $locale, ?string $term = null, int $page = 1): array
    {
        return $this->orderedByName($this->paginate($this->listing($term), $page), 'd', $locale)
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
    public function search(string $term, string $locale, int $limit = 6): array
    {
        return $this->orderedByName($this->named($this->createQueryBuilder('d'), $term), 'd', $locale)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function namedExactly(string $name): ?Deity
    {
        return $this->oneNamed($name);
    }

    public function findById(string $id): ?Deity
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return list<Deity>
     */
    public function enshrinedIn(Location $location, string $locale): array
    {
        $ids = $location->getDeities()->map(static fn (Deity $deity): string => $deity->getId())->toArray();

        if ($ids === []) {
            return [];
        }

        return $this->orderedByName($this->createQueryBuilder('d')
            ->andWhere('d.id IN (:ids)')
            ->setParameter('ids', $ids), 'd', $locale)
            ->getQuery()
            ->getResult()
        ;
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
            ->andWhere('LOWER(d.romanizedName) LIKE :term OR LOWER(d.kanjiName) LIKE :term OR LOWER(d.kanaName) LIKE :term OR LOWER(JSON_TEXT(d.additionalNames)) LIKE :term')
            ->setParameter('term', '%'.mb_strtolower($term).'%')
        ;
    }
}
