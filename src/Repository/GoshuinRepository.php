<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Goshuin>
 */
class GoshuinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goshuin::class);
    }

    public function atPosition(Goshuincho $goshuincho, int $position): ?Goshuin
    {
        return $this->createQueryBuilder('g')
            ->addSelect('location')
            ->innerJoin('g.location', 'location')
            ->andWhere('g.goshuincho = :goshuincho')
            ->andWhere('g.position = :position')
            ->setParameter('goshuincho', $goshuincho)
            ->setParameter('position', $position)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countIn(Goshuincho $goshuincho): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.goshuincho = :goshuincho')
            ->setParameter('goshuincho', $goshuincho)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function lastPosition(Goshuincho $goshuincho): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COALESCE(MAX(g.position), 0)')
            ->andWhere('g.goshuincho = :goshuincho')
            ->setParameter('goshuincho', $goshuincho)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Goshuin>
     */
    public function inOrder(Goshuincho $goshuincho): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.goshuincho = :goshuincho')
            ->setParameter('goshuincho', $goshuincho)
            ->orderBy('g.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<int>
     */
    public function positions(Goshuincho $goshuincho): array
    {
        return array_map(
            static fn (array $row): int => $row['position'],
            $this->createQueryBuilder('g')
                ->select('g.position')
                ->andWhere('g.goshuincho = :goshuincho')
                ->setParameter('goshuincho', $goshuincho)
                ->orderBy('g.position', 'ASC')
                ->getQuery()
                ->getArrayResult(),
        );
    }
}
