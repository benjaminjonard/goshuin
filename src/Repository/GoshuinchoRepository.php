<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Service\Tally;
use App\Service\Summary;
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

    public function tally(): Tally
    {
        $row = $this->createQueryBuilder('g')
            ->select('COUNT(DISTINCT g.id) AS goshuinchos')
            ->addSelect('COUNT(goshuin.id) AS goshuins')
            ->addSelect('COUNT(DISTINCT location.locality) AS cities')
            ->addSelect('COUNT(DISTINCT location.prefecture) AS prefectures')
            ->leftJoin('g.goshuins', 'goshuin')
            ->leftJoin('goshuin.location', 'location')
            ->getQuery()
            ->getSingleResult()
        ;

        return new Tally(
            goshuincho: (int) $row['goshuinchos'],
            goshuin: (int) $row['goshuins'],
            cities: (int) $row['cities'],
            prefectures: (int) $row['prefectures'],
        );
    }

    /**
     * @return list<Goshuincho>
     */
    public function shelf(): array
    {
        return $this->createQueryBuilder('g')
            ->select('g')
            ->addSelect('COALESCE(MAX(goshuin.receivedOn), g.purchasedAt, :epoch) AS HIDDEN ranked')
            ->leftJoin('g.goshuins', 'goshuin')
            ->setParameter('epoch', new \DateTimeImmutable('@0'))
            ->groupBy('g.id')
            ->orderBy('ranked', 'DESC')
            ->addOrderBy('g.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function summary(Goshuincho $goshuincho): Summary
    {
        $row = $this->createQueryBuilder('g')
            ->select(
                'COUNT(goshuin.id) AS held',
                'COUNT(DISTINCT goshuin.location) AS places',
                'MIN(goshuin.receivedOn) AS first',
                'MAX(goshuin.receivedOn) AS last',
            )
            ->leftJoin('g.goshuins', 'goshuin')
            ->andWhere('g.id = :id')
            ->setParameter('id', $goshuincho->getId())
            ->getQuery()
            ->getSingleResult()
        ;

        return new Summary(
            goshuin: (int) $row['held'],
            locations: (int) $row['places'],
            cities: $this->places($goshuincho, 'locality'),
            prefectures: $this->places($goshuincho, 'prefecture'),
            first: $this->day($row['first']),
            last: $this->day($row['last']),
        );
    }

    /**
     * @return list<string>
     */
    private function places(Goshuincho $goshuincho, string $column): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select(sprintf('DISTINCT location.%s AS place', $column))
            ->from(Goshuin::class, 'goshuin')
            ->innerJoin('goshuin.location', 'location')
            ->andWhere('goshuin.goshuincho = :goshuincho')
            ->andWhere(sprintf('location.%s IS NOT NULL', $column))
            ->andWhere(sprintf("location.%s <> ''", $column))
            ->setParameter('goshuincho', $goshuincho)
            ->orderBy('place', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(static fn (array $row): string => (string) $row['place'], $rows);
    }

    private function day(?string $day): ?\DateTimeImmutable
    {
        return $day === null ? null : new \DateTimeImmutable($day);
    }

    public function withGoshuins(string $slug): ?Goshuincho
    {
        return $this->createQueryBuilder('g')
            ->addSelect('goshuin', 'location')
            ->leftJoin('g.goshuins', 'goshuin')
            ->leftJoin('goshuin.location', 'location')
            ->andWhere('g.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('goshuin.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
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
