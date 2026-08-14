<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Location;
use App\Entity\LocationPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocationPhoto>
 */
class LocationPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocationPhoto::class);
    }

    /**
     * @return list<LocationPhoto>
     */
    public function ofLocation(Location $location): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.location = :location')
            ->setParameter('location', $location)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function lastPosition(Location $location): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(MAX(p.position), 0)')
            ->andWhere('p.location = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
