<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\City;
use App\Entity\Deity;
use App\Entity\Location;
use App\Entity\Prefecture;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Uses
{
    private const string LOCATION_COUNT = 'SELECT (SELECT COUNT(*) FROM gos_goshuin WHERE location_id = :id)
        + (SELECT COUNT(*) FROM gos_goshuincho WHERE bought_at_id = :id)';

    private const string DEITY_COUNT = 'SELECT COUNT(*) FROM gos_location_deity WHERE deity_id = :id';

    private const string CITY_COUNT = 'SELECT COUNT(*) FROM gos_location WHERE city_id = :id';

    private const string PREFECTURE_COUNT = 'SELECT (SELECT COUNT(*) FROM gos_location WHERE prefecture_id = :id)
        + (SELECT COUNT(*) FROM gos_city WHERE prefecture_id = :id)';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function of(Location|Deity|City|Prefecture $subject): int
    {
        $count = match (true) {
            $subject instanceof Location => self::LOCATION_COUNT,
            $subject instanceof Deity => self::DEITY_COUNT,
            $subject instanceof City => self::CITY_COUNT,
            $subject instanceof Prefecture => self::PREFECTURE_COUNT,
        };

        return (int) $this->entityManager->getConnection()->fetchOne($count, ['id' => $subject->getId()]);
    }
}
