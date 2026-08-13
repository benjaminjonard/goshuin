<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Service\Summary;
use PHPUnit\Framework\TestCase;

class SummaryTest extends TestCase
{
    public function test_one_day_counts_as_one_day(): void
    {
        $this->assertSame(1, $this->between('2025-03-15', '2025-03-15'));
    }

    public function test_the_span_counts_both_ends(): void
    {
        $this->assertSame(3, $this->between('2025-03-14', '2025-03-16'));
        $this->assertSame(32, $this->between('2025-03-01', '2025-04-01'));
    }

    public function test_a_span_missing_one_end_is_not_counted(): void
    {
        $this->assertNull($this->between(null, '2025-03-16'), 'A span was counted without a beginning.');
        $this->assertNull($this->between('2025-03-14', null), 'A span was counted without an end.');
        $this->assertNull($this->between(null, null));
    }

    private function between(?string $first, ?string $last): ?int
    {
        return new Summary(
            goshuin: 0,
            locations: 0,
            spend: [],
            cities: [],
            prefectures: [],
            first: $first === null ? null : new \DateTimeImmutable($first),
            last: $last === null ? null : new \DateTimeImmutable($last),
            region: null,
            spread: null,
        )->days();
    }
}
