<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\Deity;
use App\Entity\Location;
use App\Entity\Prefecture;
use App\Repository\Trait\FindsByName;
use App\Repository\Trait\Paginates;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    use FindsByName;
    use Paginates;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * @return list<Location>
     */
    public function browse(string $locale, ?string $term = null, int $page = 1, City|Prefecture|null $narrow = null): array
    {
        return $this->orderedByName($this->paginate($this->listing($term, $narrow), $page), 'l', $locale)
            ->getQuery()
            ->getResult()
        ;
    }

    public function pages(?string $term = null, City|Prefecture|null $narrow = null): int
    {
        return $this->pagesOf($this->listing($term, $narrow));
    }

    /**
     * @return list<Location>
     */
    public function withoutMunicipalityCode(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.municipalityCode IS NULL')
            ->andWhere('l.latitude IS NOT NULL')
            ->andWhere('l.longitude IS NOT NULL')
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function countIn(City|Prefecture $place): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere(sprintf('l.%s = :place', $place instanceof City ? 'city' : 'prefecture'))
            ->setParameter('place', $place)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findById(string $id): ?Location
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function listing(?string $term, City|Prefecture|null $narrow): QueryBuilder
    {
        $builder = $this->createQueryBuilder('l');

        if ($term !== null && $term !== '') {
            $this->matchingName($builder, 'l', $term);
        }

        if ($narrow !== null) {
            $builder
                ->andWhere(sprintf('l.%s = :narrow', $narrow instanceof City ? 'city' : 'prefecture'))
                ->setParameter('narrow', $narrow)
            ;
        }

        return $builder;
    }

    /**
     * @return list<Location>
     */
    public function enshrining(Deity $deity, string $locale): array
    {
        return $this->orderedByName($this->createQueryBuilder('l')
            ->innerJoin('l.deities', 'd')
            ->andWhere('d = :deity')
            ->setParameter('deity', $deity), 'l', $locale)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Location>
     */
    public function search(string $term, string $locale, int $limit = 8): array
    {
        return $this->orderedByName($this->matchingName($this->createQueryBuilder('l'), 'l', $term), 'l', $locale)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Location>
     */
    public function namedExactly(string $name): array
    {
        return $this->matchingName($this->createQueryBuilder('l'), 'l', $name, exact: true)
            ->getQuery()
            ->getResult()
        ;
    }
}
