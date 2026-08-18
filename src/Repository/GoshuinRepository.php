<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Prefecture;
use App\Entity\Tag;
use App\Model\Pin;
use App\Repository\Trait\Paginates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Goshuin>
 */
class GoshuinRepository extends ServiceEntityRepository
{
    use Paginates;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goshuin::class);
    }

    /**
     * @return list<Goshuin>
     */
    public function browse(?string $term = null, int $page = 1, ?Tag $tag = null): array
    {
        return $this->paginate($this->listing($term, $tag), $page)
            ->addSelect('location', 'goshuincho')
            ->addSelect('COALESCE(g.receivedOn, :epoch) AS HIDDEN ranked')
            ->setParameter('epoch', new \DateTimeImmutable('@0'))
            ->orderBy('ranked', 'DESC')
            ->addOrderBy('g.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null, ?Tag $tag = null): int
    {
        return $this->pagesOf($this->listing($term, $tag));
    }

    /**
     * @return list<Goshuin>
     */
    public function from(City|Prefecture $place): array
    {
        return $this->createQueryBuilder('g')
            ->addSelect('location', 'goshuincho')
            ->addSelect('COALESCE(g.receivedOn, :epoch) AS HIDDEN ranked')
            ->innerJoin('g.location', 'location')
            ->innerJoin('g.goshuincho', 'goshuincho')
            ->andWhere(sprintf('location.%s = :place', $place instanceof City ? 'city' : 'prefecture'))
            ->setParameter('place', $place)
            ->setParameter('epoch', new \DateTimeImmutable('@0'))
            ->orderBy('ranked', 'DESC')
            ->addOrderBy('g.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    private function listing(?string $term, ?Tag $tag = null): QueryBuilder
    {
        $builder = $this->createQueryBuilder('g')
            ->innerJoin('g.location', 'location')
            ->innerJoin('g.goshuincho', 'goshuincho')
        ;

        if ($tag !== null) {
            $builder
                ->innerJoin('g.tags', 'tag')
                ->andWhere('tag = :tag')
                ->setParameter('tag', $tag)
            ;
        }

        if ($term !== null && $term !== '') {
            $builder
                ->andWhere('LOWER(location.romanizedName) LIKE :term OR LOWER(location.japaneseName) LIKE :term OR LOWER(goshuincho.title) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%')
            ;
        }

        return $builder;
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
            ->addSelect('g.position AS position')
            ->addSelect('goshuincho.title AS title')
            ->addSelect('goshuincho.slug AS slug')
            ->addSelect('goshuincho.hue AS hue')
            ->innerJoin('g.location', 'location')
            ->innerJoin('g.goshuincho', 'goshuincho')
            ->andWhere('location.latitude IS NOT NULL')
            ->andWhere('location.longitude IS NOT NULL')
            ->orderBy('goshuincho.title', 'ASC')
            ->addOrderBy('g.position', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(
            static fn (array $row): Pin => new Pin(
                name: (string) $row['name'],
                latitude: (float) $row['latitude'],
                longitude: (float) $row['longitude'],
                position: (int) $row['position'],
                title: (string) $row['title'],
                slug: (string) $row['slug'],
                hue: $row['hue'] === null ? null : (int) $row['hue'],
            ),
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

    /**
     * @param list<Tag> $tags
     *
     * @return array<string, int>
     */
    public function countPerTag(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $counted = [];

        $rows = $this->createQueryBuilder('g')
            ->select('tag.id AS id')
            ->addSelect('COUNT(g.id) AS held')
            ->innerJoin('g.tags', 'tag')
            ->andWhere('tag IN (:tags)')
            ->setParameter('tags', $tags)
            ->groupBy('tag.id')
            ->getQuery()
            ->getArrayResult()
        ;

        foreach ($rows as $row) {
            $counted[(string) $row['id']] = (int) $row['held'];
        }

        return $counted;
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
