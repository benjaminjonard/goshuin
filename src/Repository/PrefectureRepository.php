<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Prefecture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prefecture>
 */
class PrefectureRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 24;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prefecture::class);
    }

    /**
     * @return list<Prefecture>
     */
    public function browse(?string $term = null, int $page = 1, ?Goshuincho $goshuincho = null): array
    {
        return $this->listing($term, $goshuincho)
            ->orderBy('p.name', 'ASC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE)
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null, ?Goshuincho $goshuincho = null): int
    {
        $total = (int) $this->listing($term, $goshuincho)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    public function namedExactly(string $name): ?Prefecture
    {
        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function listing(?string $term, ?Goshuincho $goshuincho): QueryBuilder
    {
        $builder = $this->createQueryBuilder('p');

        if ($term !== null && $term !== '') {
            $builder
                ->andWhere('LOWER(p.name) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%')
            ;
        }

        if ($goshuincho !== null) {
            $visited = $this->getEntityManager()->createQueryBuilder()
                ->select('IDENTITY(location.prefecture)')
                ->from(Goshuin::class, 'goshuin')
                ->innerJoin('goshuin.location', 'location')
                ->andWhere('goshuin.goshuincho = :goshuincho')
            ;

            $builder
                ->andWhere($builder->expr()->in('p.id', $visited->getDQL()))
                ->setParameter('goshuincho', $goshuincho)
            ;
        }

        return $builder;
    }
}
