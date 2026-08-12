<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::prePersist)]
final readonly class OwnerListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'getOwner') || !method_exists($entity, 'setOwner')) {
            return;
        }

        if ($entity->getOwner() !== null) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof User) {
            $entity->setOwner($user);
        }
    }
}
