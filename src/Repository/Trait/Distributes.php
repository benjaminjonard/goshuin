<?php

declare(strict_types=1);

namespace App\Repository\Trait;

trait Distributes
{
    /**
     * @param list<array{code: ?string, held: mixed}> $rows
     *
     * @return array{zones: array<string, int>, unlocated: int}
     */
    private function zonesOf(array $rows): array
    {
        $zones = [];
        $unlocated = 0;

        foreach ($rows as $row) {
            $held = (int) $row['held'];

            if ($row['code'] === null) {
                $unlocated += $held;

                continue;
            }

            $code = trim((string) $row['code']);
            $zones[$code] = ($zones[$code] ?? 0) + $held;
        }

        return ['zones' => $zones, 'unlocated' => $unlocated];
    }

    /**
     * @param list<array{day: mixed, held: mixed}> $rows
     *
     * @return array{years: array<int, int>, months: array<int, int>, weekdays: array<int, int>, undated: int}
     */
    private function spansOf(array $rows): array
    {
        $years = [];
        $months = [];
        $weekdays = [];
        $undated = 0;

        foreach ($rows as $row) {
            $held = (int) $row['held'];
            $day = $this->dayOf($row['day']);

            if (!$day instanceof \DateTimeImmutable) {
                $undated += $held;

                continue;
            }

            foreach ([[&$years, 'Y'], [&$months, 'n'], [&$weekdays, 'N']] as [&$bucket, $part]) {
                $key = (int) $day->format($part);
                $bucket[$key] = ($bucket[$key] ?? 0) + $held;
            }

            unset($bucket);
        }

        return ['years' => $years, 'months' => $months, 'weekdays' => $weekdays, 'undated' => $undated];
    }

    private function dayOf(mixed $day): ?\DateTimeImmutable
    {
        if ($day instanceof \DateTimeImmutable) {
            return $day;
        }

        return \is_string($day) && $day !== '' ? new \DateTimeImmutable($day) : null;
    }
}
