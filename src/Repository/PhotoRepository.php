<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goshuin;
use App\Entity\Photo;
use App\Enum\PhotoType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * @return list<Photo>
     */
    public function ofType(Goshuin $goshuin, PhotoType $type): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.goshuin = :goshuin')
            ->andWhere('p.type = :type')
            ->setParameter('goshuin', $goshuin)
            ->setParameter('type', $type)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function atPosition(Goshuin $goshuin, PhotoType $type, int $position): ?Photo
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.goshuin = :goshuin')
            ->andWhere('p.type = :type')
            ->andWhere('p.position = :position')
            ->setParameter('goshuin', $goshuin)
            ->setParameter('type', $type)
            ->setParameter('position', $position)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function lastPosition(Goshuin $goshuin, PhotoType $type): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(MAX(p.position), 0)')
            ->andWhere('p.goshuin = :goshuin')
            ->andWhere('p.type = :type')
            ->setParameter('goshuin', $goshuin)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Photo>
     */
    public function range(Goshuin $goshuin, PhotoType $type, int $low, int $high, bool $descending): array
    {
        if ($low > $high) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.goshuin = :goshuin')
            ->andWhere('p.type = :type')
            ->andWhere('p.position BETWEEN :low AND :high')
            ->setParameter('goshuin', $goshuin)
            ->setParameter('type', $type)
            ->setParameter('low', $low)
            ->setParameter('high', $high)
            ->orderBy('p.position', $descending ? 'DESC' : 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
