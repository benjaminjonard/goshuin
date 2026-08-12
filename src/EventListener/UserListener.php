<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Hashing belongs here rather than in a controller: every path that sets a plain
 * password — setup, the administrator's user form, a User changing their own —
 * goes through a flush, and none of them should have to remember to hash.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class UserListener
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->hash($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $user = $args->getObject();
        if ($this->hash($user)) {
            $args->getObjectManager()->getUnitOfWork()->recomputeSingleEntityChangeSet(
                $args->getObjectManager()->getClassMetadata($user::class),
                $user,
            );
        }
    }

    private function hash(object $user): bool
    {
        if (!$user instanceof User || $user->getPlainPassword() === null) {
            return false;
        }

        $user->setPassword($this->hasher->hashPassword($user, $user->getPlainPassword()));
        $user->eraseCredentials();

        return true;
    }
}
