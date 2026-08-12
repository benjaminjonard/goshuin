<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Service\RegionResolver;
use App\Service\Summary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Goshuincho>
 */
class GoshuinchoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly RegionResolver $regions)
    {
        parent::__construct($registry, Goshuincho::class);
    }

    public function summary(Goshuincho $goshuincho): Summary
    {
        $row = $this->createQueryBuilder('g')
            ->select(
                'COUNT(goshuin.id) AS held',
                'COUNT(DISTINCT goshuin.location) AS places',
                'MIN(goshuin.receivedOn) AS first',
                'MAX(goshuin.receivedOn) AS last',
                'MIN(location.latitude) AS south',
                'MAX(location.latitude) AS north',
                'MIN(location.longitude) AS west',
                'MAX(location.longitude) AS east',
            )
            ->leftJoin('g.goshuins', 'goshuin')
            ->leftJoin('goshuin.location', 'location')
            ->andWhere('g.id = :id')
            ->setParameter('id', $goshuincho->getId())
            ->getQuery()
            ->getSingleResult()
        ;

        $corners = array_map(
            static fn (string $corner): ?float => $row[$corner] === null ? null : (float) $row[$corner],
            ['south' => 'south', 'north' => 'north', 'west' => 'west', 'east' => 'east'],
        );

        return new Summary(
            goshuin: (int) $row['held'],
            locations: (int) $row['places'],
            spend: $this->spend($goshuincho),
            first: $this->day($row['first']),
            last: $this->day($row['last']),
            region: $this->regions->resolve(...array_values($corners)),
            spread: $this->regions->spread(...array_values($corners)),
        );
    }

    /**
     * @return array<string, int>
     */
    private function spend(Goshuincho $goshuincho): array
    {
        $spent = [];

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('goshuin.currency AS currency', 'SUM(goshuin.price) AS total')
            ->from(Goshuin::class, 'goshuin')
            ->andWhere('goshuin.goshuincho = :goshuincho')
            ->andWhere('goshuin.price IS NOT NULL')
            ->setParameter('goshuincho', $goshuincho)
            ->groupBy('goshuin.currency')
            ->getQuery()
            ->getArrayResult()
        ;

        foreach ($rows as $row) {
            $spent[(string) $row['currency']] = (int) $row['total'];
        }

        return $spent;
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
