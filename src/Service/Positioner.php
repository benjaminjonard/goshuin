<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Repository\GoshuinRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Positioner
{
    public function __construct(
        private EntityManagerInterface $manager,
        private GoshuinRepository $repository,
    ) {
    }

    public function add(Goshuin $goshuin): void
    {
        $goshuincho = $goshuin->getGoshuincho();

        if (!$goshuincho instanceof Goshuincho) {
            throw new \LogicException('A Goshuin is positioned within its Goshuincho.');
        }

        $this->manager->wrapInTransaction(function () use ($goshuin, $goshuincho): void {
            $this->manager->lock($goshuincho, LockMode::PESSIMISTIC_WRITE);
            $goshuin->setPosition($this->repository->lastPosition($goshuincho) + 1);
            $this->manager->persist($goshuin);
            $this->manager->flush();
        });
    }
}
