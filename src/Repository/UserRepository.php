<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function hasNone(): bool
    {
        return $this->count([]) === 0;
    }

    public function countAdministrators(): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT count(*) FROM gos_user WHERE jsonb_exists(roles::jsonb, :role)',
            ['role' => 'ROLE_ADMIN'],
        );
    }
}
