<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Uses
{
    private const string COUNT = 'SELECT (SELECT COUNT(*) FROM gos_goshuin WHERE location_id = :id)
        + (SELECT COUNT(*) FROM gos_goshuincho WHERE bought_at_id = :id)';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function of(Location $location): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(self::COUNT, ['id' => $location->getId()]);
    }
}
