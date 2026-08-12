<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Goshuincho;
use App\Repository\GoshuinchoRepository;
use App\Service\HueAllocator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
final readonly class HueListener
{
    public function __construct(
        private GoshuinchoRepository $repository,
        private HueAllocator $allocator,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $goshuincho = $args->getObject();

        if (!$goshuincho instanceof Goshuincho || $goshuincho->getHue() !== null) {
            return;
        }

        $goshuincho->setHue($this->allocator->next($this->repository->usedHues()));
    }
}
