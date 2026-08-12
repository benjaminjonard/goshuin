<?php

declare(strict_types=1);

namespace App\Service;

final readonly class RegionResolver
{
    /**
     * @var list<array{0: string, 1: string, 2: float, 3: float, 4: float, 5: float}>
     */
    private const array REGIONS = [
        ['沖縄', 'Okinawa', 24.0, 27.9, 122.9, 131.4],
        ['九州', 'Kyūshū', 30.9, 34.0, 128.3, 132.1],
        ['四国', 'Shikoku', 32.7, 34.4, 132.0, 134.8],
        ['中国', 'Chūgoku', 33.8, 35.7, 130.9, 134.5],
        ['関西', 'Kansai', 33.4, 35.9, 134.0, 136.6],
        ['中部', 'Chūbu', 34.5, 37.6, 136.0, 139.2],
        ['関東', 'Kantō', 34.9, 37.2, 138.5, 141.0],
        ['東北', 'Tōhoku', 36.7, 41.6, 139.0, 142.1],
        ['北海道', 'Hokkaidō', 41.3, 45.6, 139.3, 146.0],
    ];

    public function resolve(?float $south, ?float $north, ?float $west, ?float $east): ?Region
    {
        if ($south === null || $north === null || $west === null || $east === null) {
            return null;
        }

        $latitude = ($south + $north) / 2;
        $longitude = ($west + $east) / 2;

        foreach (self::REGIONS as [$japanese, $romanized, $bottom, $top, $left, $right]) {
            if ($latitude >= $bottom && $latitude <= $top && $longitude >= $left && $longitude <= $right) {
                return new Region($japanese, $romanized);
            }
        }

        return null;
    }

    public function spread(?float $south, ?float $north, ?float $west, ?float $east): ?int
    {
        if ($south === null || $north === null || $west === null || $east === null) {
            return null;
        }

        $half = sin(deg2rad($north - $south) / 2) ** 2
            + cos(deg2rad($south)) * cos(deg2rad($north)) * sin(deg2rad($east - $west) / 2) ** 2;

        return (int) round(6371.0 * 2 * asin(min(1.0, sqrt($half))));
    }
}
