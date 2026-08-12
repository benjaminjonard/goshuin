<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Repository\LocationRepository;
use App\Service\Geocoder;
use App\Service\LocationTypeGuesser;
use App\Twig\Components\LocationCombobox;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationSearchStateTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_nothing_is_searched_below_three_characters(): void
    {
        $component = $this->combobox([]);
        $component->address = 'ky';

        $this->assertFalse($component->isSearchable(), 'A two-letter query opened the menu.');
    }

    public function test_three_characters_open_the_menu(): void
    {
        $component = $this->combobox([$this->answer([]), $this->answer([])]);
        $component->address = 'kyo';

        $this->assertTrue($component->isSearchable());
    }

    public function test_a_search_that_finds_nothing_is_not_a_failure(): void
    {
        $component = $this->combobox([$this->answer([]), $this->answer([])]);
        $component->address = 'kiyomizu';

        $this->assertSame([], $component->getPlaces());
        $this->assertFalse($component->hasFailed(), 'An empty answer was reported as a failure.');
    }

    public function test_a_search_that_finds_something_is_not_a_failure(): void
    {
        $component = $this->combobox([
            $this->answer([['W', 1, 'Kiyomizu-dera', 'Kyoto']]),
            $this->answer([['W', 1, '清水寺', '京都市']]),
        ]);
        $component->address = 'kiyomizu';

        $this->assertCount(1, $component->getPlaces());
        $this->assertFalse($component->hasFailed());
    }

    public function test_a_failing_search_is_reported_as_a_failure_and_not_as_emptiness(): void
    {
        $component = $this->combobox([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 503]),
        ]);
        $component->address = 'kiyomizu';

        $this->assertTrue($component->hasFailed(), 'A failing geocoder was not reported.');
        $this->assertSame([], $component->getPlaces());
    }

    public function test_an_unchanged_address_is_never_searched_twice(): void
    {
        $asked = 0;
        $client = new MockHttpClient(function () use (&$asked): MockResponse {
            ++$asked;

            return $this->answer([]);
        });

        $component = $this->combobox([], $client);
        $component->address = 'kiyomizu';

        $component->getPlaces();
        $component->getPlaces();
        $component->hasFailed();

        $this->assertSame(2, $asked, 'One address cost more than one pair of requests.');
    }

    public function test_a_render_for_another_reason_does_not_search_again(): void
    {
        $asked = 0;
        $client = new MockHttpClient(function () use (&$asked): MockResponse {
            ++$asked;

            return $this->answer([['W', 1, 'Kiyomizu-dera', 'Kyoto']]);
        });

        $searched = $this->combobox([], $client);
        $searched->address = 'kiyomizu';
        $searched->getPlaces();
        $this->assertSame(2, $asked);

        $next = $this->combobox([], $client);
        $next->address = $searched->address;
        $next->found = $searched->found;
        $next->foundFor = $searched->foundFor;
        $next->newRomanizedName = 'typing something else';
        $next->getPlaces();

        $this->assertSame(2, $asked, 'Typing in another field asked the geocoder again.');
        $this->assertCount(1, $next->getPlaces(), 'The remembered results were lost.');
    }

    public function test_a_changed_address_is_searched_again(): void
    {
        $asked = 0;
        $client = new MockHttpClient(function () use (&$asked): MockResponse {
            ++$asked;

            return $this->answer([]);
        });

        $component = $this->combobox([], $client);
        $component->address = 'kiyomizu';
        $component->getPlaces();

        $component->address = 'fushimi';
        $component->getPlaces();

        $this->assertSame(4, $asked, 'A new address was not searched.');
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function combobox(array $responses, ?MockHttpClient $client = null): LocationCombobox
    {
        $container = static::getContainer();

        return new LocationCombobox(
            $container->get(LocationRepository::class),
            new LocationTypeGuesser(),
            new Geocoder($client ?? new MockHttpClient($responses), 'https://photon.example'),
            $container->get(EntityManagerInterface::class),
        );
    }

    /**
     * @param list<array{0: string, 1: int, 2: string, 3: string}> $places
     */
    private function answer(array $places): MockResponse
    {
        $features = array_map(static fn (array $place): array => [
            'properties' => ['osm_type' => $place[0], 'osm_id' => $place[1], 'name' => $place[2], 'city' => $place[3]],
            'geometry' => ['coordinates' => [135.0, 34.0]],
        ], $places);

        return new MockResponse(json_encode(['features' => $features], \JSON_THROW_ON_ERROR));
    }
}
