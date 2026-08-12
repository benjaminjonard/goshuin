<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goshuincho;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Goshuincho>
 */
class GoshuinchoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goshuincho::class);
    }

    /**
     * @return list<int>
     */
    public function usedHues(): array
    {
        return array_map(
            static fn (array $row): int => $row['hue'],
            $this->createQueryBuilder('g')
                ->select('g.hue')
                ->getQuery()
                ->getArrayResult(),
        );
    }
}
