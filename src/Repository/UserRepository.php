<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Model\Holdings;
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

    public function holdings(User $user): Holdings
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT (SELECT COUNT(*) FROM gos_goshuincho WHERE owner_id = :id) AS goshuinchos,
                (SELECT COUNT(*) FROM gos_goshuin WHERE owner_id = :id) AS goshuins,
                (SELECT COUNT(*) FROM gos_photo WHERE owner_id = :id) AS photographs',
            ['id' => $user->getId()],
        );

        return new Holdings(
            goshuincho: (int) $row['goshuinchos'],
            goshuin: (int) $row['goshuins'],
            photographs: (int) $row['photographs'],
        );
    }

    public function countAdministrators(?string $excluding = null): int
    {
        $sql = 'SELECT count(*) FROM gos_user WHERE jsonb_exists(roles::jsonb, :role) AND enabled = true';
        $parameters = ['role' => 'ROLE_ADMIN'];

        if ($excluding !== null) {
            $sql .= ' AND id <> :excluding';
            $parameters['excluding'] = $excluding;
        }

        return (int) $this->getEntityManager()->getConnection()->fetchOne($sql, $parameters);
    }
}
