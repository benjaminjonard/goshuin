<?php

declare(strict_types=1);

namespace App\Repository\Trait;

use Doctrine\ORM\QueryBuilder;

trait FindsByName
{
    private function oneNamed(string $name): ?object
    {
        return $this->matchingName($this->createQueryBuilder('e'), 'e', $name, exact: true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function matchingName(QueryBuilder $builder, string $alias, string $term, bool $exact = false): QueryBuilder
    {
        $comparison = $exact ? '= :term' : 'LIKE :term';

        return $builder
            ->andWhere(sprintf(
                'LOWER(%1$s.romanizedName) %2$s OR LOWER(%1$s.kanjiName) %2$s OR LOWER(%1$s.kanaName) %2$s',
                $alias,
                $comparison,
            ))
            ->setParameter('term', $exact ? mb_strtolower($term) : '%'.mb_strtolower($term).'%')
        ;
    }

    private function orderedByName(QueryBuilder $builder, string $alias, string $locale): QueryBuilder
    {
        $chain = implode(', ', array_map(
            static fn (string $field): string => sprintf("NULLIF(%s.%s, '')", $alias, $field),
            $this->getClassName()::orderFields($locale),
        ));

        return $builder
            ->addSelect(sprintf('COALESCE(%s) AS HIDDEN name_order', $chain))
            ->orderBy('name_order', 'ASC')
        ;
    }
}
