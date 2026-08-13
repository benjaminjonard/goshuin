<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Entity\Goshuin;
use App\Service\Day;
use App\Service\Trip;
use PHPUnit\Framework\TestCase;

class TripTest extends TestCase
{
    private Trip $trip;

    #[\Override]
    protected function setUp(): void
    {
        $this->trip = new Trip();
    }

    public function test_one_goshuin_a_day_makes_one_day_each(): void
    {
        $days = $this->trip->days([$this->on('2025-03-14'), $this->on('2025-03-15'), $this->on('2025-03-16')]);

        $this->assertCount(3, $days);
        $this->assertSame(['2025-03-14', '2025-03-15', '2025-03-16'], $this->dates($days));
    }

    public function test_the_goshuin_of_one_day_are_held_together(): void
    {
        $morning = $this->on('2025-03-14');
        $afternoon = $this->on('2025-03-14');

        $days = $this->trip->days([$morning, $afternoon, $this->on('2025-03-15')]);

        $this->assertCount(2, $days, 'Two goshuin of the same day were split across two days.');
        $this->assertSame([$morning, $afternoon], $days[0]->goshuins);
        $this->assertCount(1, $days[1]->goshuins);
    }

    public function test_nothing_comes_before_the_first_day(): void
    {
        $days = $this->trip->days([$this->on('2025-03-14')]);

        $this->assertNull($days[0]->after, 'A gap was measured before the first day.');
    }

    public function test_the_next_day_is_not_a_gap(): void
    {
        $days = $this->trip->days([$this->on('2025-03-14'), $this->on('2025-03-15')]);

        $this->assertNull($days[1]->after, 'One day apart was announced as a gap.');
    }

    public function test_a_real_gap_is_measured(): void
    {
        $days = $this->trip->days([$this->on('2025-03-14'), $this->on('2025-03-20')]);

        $this->assertSame(6, $days[1]->after);
    }

    public function test_a_day_with_no_date_is_a_day_of_its_own(): void
    {
        $undated = new Goshuin();

        $days = $this->trip->days([$this->on('2025-03-14'), $undated, $this->on('2025-03-20')]);

        $this->assertCount(3, $days);
        $this->assertNull($days[1]->date);
        $this->assertSame([$undated], $days[1]->goshuins);
        $this->assertNull($days[1]->after, 'A gap was measured up to a day with no date.');
    }

    public function test_an_undated_day_does_not_lose_the_date_before_it(): void
    {
        $days = $this->trip->days([$this->on('2025-03-14'), new Goshuin(), $this->on('2025-03-20')]);

        $this->assertSame(6, $days[2]->after, 'The undated day swallowed the date that came before it.');
    }

    public function test_undated_goshuin_side_by_side_stay_one_day(): void
    {
        $days = $this->trip->days([new Goshuin(), new Goshuin()]);

        $this->assertCount(1, $days, 'Two goshuin with no date were split apart.');
        $this->assertNull($days[0]->date);
        $this->assertCount(2, $days[0]->goshuins);
    }

    public function test_the_same_date_reached_twice_is_two_days(): void
    {
        $days = $this->trip->days([$this->on('2025-03-14'), $this->on('2025-03-15'), $this->on('2025-03-14')]);

        $this->assertCount(3, $days, 'A date that came back was folded into its first appearance.');
        $this->assertSame(['2025-03-14', '2025-03-15', '2025-03-14'], $this->dates($days));
    }

    public function test_a_collection_with_nothing_in_it_has_no_day(): void
    {
        $this->assertSame([], $this->trip->days([]));
    }

    /**
     * @param list<Day> $days
     *
     * @return list<?string>
     */
    private function dates(array $days): array
    {
        return array_map(static fn (Day $day): ?string => $day->date?->format('Y-m-d'), $days);
    }

    private function on(string $day): Goshuin
    {
        return new Goshuin()->setReceivedOn(new \DateTimeImmutable($day));
    }
}
