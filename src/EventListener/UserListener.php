<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preFlush)]
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

    public function preFlush(PreFlushEventArgs $args): void
    {
        $identityMap = $args->getObjectManager()->getUnitOfWork()->getIdentityMap();

        foreach ($identityMap[User::class] ?? [] as $user) {
            $this->hash($user);
        }
    }

    private function hash(object $user): void
    {
        if (!$user instanceof User || $user->getPlainPassword() === null) {
            return;
        }

        $user->setPassword($this->hasher->hashPassword($user, $user->getPlainPassword()));
        $user->eraseCredentials();
    }
}
