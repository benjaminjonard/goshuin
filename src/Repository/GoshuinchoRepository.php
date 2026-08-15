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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Goshuincho>
 */
class GoshuinchoRepository extends ServiceEntityRepository
{
    private const int PER_PAGE = 24;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goshuincho::class);
    }

    /**
     * @return list<Goshuincho>
     */
    public function browse(?string $term = null, int $page = 1): array
    {
        return $this->listing($term)
            ->orderBy('g.title', 'ASC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE)
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null): int
    {
        $total = (int) $this->listing($term)
            ->select('COUNT(g.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return max(1, (int) ceil($total / self::PER_PAGE));
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
            cities: $this->places($goshuincho, City::class, 'city'),
            prefectures: $this->places($goshuincho, Prefecture::class, 'prefecture'),
            first: $this->day($row['first']),
            last: $this->day($row['last']),
        );
    }

    /**
     * @param class-string<City|Prefecture> $class
     *
     * @return list<City|Prefecture>
     */
    private function places(Goshuincho $goshuincho, string $class, string $association): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT place')
            ->from($class, 'place')
            ->innerJoin(Location::class, 'location', Join::WITH, sprintf('location.%s = place', $association))
            ->innerJoin(Goshuin::class, 'goshuin', Join::WITH, 'goshuin.location = location')
            ->andWhere('goshuin.goshuincho = :goshuincho')
            ->setParameter('goshuincho', $goshuincho)
            ->orderBy('place.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
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
