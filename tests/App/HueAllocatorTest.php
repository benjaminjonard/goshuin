<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Service\HueAllocator;
use PHPUnit\Framework\TestCase;

class HueAllocatorTest extends TestCase
{
    public function test_the_first_hue_is_deterministic(): void
    {
        $this->assertSame(0, $this->allocator()->next([]));
    }

    public function test_it_stays_inside_the_circle(): void
    {
        $used = [];

        for ($i = 0; $i < 40; ++$i) {
            $hue = $this->allocator()->next($used);
            $this->assertGreaterThanOrEqual(0, $hue);
            $this->assertLessThanOrEqual(360, $hue);
            $used[] = $hue;
        }
    }

    public function test_consecutive_hues_are_visibly_apart(): void
    {
        $used = [];

        for ($i = 0; $i < 12; ++$i) {
            $used[] = $this->allocator()->next($used);
        }

        $this->assertSame($used, array_unique($used), 'The same hue was handed out twice.');

        foreach ($used as $index => $hue) {
            foreach (array_slice($used, $index + 1) as $other) {
                $this->assertGreaterThanOrEqual(24, $this->distance($hue, $other), sprintf('%d and %d are too close.', $hue, $other));
            }
        }
    }

    public function test_it_avoids_what_is_already_taken(): void
    {
        $hue = $this->allocator()->next([0, 10, 350]);

        $this->assertGreaterThanOrEqual(24, $this->distance($hue, 0));
        $this->assertGreaterThanOrEqual(24, $this->distance($hue, 10));
        $this->assertGreaterThanOrEqual(24, $this->distance($hue, 350));
    }

    public function test_it_reuses_a_freed_slot(): void
    {
        $used = [];

        for ($i = 0; $i < 5; ++$i) {
            $used[] = $this->allocator()->next($used);
        }

        $freed = $used[2];
        unset($used[2]);

        $this->assertSame($freed, $this->allocator()->next(array_values($used)), 'A deleted book left its hue unusable.');
    }

    public function test_it_still_answers_when_the_circle_is_crowded(): void
    {
        $used = range(0, 359);

        $hue = $this->allocator()->next($used);

        $this->assertGreaterThanOrEqual(0, $hue);
        $this->assertLessThanOrEqual(360, $hue);
    }

    private function allocator(): HueAllocator
    {
        return new HueAllocator();
    }

    private function distance(int $a, int $b): int
    {
        $delta = abs($a - $b) % 360;

        return (int) min($delta, 360 - $delta);
    }
}
