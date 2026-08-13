<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Service\RegionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegionResolverTest extends TestCase
{
    private RegionResolver $resolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->resolver = new RegionResolver();
    }

    public function test_it_names_the_region_the_box_sits_in(): void
    {
        $region = $this->resolver->resolve(34.9671, 34.9949, 135.7727, 135.7850);

        $this->assertNotNull($region);
        $this->assertSame('関西', $region->japanese);
        $this->assertSame('Kansai', $region->romanized);
    }

    #[DataProvider('regions')]
    public function test_it_reads_the_centre_of_the_box_and_not_a_corner(float $latitude, float $longitude, string $expected): void
    {
        $region = $this->resolver->resolve($latitude, $latitude, $longitude, $longitude);

        $this->assertNotNull($region, sprintf('%f, %f was placed nowhere.', $latitude, $longitude));
        $this->assertSame($expected, $region->romanized);
    }

    /**
     * @return list<array{float, float, string}>
     */
    public static function regions(): array
    {
        return [
            [26.2, 127.7, 'Okinawa'],
            [33.6, 130.4, 'Kyūshū'],
            [33.8, 132.8, 'Shikoku'],
            [34.9, 132.5, 'Chūgoku'],
            [34.7, 135.5, 'Kansai'],
            [35.2, 137.0, 'Chūbu'],
            [35.7, 139.7, 'Kantō'],
            [38.3, 140.9, 'Tōhoku'],
            [43.1, 141.4, 'Hokkaidō'],
        ];
    }

    public function test_a_box_spanning_two_regions_is_named_by_its_middle(): void
    {
        $region = $this->resolver->resolve(34.7, 35.7, 135.5, 139.7);

        $this->assertNotNull($region);
        $this->assertSame('Chūbu', $region->romanized, 'The box was named after one of its ends.');
    }

    public function test_a_place_outside_every_region_is_named_by_none(): void
    {
        $this->assertNull($this->resolver->resolve(10.0, 10.0, 100.0, 100.0), 'A place outside Japan was given a region.');
    }

    #[DataProvider('incompleteBoxes')]
    public function test_an_incomplete_box_has_neither_region_nor_spread(?float $south, ?float $north, ?float $west, ?float $east): void
    {
        $this->assertNull($this->resolver->resolve($south, $north, $west, $east));
        $this->assertNull($this->resolver->spread($south, $north, $west, $east));
    }

    /**
     * @return list<array{?float, ?float, ?float, ?float}>
     */
    public static function incompleteBoxes(): array
    {
        return [
            [null, 34.9, 135.7, 135.8],
            [34.9, null, 135.7, 135.8],
            [34.9, 34.9, null, 135.8],
            [34.9, 34.9, 135.7, null],
            [null, null, null, null],
        ];
    }

    public function test_a_single_point_spreads_over_nothing(): void
    {
        $this->assertSame(0, $this->resolver->spread(34.9671, 34.9671, 135.7727, 135.7727));
    }

    public function test_the_spread_grows_with_the_box(): void
    {
        $city = $this->resolver->spread(34.9671, 34.9949, 135.7727, 135.7850);
        $country = $this->resolver->spread(33.6, 43.1, 130.4, 141.4);

        $this->assertNotNull($city);
        $this->assertNotNull($country);
        $this->assertGreaterThan(0, $city);
        $this->assertLessThan(10, $city, 'Two points a few streets apart were called a journey.');
        $this->assertGreaterThan($city, $country);
        $this->assertGreaterThan(1000, $country, 'Kyūshū to Hokkaidō came out shorter than a thousand kilometres.');
    }

    public function test_the_spread_is_a_distance_and_not_a_direction(): void
    {
        $this->assertSame(
            $this->resolver->spread(34.0, 36.0, 135.0, 137.0),
            $this->resolver->spread(34.0, 36.0, 135.0, 137.0),
        );
    }
}
