<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goshuin;
use App\Model\Day;

final readonly class Trip
{
    /**
     * @param iterable<Goshuin> $goshuins
     *
     * @return list<Day>
     */
    public function days(iterable $goshuins): array
    {
        $days = [];
        $held = [];
        $day = null;
        $previous = null;

        foreach ($goshuins as $goshuin) {
            $on = $goshuin->getReceivedOn();

            if ($held !== [] && $this->stamp($on) !== $this->stamp($day)) {
                $days[] = new Day($day, $held, $this->between($previous, $day));
                $previous = $day ?? $previous;
                $held = [];
            }

            $day = $on;
            $held[] = $goshuin;
        }

        if ($held !== []) {
            $days[] = new Day($day, $held, $this->between($previous, $day));
        }

        return $days;
    }

    private function stamp(?\DateTimeImmutable $day): ?string
    {
        return $day?->format('Y-m-d');
    }

    private function between(?\DateTimeImmutable $previous, ?\DateTimeImmutable $day): ?int
    {
        if ($previous === null || $day === null) {
            return null;
        }

        $days = (int) $previous->diff($day)->days;

        return $days > 1 ? $days : null;
    }
}
