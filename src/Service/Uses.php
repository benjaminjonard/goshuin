<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Deity;
use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Uses
{
    private const string LOCATION_COUNT = 'SELECT (SELECT COUNT(*) FROM gos_goshuin WHERE location_id = :id)
        + (SELECT COUNT(*) FROM gos_goshuincho WHERE bought_at_id = :id)';

    private const string DEITY_COUNT = 'SELECT COUNT(*) FROM gos_location_deity WHERE deity_id = :id';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function of(Location|Deity $subject): int
    {
        $count = $subject instanceof Location ? self::LOCATION_COUNT : self::DEITY_COUNT;

        return (int) $this->entityManager->getConnection()->fetchOne($count, ['id' => $subject->getId()]);
    }
}
