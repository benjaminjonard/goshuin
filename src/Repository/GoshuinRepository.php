<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Service\Pin;
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

    /**
     * @return list<Goshuin>
     */
    public function recent(int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->addSelect('location', 'goshuincho')
            ->addSelect('COALESCE(g.receivedOn, :epoch) AS HIDDEN ranked')
            ->innerJoin('g.location', 'location')
            ->innerJoin('g.goshuincho', 'goshuincho')
            ->setParameter('epoch', new \DateTimeImmutable('@0'))
            ->orderBy('ranked', 'DESC')
            ->addOrderBy('g.position', 'ASC')
            ->addOrderBy('g.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Pin>
     */
    public function pins(): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('location.romanizedName AS name')
            ->addSelect('location.latitude AS latitude')
            ->addSelect('location.longitude AS longitude')
            ->addSelect('COUNT(g.id) AS held')
            ->addSelect('COUNT(DISTINCT goshuincho.id) AS holders')
            ->addSelect('MIN(goshuincho.title) AS title')
            ->addSelect('MIN(goshuincho.slug) AS slug')
            ->addSelect('MIN(goshuincho.hue) AS hue')
            ->innerJoin('g.location', 'location')
            ->innerJoin('g.goshuincho', 'goshuincho')
            ->andWhere('location.latitude IS NOT NULL')
            ->andWhere('location.longitude IS NOT NULL')
            ->groupBy('location.id')
            ->orderBy('location.romanizedName', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(
            static function (array $row): Pin {
                $alone = (int) $row['holders'] === 1;

                return new Pin(
                    name: (string) $row['name'],
                    latitude: (float) $row['latitude'],
                    longitude: (float) $row['longitude'],
                    goshuin: (int) $row['held'],
                    title: $alone ? (string) $row['title'] : null,
                    slug: $alone ? (string) $row['slug'] : null,
                    hue: $alone && $row['hue'] !== null ? (int) $row['hue'] : null,
                );
            },
            $rows,
        );
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
