<?php

declare(strict_types=1);

namespace App\Repository\Trait;

trait FindsByName
{
    private function oneNamed(string $name): ?object
    {
        return $this->createQueryBuilder('e')
            ->andWhere('LOWER(e.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
