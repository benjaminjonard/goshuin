<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goshuin;

final readonly class Trip
{
    /**
     * @param iterable<Goshuin> $goshuins
     *
     * @return list<Leg>
     */
    public function legs(iterable $goshuins): array
    {
        $legs = [];
        $previous = null;

        foreach ($goshuins as $goshuin) {
            $legs[] = new Leg($goshuin, ...$this->interval($previous, $goshuin->getReceivedOn()));
            $previous = $goshuin->getReceivedOn() ?? $previous;
        }

        return $legs;
    }

    /**
     * @return array{0: ?int, 1: bool}
     */
    private function interval(?\DateTimeImmutable $previous, ?\DateTimeImmutable $day): array
    {
        if ($previous === null || $day === null) {
            return [null, false];
        }

        $days = (int) $previous->diff($day)->days;

        return [$days > 1 ? $days : null, $days === 0];
    }
}
