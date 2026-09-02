<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Service\Municipality;
use PHPUnit\Framework\TestCase;

class MunicipalityTest extends TestCase
{
    private const float BORDER_LATITUDE = 35.89242;
    private const float BORDER_LONGITUDE = 139.74683;

    public function test_a_point_in_japan_is_named_by_the_unit_it_sits_in(): void
    {
        $this->assertSame('26100', $this->service()->at(34.994856, 135.784997));
        $this->assertSame('13106', $this->service()->at(35.714765, 139.796655));
        $this->assertSame('34213', $this->service()->at(34.295852, 132.319740));
        $this->assertSame('01100', $this->service()->at(43.054470, 141.307487));
    }

    public function test_a_point_outside_japan_is_named_by_nothing(): void
    {
        $this->assertNull($this->service()->at(48.852968, 2.349902));
    }

    public function test_a_point_two_simplified_polygons_both_claim_goes_to_the_first_in_file_order(): void
    {
        $order = $this->fileOrder();

        $this->assertArrayHasKey('11100', $order);
        $this->assertArrayHasKey('11222', $order);
        $this->assertLessThan($order['11222'], $order['11100'], 'The fixture no longer has 11100 ahead of 11222 in the file.');

        $this->assertSame('11100', $this->service()->at(self::BORDER_LATITUDE, self::BORDER_LONGITUDE));
    }

    public function test_a_point_on_a_boundary_answers_the_same_every_time(): void
    {
        $held = $this->service();
        $answers = [
            $held->at(self::BORDER_LATITUDE, self::BORDER_LONGITUDE),
            $held->at(self::BORDER_LATITUDE, self::BORDER_LONGITUDE),
            $this->service()->at(self::BORDER_LATITUDE, self::BORDER_LONGITUDE),
        ];

        $this->assertSame(['11100', '11100', '11100'], $answers);
    }

    public function test_a_missing_boundary_file_names_nothing_and_raises_nothing(): void
    {
        $this->assertNull(new Municipality(self::projectDir().'/var/no-such-place')->at(34.994856, 135.784997));
    }

    public function test_the_server_index_and_the_layer_it_is_drawn_from_carry_the_same_codes(): void
    {
        $index = array_map(
            static fn (array $unit): string => $unit['code'],
            json_decode((string) gzdecode((string) file_get_contents(self::projectDir().'/data/geo/municipalities.json.gz')), true),
        );

        $drawn = $this->layer('municipalities');

        sort($index);
        sort($drawn);

        $this->assertSame($drawn, $index, 'The index the server tests against and the layer the browser draws have drifted apart.');
    }

    public function test_every_municipality_sits_under_a_prefecture_the_other_layer_draws(): void
    {
        $prefectures = $this->layer('prefectures');

        $this->assertCount(47, $prefectures, 'The prefecture layer no longer draws all 47.');

        $parents = array_unique(array_map(
            static fn (string $code): string => substr($code, 0, 2),
            $this->layer('municipalities'),
        ));

        $this->assertSame([], array_diff($parents, $prefectures), 'A municipality sits under a prefecture the other layer does not draw.');
    }

    /**
     * @return list<string>
     */
    private function layer(string $name): array
    {
        $topology = json_decode((string) file_get_contents(self::projectDir().'/public/geo/'.$name.'.topo.json'), true);

        return array_map(
            static fn (array $geometry): string => $geometry['properties']['code'],
            $topology['objects'][$name]['geometries'],
        );
    }

    private function service(): Municipality
    {
        return new Municipality(self::projectDir());
    }

    /**
     * @return array<string, int>
     */
    private function fileOrder(): array
    {
        $units = json_decode((string) gzdecode((string) file_get_contents(self::projectDir().'/data/geo/municipalities.json.gz')), true);
        $order = [];

        foreach ($units as $rank => $unit) {
            $order[$unit['code']] = $rank;
        }

        return $order;
    }

    private static function projectDir(): string
    {
        return dirname(__DIR__, 3);
    }
}
