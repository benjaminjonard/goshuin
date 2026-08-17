<?php

declare(strict_types=1);

namespace App\Repository\Trait;

use Doctrine\ORM\QueryBuilder;

trait Paginates
{
    private const int PER_PAGE = 24;

    private function paginate(QueryBuilder $builder, int $page): QueryBuilder
    {
        return $builder
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE)
        ;
    }

    private function pagesOf(QueryBuilder $builder): int
    {
        $total = (int) $builder
            ->select(sprintf('COUNT(%s.id)', $builder->getRootAliases()[0]))
            ->getQuery()
            ->getSingleScalarResult();

        return max(1, (int) ceil($total / self::PER_PAGE));
    }
}
