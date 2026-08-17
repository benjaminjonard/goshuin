<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\Prefecture;
use App\Repository\Trait\FindsByName;
use App\Repository\Trait\Paginates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    use FindsByName;
    use Paginates;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * @return list<City>
     */
    public function browse(?string $term = null, int $page = 1, ?Prefecture $narrow = null): array
    {
        return $this->paginate($this->listing($term, $narrow), $page)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null, ?Prefecture $narrow = null): int
    {
        return $this->pagesOf($this->listing($term, $narrow));
    }

    public function namedExactly(string $name): ?City
    {
        return $this->oneNamed($name);
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
