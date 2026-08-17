<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Prefecture;
use App\Repository\Trait\FindsByName;
use App\Repository\Trait\Paginates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prefecture>
 */
class PrefectureRepository extends ServiceEntityRepository
{
    use FindsByName;
    use Paginates;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prefecture::class);
    }

    /**
     * @return list<Prefecture>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        return $this->paginate($this->listing($term), $page)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null): int
    {
        return $this->pagesOf($this->listing($term));
    }

    public function namedExactly(string $name): ?Prefecture
    {
        return $this->oneNamed($name);
    }

    private function listing(?string $term): QueryBuilder
    {
        $builder = $this->createQueryBuilder('p');

        if ($term !== null && $term !== '') {
            $builder
                ->andWhere('LOWER(p.name) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%')
            ;
        }

        return $builder;
    }
}
