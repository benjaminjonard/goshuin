<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Entity\Prefecture;
use App\Model\Summary;
use App\Model\Tally;
use App\Repository\Trait\Distributes;
use App\Repository\Trait\Paginates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Goshuincho>
 */
class GoshuinchoRepository extends ServiceEntityRepository
{
    use Distributes;
    use Paginates;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goshuincho::class);
    }

    /**
     * @return list<Goshuincho>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        return $this->paginate($this->listing($term), $page)
            ->orderBy('g.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null): int
    {
        return $this->pagesOf($this->listing($term));
    }

    public function findById(string $id): ?Goshuincho
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function listing(?string $term): QueryBuilder
    {
        $builder = $this->createQueryBuilder('g');

        if ($term !== null && $term !== '') {
            $builder
                ->andWhere('LOWER(g.title) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%')
            ;
        }

        return $builder;
    }

    public function tally(): Tally
    {
        $row = $this->createQueryBuilder('g')
            ->select('COUNT(DISTINCT g.id) AS goshuinchos')
            ->addSelect('COUNT(goshuin.id) AS goshuins')
            ->addSelect('COUNT(DISTINCT location.city) AS cities')
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
     * @return array{zones: array<string, int>, unlocated: int}
     */
    public function coverage(): array
    {
        return $this->zonesOf($this->createQueryBuilder('g')
            ->select('location.municipalityCode AS code')
            ->addSelect('COUNT(g.id) AS held')
            ->leftJoin('g.boughtAt', 'location')
            ->groupBy('location.municipalityCode')
            ->orderBy('location.municipalityCode', 'ASC')
            ->getQuery()
            ->getArrayResult());
    }

    /**
     * @return array{years: array<int, int>, months: array<int, int>, weekdays: array<int, int>, undated: int}
     */
    public function spans(): array
    {
        return $this->spansOf($this->createQueryBuilder('g')
            ->select('g.purchasedAt AS day')
            ->addSelect('COUNT(g.id) AS held')
            ->groupBy('g.purchasedAt')
            ->orderBy('g.purchasedAt', 'ASC')
            ->getQuery()
            ->getArrayResult());
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

    /**
     * @return list<Goshuincho>
     */
    public function holding(City|Prefecture $place): array
    {
        return $this->createQueryBuilder('g')
            ->distinct()
            ->innerJoin('g.goshuins', 'goshuin')
            ->innerJoin('goshuin.location', 'location')
            ->andWhere(sprintf('location.%s = :place', $place instanceof City ? 'city' : 'prefecture'))
            ->setParameter('place', $place)
            ->orderBy('g.title', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function summary(Goshuincho $goshuincho, string $locale): Summary
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
            cities: $this->places($goshuincho, City::class, 'city', $locale),
            prefectures: $this->places($goshuincho, Prefecture::class, 'prefecture', $locale),
            first: $this->day($row['first']),
            last: $this->day($row['last']),
        );
    }

    /**
     * @param class-string<City|Prefecture> $class
     *
     * @return list<City|Prefecture>
     */
    private function places(Goshuincho $goshuincho, string $class, string $association, string $locale): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT place')
            ->from($class, 'place')
            ->innerJoin(Location::class, 'location', Join::WITH, sprintf('location.%s = place', $association))
            ->innerJoin(Goshuin::class, 'goshuin', Join::WITH, 'goshuin.location = location')
            ->andWhere('goshuin.goshuincho = :goshuincho')
            ->setParameter('goshuincho', $goshuincho)
            ->addSelect(sprintf('COALESCE(%s) AS HIDDEN name_order', implode(', ', array_map(
                static fn (string $field): string => sprintf("NULLIF(place.%s, '')", $field),
                $class::orderFields($locale),
            ))))
            ->orderBy('name_order', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    private function day(?string $day): ?\DateTimeImmutable
    {
        return $day === null ? null : new \DateTimeImmutable($day);
    }

    public function withGoshuins(string $id): ?Goshuincho
    {
        return $this->createQueryBuilder('g')
            ->addSelect('goshuin', 'location')
            ->leftJoin('g.goshuins', 'goshuin')
            ->leftJoin('goshuin.location', 'location')
            ->andWhere('g.id = :id')
            ->setParameter('id', $id)
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
