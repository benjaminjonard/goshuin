<?php

declare(strict_types=1);

namespace App\Service;

final readonly class HueAllocator
{
    private const float GOLDEN_ANGLE = 137.50776405003785;

    private const int MINIMUM_DISTANCE = 24;

    /**
     * @param list<int> $used
     */
    public function next(array $used): int
    {
        $best = 0;
        $bestDistance = -1;

        for ($step = 0; $step < 360; ++$step) {
            $candidate = (int) round($step * self::GOLDEN_ANGLE) % 360;
            $distance = $this->distanceFrom($candidate, $used);

            if ($distance >= self::MINIMUM_DISTANCE) {
                return $candidate;
            }

            if ($distance > $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param list<int> $used
     */
    private function distanceFrom(int $candidate, array $used): int
    {
        if ($used === []) {
            return 360;
        }

        return min(array_map(
            static function (int $hue) use ($candidate): int {
                $delta = abs($candidate - $hue) % 360;

                return (int) min($delta, 360 - $delta);
            },
            $used,
        ));
    }
}
