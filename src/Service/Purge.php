<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\GoshuinchoRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Purge
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GoshuinchoRepository $goshuinchos,
    ) {
    }

    public function of(User $user): void
    {
        foreach ($this->goshuinchos->findBy(['owner' => $user]) as $goshuincho) {
            $this->entityManager->remove($goshuincho);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
