<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Entity\Location;
use App\Enum\LocationType;
use App\Repository\LocationRepository;
use App\Service\Geocoder;
use App\Twig\Components\LocationCombobox;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationComboboxTest extends KernelTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use ResetDatabase;

    private function locations(): LocationRepository
    {
        static::getContainer()->get('doctrine')->getManager()->clear();

        return static::getContainer()->get(LocationRepository::class);
    }

    private function combobox(array $data = []): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent(LocationCombobox::class, ['name' => 'goshuincho[boughtAt]'] + $data)
            ->actingAs(UserFactory::createOne())
        ;
    }

    public function test_it_offers_nothing_before_anything_is_typed(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $component = $this->combobox();

        $this->assertStringNotContainsString('清水寺', $component->render()->toString());
        $this->assertStringContainsString('role="combobox"', $component->render()->toString());
    }

    public function test_typing_offers_the_matching_locations(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺', 'locality' => 'Kyōto']);
        LocationFactory::createOne(['romanizedName' => 'Byodo-in']);

        $rendered = $this->combobox()
            ->set('term', 'kiyomizu')
            ->render()
            ->toString()
        ;

        $this->assertStringContainsString('Kiyomizu-dera', $rendered, 'The principal name is missing.');
        $this->assertStringContainsString('清水寺', $rendered, 'The Japanese name is missing.');
        $this->assertStringContainsString('Kyōto', $rendered);
        $this->assertStringNotContainsString('Byodo-in', $rendered);
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

        $component = $this->combobox();

        $this->assertSame('伏見稲荷大社', $component->set('term', '伏見稲荷大社')->component()->getCreatable());
    }

    public function test_an_exact_match_is_not_offered_for_creation(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $component = $this->combobox();

        $this->assertNull($component->set('term', 'Kiyomizu-dera')->component()->getCreatable(), 'An existing location was offered for creation.');
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

        $rendered = $component->render()->toString();
        $this->assertStringContainsString('<option value="shrine" selected>', $rendered, 'The inference was applied without being shown in the field.');
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

    public function test_the_address_search_is_absent_when_no_geocoder_is_configured(): void
    {
        $rendered = $this->combobox()->set('term', 'Nowhere')->call('startCreating')->render()->toString();

        $this->assertStringNotContainsString('Find the place', $rendered, 'The address search appeared without a geocoder.');
        $this->assertStringContainsString('Romanized name', $rendered, 'The manual fields disappeared with it.');
    }

    public function test_nothing_of_the_search_is_rendered_without_a_geocoder(): void
    {
        $rendered = $this->combobox()->set('term', 'Nowhere')->call('startCreating')->render()->toString();

        $this->assertStringNotContainsString('data-loading', $rendered, 'A loader was rendered without a geocoder configured.');
        $this->assertStringNotContainsString('Find the place', $rendered);
    }


    public function test_clearing_releases_the_choice(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);

        $component = $this->combobox(['selected' => $location->getId()])->call('clear');

        $this->assertNull($component->component()->selected);
        $this->assertStringContainsString('role="combobox"', $component->render()->toString(), 'Clearing did not bring the search back.');
    }
}
