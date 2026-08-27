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
    public function browse(string $locale, ?string $term = null, int $page = 1): array
    {
        return $this->orderedByName($this->paginate($this->listing($term), $page), 'p', $locale)
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

    public function findById(string $id): ?Prefecture
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function listing(?string $term): QueryBuilder
    {
        $builder = $this->createQueryBuilder('p');

        if ($term !== null && $term !== '') {
            $this->matchingName($builder, 'p', $term);
        }

        return $builder;
    }
}
