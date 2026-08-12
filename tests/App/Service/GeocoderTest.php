<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Service\Geocoder;
use App\Service\GeocoderFailed;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeocoderTest extends TestCase
{
    public function test_it_is_unavailable_without_a_host(): void
    {
        $geocoder = new Geocoder(new MockHttpClient(), '');

        $this->assertFalse($geocoder->isAvailable());
        $this->assertSame([], $geocoder->search('kiyomizu'), 'A search was attempted with no host configured.');
    }

    public function test_no_request_leaves_when_no_host_is_configured(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \LogicException('A request left the instance.');
        });

        $this->assertSame([], (new Geocoder($client, ''))->search('kiyomizu'));
    }

    public function test_an_empty_query_asks_nothing(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \LogicException('A request left the instance.');
        });

        $this->assertSame([], (new Geocoder($client, 'https://photon.example', 0.0))->search('   '));
    }

    public function test_a_query_too_short_asks_nothing(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \LogicException('A request left the instance for a two-letter query.');
        });

        $this->assertSame([], (new Geocoder($client, 'https://photon.example', 0.0))->search('ky'));
    }

    public function test_both_languages_are_asked_before_either_is_read(): void
    {
        $order = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$order): MockResponse {
            $order[] = 'asked';

            return new MockResponse(json_encode(['features' => []], \JSON_THROW_ON_ERROR), [
                'response_headers' => ['content-type' => 'application/json'],
            ]);
        });

        (new Geocoder($client, 'https://photon.example', 0.0))->search('kiyomizu');

        $this->assertSame(['asked', 'asked'], $order, 'The two requests were not both started.');
    }

    public function test_photon_is_given_twenty_seconds(): void
    {
        $seen = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen ??= $options;

            return new MockResponse(json_encode(['features' => []], \JSON_THROW_ON_ERROR));
        });

        (new Geocoder($client, 'https://photon.example', 0.0))->search('kiyomizu');

        $this->assertSame(20.0, $seen['timeout']);
    }

    public function test_it_pairs_the_romanised_and_local_names(): void
    {
        $geocoder = new Geocoder($this->clientReturning(
            $this->collection([['W', 336641107, 'Kiyomizu-dera', 'Kyoto', 135.7844, 34.9943]]),
            $this->collection([['W', 336641107, '清水寺', '京都市', 135.7844, 34.9943]]),
        ), 'https://photon.example', 0.0);

        $places = $geocoder->search('kiyomizu');

        $this->assertCount(1, $places);
        $this->assertSame('Kiyomizu-dera', $places[0]['name']);
        $this->assertSame('清水寺', $places[0]['japaneseName'], 'The local name was not paired with the romanised one.');
        $this->assertSame('Kyoto', $places[0]['locality']);
        $this->assertSame('Kiyomizu Slope, Kyoto, Kyoto Prefecture, 605-0862, Japan', $places[0]['address'], 'The address was not composed from what Photon returned.');
        $this->assertSame(34.9943, $places[0]['latitude']);
        $this->assertSame(135.7844, $places[0]['longitude']);
    }

    public function test_a_place_missing_from_the_local_answer_keeps_its_romanised_name(): void
    {
        $geocoder = new Geocoder($this->clientReturning(
            $this->collection([['N', 42, 'Somewhere', 'Nara', 135.0, 34.0]]),
            $this->collection([]),
        ), 'https://photon.example', 0.0);

        $places = $geocoder->search('somewhere');

        $this->assertCount(1, $places);
        $this->assertSame('', $places[0]['japaneseName'], 'A missing local name became something else.');
    }

    public function test_an_address_leaves_out_what_photon_did_not_return(): void
    {
        $body = json_encode(['features' => [[
            'properties' => ['osm_type' => 'W', 'osm_id' => 7, 'name' => 'Bare', 'city' => 'Nara'],
            'geometry' => ['coordinates' => [135.0, 34.0]],
        ]]], \JSON_THROW_ON_ERROR);

        $geocoder = new Geocoder($this->clientReturning(new MockResponse($body), new MockResponse($body)), 'https://photon.example', 0.0);

        $this->assertSame('Nara', $geocoder->search('bare')[0]['address'], 'Empty parts left separators behind.');
    }

    public function test_a_feature_without_a_name_or_coordinates_is_dropped(): void
    {
        $body = json_encode(['features' => [
            ['properties' => ['osm_type' => 'W', 'osm_id' => 1], 'geometry' => ['coordinates' => [135.0, 34.0]]],
            ['properties' => ['osm_type' => 'W', 'osm_id' => 2, 'name' => 'No geometry'], 'geometry' => null],
        ]], \JSON_THROW_ON_ERROR);

        $geocoder = new Geocoder($this->clientReturning(new MockResponse($body), new MockResponse($body)), 'https://photon.example', 0.0);

        $this->assertSame([], $geocoder->search('broken'));
    }

    public function test_a_failing_geocoder_says_so(): void
    {
        $geocoder = new Geocoder(new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 503]),
        ]), 'https://photon.example', 0.0);

        $this->expectException(GeocoderFailed::class);

        $geocoder->search('kiyomizu');
    }

    public function test_only_the_local_answer_failing_is_not_a_failure(): void
    {
        $geocoder = new Geocoder(new MockHttpClient([
            $this->collection([['W', 1, 'Kiyomizu-dera', 'Kyoto', 135.7844, 34.9943]]),
            new MockResponse('', ['http_code' => 503]),
        ]), 'https://photon.example', 0.0);

        $places = $geocoder->search('kiyomizu');

        $this->assertCount(1, $places, 'A failing local answer lost the romanised results.');
        $this->assertSame('', $places[0]['japaneseName']);
    }

    public function test_it_asks_the_configured_host_in_both_languages(): void
    {
        $asked = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$asked): MockResponse {
            $asked[] = $url;

            return $this->collection([]);
        });

        (new Geocoder($client, 'https://photon.example/', 0.0))->search('kiyomizu');

        $this->assertCount(2, $asked);
        $this->assertStringStartsWith('https://photon.example/api/', $asked[0], 'The configured host was not used.');
        $this->assertStringContainsString('lang=en', $asked[0]);
        $this->assertStringContainsString('lang=default', $asked[1]);
    }

    public function test_an_answer_is_not_handed_back_before_the_pace_has_elapsed(): void
    {
        $geocoder = new Geocoder($this->clientReturning($this->collection([]), $this->collection([])), 'https://photon.example', 0.4);

        $started = microtime(true);
        $geocoder->search('kiyomizu');

        $this->assertGreaterThanOrEqual(0.4, microtime(true) - $started, 'Photon answered and the results were handed straight back.');
    }

    public function test_a_failure_is_paced_too(): void
    {
        $geocoder = new Geocoder(new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 503]),
        ]), 'https://photon.example', 0.4);

        $started = microtime(true);

        try {
            $geocoder->search('kiyomizu');
        } catch (GeocoderFailed) {
        }

        $this->assertGreaterThanOrEqual(0.4, microtime(true) - $started, 'A failure came back faster than the pace.');
    }

    /**
     * @param list<array{0: string, 1: int, 2: string, 3: string, 4: float, 5: float}> $places
     */
    private function collection(array $places): MockResponse
    {
        $features = array_map(static fn (array $place): array => [
            'properties' => [
                'osm_type' => $place[0],
                'osm_id' => $place[1],
                'name' => $place[2],
                'city' => $place[3],
                'street' => 'Kiyomizu Slope',
                'state' => 'Kyoto Prefecture',
                'postcode' => '605-0862',
                'country' => 'Japan',
            ],
            'geometry' => ['coordinates' => [$place[4], $place[5]]],
        ], $places);

        return new MockResponse(json_encode(['features' => $features], \JSON_THROW_ON_ERROR));
    }

    private function clientReturning(MockResponse ...$responses): HttpClientInterface
    {
        return new MockHttpClient($responses);
    }
}
