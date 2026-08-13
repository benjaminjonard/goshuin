<?php

declare(strict_types=1);

namespace App\Tests\App\LiveComponent;

use App\Enum\LocationType;
use App\Repository\LocationRepository;
use App\Service\Geocoder;
use App\Service\LocationTypeGuesser;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use App\Twig\Components\LocationCombobox;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationComboboxTest extends KernelTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use ResetDatabase;

    public function test_it_offers_nothing_before_anything_is_typed(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $rendered = $this->combobox()->render()->toString();

        $this->assertStringNotContainsString('清水寺', $rendered);
        $this->assertStringContainsString('role="combobox"', $rendered);
    }

    public function test_typing_offers_the_matching_locations(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺', 'locality' => 'Kyōto']);
        LocationFactory::createOne(['romanizedName' => 'Byodo-in']);

        $rendered = $this->combobox()->set('term', 'kiyomizu')->render()->toString();

        $this->assertStringContainsString('Kiyomizu-dera', $rendered, 'The principal name is missing.');
        $this->assertStringContainsString('清水寺', $rendered, 'The Japanese name is missing.');
        $this->assertStringContainsString('Kyōto', $rendered);
        $this->assertStringNotContainsString('Byodo-in', $rendered);
    }

    public function test_it_searches_on_either_name(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha', 'japaneseName' => '伏見稲荷大社']);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);
        $component = $this->combobox();

        $this->assertStringContainsString('Fushimi Inari-taisha', $component->set('term', '伏見')->render()->toString(), 'The Japanese name was not searched.');
        $this->assertStringContainsString('Kiyomizu-dera', $component->set('term', 'KIYOMIZU')->render()->toString(), 'The search was case-sensitive.');
    }

    public function test_it_says_so_only_when_it_found_nothing(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);
        $component = $this->combobox();

        $found = $component->set('term', 'kiyomizu')->render()->toString();
        $this->assertStringNotContainsString('No result', $found, 'It claimed nothing was found while listing a result.');

        $nothing = $component->set('term', 'zzz nowhere')->render()->toString();
        $this->assertStringContainsString('No result', $nothing, 'An empty search said nothing at all.');
    }

    public function test_a_term_that_matches_nothing_offers_to_create_it(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $this->assertSame('伏見稲荷大社', $this->combobox()->set('term', '伏見稲荷大社')->component()->getCreatable());
    }

    public function test_an_exact_match_is_not_offered_for_creation(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $this->assertNull($this->combobox()->set('term', 'Kiyomizu-dera')->component()->getCreatable(), 'An existing location was offered for creation.');
    }

    public function test_choosing_a_location_puts_its_id_in_the_form_field(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);
        $id = $location->getId();

        $component = $this->combobox()
            ->set('term', '清水')
            ->call('choose', ['location' => $id])
        ;

        $this->assertSame($id, $component->component()->selected);
        $this->assertSame('', $component->component()->term, 'The typed term survived the choice.');

        $rendered = $component->render()->toString();
        $this->assertStringContainsString('name="goshuincho[boughtAt]"', $rendered);
        $this->assertStringContainsString($id, $rendered);
    }

    public function test_creating_starts_from_what_was_typed_with_the_type_inferred(): void
    {
        $component = $this->combobox()->set('term', '伏見稲荷大社')->call('startCreating');

        $this->assertTrue($component->component()->creating);
        $this->assertSame('伏見稲荷大社', $component->component()->newRomanizedName);
        $this->assertSame('shrine', $component->component()->newType, 'The suffix was not read.');
        $this->assertStringContainsString('<option value="shrine" selected>', $component->render()->toString(), 'The inference was applied without being shown in the field.');
    }

    public function test_a_name_with_no_recognised_suffix_infers_nothing(): void
    {
        $component = $this->combobox()->set('term', 'Some Place')->call('startCreating');

        $this->assertNull($component->component()->newType, 'A type was invented.');
        $this->assertStringNotContainsString('selected>', $component->render()->toString(), 'A type was preselected for a name with no recognised suffix.');
    }

    public function test_creating_stores_the_location_and_selects_it(): void
    {
        $component = $this->combobox()
            ->set('term', '清水寺')
            ->call('startCreating')
            ->set('newRomanizedName', 'Kiyomizu-dera')
            ->set('newJapaneseName', '清水寺')
            ->set('newLocality', 'Kyōto')
            ->set('newLatitude', '34.9949')
            ->set('newLongitude', '135.7850')
            ->call('create')
        ;

        $created = $this->locations()->findOneBy(['romanizedName' => 'Kiyomizu-dera']);
        $this->assertNotNull($created, 'Nothing was created.');
        $this->assertSame(LocationType::Temple, $created->getType());
        $this->assertSame('清水寺', $created->getJapaneseName());
        $this->assertSame('Kyōto', $created->getLocality());
        $this->assertSame(34.9949, $created->getLatitude());
        $this->assertSame($created->getId(), $component->component()->selected, 'The new location was not selected.');
        $this->assertFalse($component->component()->creating, 'The creation panel stayed open.');
    }

    public function test_choosing_a_found_place_fills_the_fields_including_the_address(): void
    {
        $component = $this->combobox()
            ->set('term', 'Kiyomizu')
            ->call('startCreating')
            ->call('usePlace', [
                'placeName' => 'Kiyomizu-dera',
                'japaneseName' => '清水寺',
                'locality' => 'Kyoto',
                'prefecture' => 'Kyōto',
                'address' => 'Kiyomizu Slope, Kyoto, Japan',
                'latitude' => '34.9943',
                'longitude' => '135.7844',
            ])
            ->call('create')
        ;

        $created = $this->locations()->findOneBy(['romanizedName' => 'Kiyomizu-dera']);
        $this->assertNotNull($created);
        $this->assertSame('清水寺', $created->getJapaneseName());
        $this->assertSame('Kyoto', $created->getLocality());
        $this->assertSame('Kiyomizu Slope, Kyoto, Japan', $created->getAddress(), 'The address from the geocoder was not stored.');
        $this->assertSame('Kyōto', $created->getPrefecture(), 'The prefecture from the geocoder was not stored.');
        $this->assertSame(34.9943, $created->getLatitude());
        $this->assertSame(LocationType::Temple, $created->getType(), 'The suffix was not read from the filled name.');
        $this->assertNotNull($component->component()->selected);
    }

    public function test_creating_without_a_name_stores_nothing(): void
    {
        $component = $this->combobox()->set('term', '  ')->call('startCreating');

        try {
            $component->call('create');
        } catch (\Throwable) {
        }

        $this->assertCount(0, $this->locations()->findAll(), 'An unnamed location was stored.');
    }

    public function test_cancelling_creates_nothing_and_keeps_the_field_empty(): void
    {
        $component = $this->combobox()
            ->set('term', '清水寺')
            ->call('startCreating')
            ->call('cancelCreating')
        ;

        $this->assertCount(0, $this->locations()->findAll(), 'Cancelling still created a location.');
        $this->assertFalse($component->component()->creating);
        $this->assertNull($component->component()->selected);
        $this->assertSame('', $component->component()->newRomanizedName, 'The abandoned name was kept.');
    }

    public function test_a_probable_duplicate_is_warned_about_without_blocking(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $component = $this->combobox()->set('term', 'Kiyomizu-dera')->call('startCreating');

        $this->assertStringContainsString('already exists', $component->render()->toString(), 'No duplicate warning was shown.');

        $component = $component->call('create');

        $this->assertCount(2, $this->locations()->namedExactly('Kiyomizu-dera'), 'The duplicate was blocked.');
        $this->assertNotNull($component->component()->selected);
    }

    public function test_a_coordinate_that_is_not_a_number_is_left_unset(): void
    {
        $this->combobox()
            ->set('term', 'Unplaced')
            ->call('startCreating')
            ->set('newLatitude', 'nowhere')
            ->set('newLongitude', '135.7850')
            ->call('create')
        ;

        $created = $this->locations()->findOneBy(['romanizedName' => 'Unplaced']);
        $this->assertFalse($created->hasCoordinates(), 'A non-numeric coordinate was stored.');
    }

    public function test_clearing_releases_the_choice(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $component = $this->combobox(['selected' => $location->getId()])->call('clear');

        $this->assertNull($component->component()->selected);
        $this->assertStringContainsString('role="combobox"', $component->render()->toString(), 'Clearing did not bring the search back.');
    }

    public function test_nothing_of_the_address_search_is_rendered_without_a_geocoder(): void
    {
        $rendered = $this->combobox()->set('term', 'Nowhere')->call('startCreating')->render()->toString();

        $this->assertStringNotContainsString('Find the place', $rendered, 'The address search appeared without a geocoder.');
        $this->assertStringNotContainsString('data-loading', $rendered, 'A loader was rendered without a geocoder configured.');
        $this->assertStringContainsString('Romanized name', $rendered, 'The manual fields disappeared with it.');
    }

    public function test_three_characters_open_the_menu_and_two_do_not(): void
    {
        $short = $this->geocoding([]);
        $short->address = 'ky';
        $this->assertFalse($short->isSearchable(), 'A two-letter query opened the menu.');

        $long = $this->geocoding([$this->answer([]), $this->answer([])]);
        $long->address = 'kyo';
        $this->assertTrue($long->isSearchable());
    }

    public function test_a_search_that_finds_nothing_is_not_a_failure(): void
    {
        $component = $this->geocoding([$this->answer([]), $this->answer([])]);
        $component->address = 'kiyomizu';

        $this->assertSame([], $component->getPlaces());
        $this->assertFalse($component->hasFailed(), 'An empty answer was reported as a failure.');
    }

    public function test_a_failing_search_is_reported_as_a_failure_and_not_as_emptiness(): void
    {
        $component = $this->geocoding([
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

        $component = $this->geocoding([], $client);
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

        $searched = $this->geocoding([], $client);
        $searched->address = 'kiyomizu';
        $searched->getPlaces();
        $this->assertSame(2, $asked);

        $next = $this->geocoding([], $client);
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

        $component = $this->geocoding([], $client);
        $component->address = 'kiyomizu';
        $component->getPlaces();

        $component->address = 'fushimi';
        $component->getPlaces();

        $this->assertSame(4, $asked, 'A new address was not searched.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function combobox(array $data = []): TestLiveComponent
    {
        return $this->createLiveComponent(LocationCombobox::class, ['name' => 'goshuincho[boughtAt]'] + $data)
            ->actingAs(UserFactory::createOne())
        ;
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function geocoding(array $responses, ?MockHttpClient $client = null): LocationCombobox
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

    private function locations(): LocationRepository
    {
        static::getContainer()->get('doctrine')->getManager()->clear();

        return static::getContainer()->get(LocationRepository::class);
    }
}
