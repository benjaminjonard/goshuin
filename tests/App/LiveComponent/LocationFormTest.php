<?php

declare(strict_types=1);

namespace App\Tests\App\LiveComponent;

use App\Enum\LocationType as Kind;
use App\Repository\LocationRepository;
use App\Service\Geocoder;
use App\Service\LocationTypeGuesser;
use App\Service\PrefectureNamer;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use App\Twig\Components\LocationForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationFormTest extends KernelTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use ResetDatabase;

    public function test_creating_offers_every_field_but_the_photograph(): void
    {
        $rendered = $this->form()->render()->toString();

        foreach (['romanizedName', 'japaneseName', 'type', 'locality', 'prefecture', 'address', 'latitude', 'longitude', 'notes'] as $field) {
            $this->assertStringContainsString('location['.$field.']', $rendered, $field.' is missing from the creation form.');
        }

        $this->assertStringNotContainsString('location[photographFile]', $rendered, 'The creation form offered a photograph.');
        $this->assertStringNotContainsString('location[removePhotograph]', $rendered);
    }

    public function test_editing_offers_the_photograph_too(): void
    {
        $rendered = $this->form(['location' => LocationFactory::createOne()])->render()->toString();

        $this->assertStringContainsString('location[photographFile]', $rendered, 'The edit form dropped the photograph.');
        $this->assertStringContainsString('location[removePhotograph]', $rendered);
    }

    public function test_the_creation_panel_offers_no_gallery(): void
    {
        $this->assertStringNotContainsString('photo_add', $this->form()->render()->toString(), 'The creation panel offered a gallery.');
    }

    public function test_editing_offers_the_gallery(): void
    {
        $this->assertStringContainsString('photo_add[place][]', $this->form(['location' => LocationFactory::createOne()])->render()->toString(), 'The edit form has no gallery.');
    }

    public function test_both_paths_offer_rigorously_the_same_fields_apart_from_the_photograph(): void
    {
        $user = UserFactory::createOne();
        $location = LocationFactory::createOne();

        $creating = $this->fields($this->createLiveComponent(LocationForm::class)->actingAs($user)->render()->toString());
        $editing = $this->fields($this->createLiveComponent(LocationForm::class, ['location' => $location])->actingAs($user)->render()->toString());

        $this->assertSame(
            $creating,
            array_values(array_diff($editing, ['photographFile', 'removePhotograph'])),
            'The two paths do not describe a location the same way.',
        );
    }

    public function test_editing_starts_from_what_is_stored(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺', 'locality' => 'Kyōto']);

        $rendered = $this->form(['location' => $location])->render()->toString();

        $this->assertStringContainsString('Kiyomizu-dera', $rendered);
        $this->assertStringContainsString('清水寺', $rendered, 'The stored Japanese name was not shown.');
        $this->assertStringContainsString('Kyōto', $rendered);
    }

    public function test_choosing_a_place_fills_the_fields_and_infers_the_type(): void
    {
        $component = $this->form()->call('usePlace', [
            'placeName' => 'Kiyomizu-dera',
            'japaneseName' => '清水寺',
            'locality' => 'Kyoto',
            'prefecture' => 'Kyoto',
            'address' => 'Kiyomizu Slope, Kyoto, Japan',
            'latitude' => '34.9943',
            'longitude' => '135.7844',
        ]);

        $values = $component->component()->formValues;

        $this->assertSame('Kiyomizu-dera', $values['romanizedName']);
        $this->assertSame('清水寺', $values['japaneseName']);
        $this->assertSame('Kyoto', $values['locality']);
        $this->assertSame('Kyoto', $values['prefecture']);
        $this->assertSame('Kiyomizu Slope, Kyoto, Japan', $values['address']);
        $this->assertSame('34.994300', $values['latitude'], 'The coordinate lost the scale the field declares.');
        $this->assertSame('135.784400', $values['longitude']);
        $this->assertSame('temple', $values['type'], 'The suffix was not read from the filled name.');
    }

    public function test_a_chosen_place_overwrites_what_was_already_there(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Typed by hand', 'locality' => 'Somewhere']);

        $values = $this->form(['location' => $location])->call('usePlace', [
            'placeName' => 'Kiyomizu-dera',
            'japaneseName' => '',
            'locality' => 'Kyoto',
            'prefecture' => 'Kyoto',
            'address' => '',
            'latitude' => '34.9943',
            'longitude' => '135.7844',
        ])->component()->formValues;

        $this->assertSame('Kiyomizu-dera', $values['romanizedName'], 'A hand-typed name survived a chosen result.');
        $this->assertSame('Kyoto', $values['locality']);
        $this->assertSame('', $values['japaneseName'], 'A value the geocoder does not know was left behind.');
    }

    public function test_a_place_whose_name_infers_no_type_leaves_it_unrecorded(): void
    {
        $values = $this->form()->call('usePlace', [
            'placeName' => 'Some Place',
            'japaneseName' => '',
            'locality' => '',
            'prefecture' => '',
            'address' => '',
            'latitude' => '',
            'longitude' => '',
        ])->component()->formValues;

        $this->assertSame('', $values['type'], 'A type was invented.');
    }

    public function test_creating_stores_the_location_and_reports_it(): void
    {
        $component = $this->form()
            ->set('location', [
                'romanizedName' => 'Kiyomizu-dera',
                'japaneseName' => '清水寺',
                'type' => 'temple',
                'locality' => 'Kyōto',
                'prefecture' => 'Kyōto',
                'address' => '',
                'latitude' => '34.9949',
                'longitude' => '135.7850',
                'notes' => '',
            ])
            ->call('create')
        ;

        $created = $this->locations()->findOneBy(['romanizedName' => 'Kiyomizu-dera']);

        $this->assertNotNull($created, 'Nothing was created.');
        $this->assertSame(Kind::Temple, $created->getType());
        $this->assertSame('清水寺', $created->getJapaneseName());
        $this->assertSame(34.9949, $created->getLatitude());
        $this->assertNotNull($component);
    }

    public function test_creating_without_a_name_stores_nothing(): void
    {
        $component = $this->form()->set('location', ['romanizedName' => '']);

        try {
            $component->call('create');
        } catch (\Throwable) {
        }

        $this->assertCount(0, $this->locations()->findAll(), 'An unnamed location was stored.');
    }

    public function test_nothing_of_the_address_search_is_rendered_without_a_geocoder(): void
    {
        $rendered = $this->form()->render()->toString();

        $this->assertStringNotContainsString('Find the place', $rendered, 'The address search appeared without a geocoder.');
        $this->assertStringContainsString('location[romanizedName]', $rendered, 'The manual fields disappeared with it.');
    }

    public function test_a_probable_duplicate_is_warned_about_without_blocking(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $component = $this->form()->set('location', ['romanizedName' => 'Kiyomizu-dera']);

        $this->assertCount(1, $component->component()->getDuplicates(), 'The existing location was not noticed.');

        $component->call('create');

        $this->assertCount(2, $this->locations()->findAll(), 'A probable duplicate was blocked.');
    }

    public function test_an_existing_location_is_never_saved_by_the_creation_action(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $component = $this->form(['location' => $location])->set('location', ['romanizedName' => 'Renamed behind the form']);

        try {
            $component->call('create');
        } catch (\Throwable) {
        }

        $this->assertSame(
            'Kiyomizu-dera',
            static::getContainer()->get('doctrine')->getConnection()->fetchOne(
                'SELECT romanized_name FROM gos_location WHERE id = :id',
                ['id' => $location->getId()],
            ),
            'The creation action wrote an existing location away outside its own form.',
        );
        $this->assertCount(1, $this->locations()->findAll(), 'A duplicate was created from the edit form.');
    }

    public function test_a_coordinate_that_is_not_a_number_is_refused(): void
    {
        $component = $this->form()->set('location', ['romanizedName' => 'Unplaced', 'latitude' => 'nowhere', 'longitude' => '135.7850']);

        try {
            $component->call('create');
        } catch (\Throwable) {
        }

        $this->assertCount(0, $this->locations()->findAll(), 'A location with a coordinate that is not a number was stored.');
    }

    public function test_a_location_with_no_coordinates_at_all_is_valid(): void
    {
        $this->form()->set('location', ['romanizedName' => 'Unplaced', 'latitude' => '', 'longitude' => ''])->call('create');

        $created = $this->locations()->findOneBy(['romanizedName' => 'Unplaced']);

        $this->assertNotNull($created, 'A location without coordinates was refused.');
        $this->assertFalse($created->hasCoordinates());
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
        $next->formValues = ['romanizedName' => 'typing something else'];
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
     * @param list<MockResponse> $responses
     */
    private function geocoding(array $responses, ?MockHttpClient $client = null): LocationForm
    {
        $container = static::getContainer();

        return new LocationForm(
            $container->get(FormFactoryInterface::class),
            $container->get(LocationRepository::class),
            new LocationTypeGuesser(),
            new Geocoder($client ?? new MockHttpClient($responses), 'https://photon.example', new PrefectureNamer()),
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

    /**
     * @return list<string>
     */
    private function fields(string $rendered): array
    {
        preg_match_all('/name="location\[([a-zA-Z]+)\]"/', $rendered, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function form(array $data = []): TestLiveComponent
    {
        return $this->createLiveComponent(LocationForm::class, $data)->actingAs(UserFactory::createOne());
    }

    private function locations(): LocationRepository
    {
        return static::getContainer()->get(LocationRepository::class);
    }
}
