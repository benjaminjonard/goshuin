<?php

declare(strict_types=1);

namespace App\Tests\App\LiveComponent;

use App\Entity\User;
use App\Repository\LocationRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\CityFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use App\Twig\Components\LocationCombobox;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationComboboxTest extends AppTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use ResetDatabase;

    private User $collector;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = UserFactory::createOne();
        $this->signIn($this->collector);
    }

    public function test_it_offers_nothing_before_anything_is_typed(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺']);

        $rendered = $this->combobox()->render()->toString();

        $this->assertStringNotContainsString('清水寺', $rendered);
        $this->assertStringContainsString('role="combobox"', $rendered);
    }

    public function test_typing_offers_the_matching_locations(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺', 'city' => CityFactory::createOne(['romanizedName' => 'Kyōto'])]);
        LocationFactory::createOne(['romanizedName' => 'Byodo-in']);

        $rendered = $this->combobox()->set('term', 'kiyomizu')->render()->toString();

        $this->assertStringContainsString('Kiyomizu-dera', $rendered, 'The principal name is missing.');
        $this->assertStringContainsString('清水寺', $rendered, 'The Japanese name is missing.');
        $this->assertStringContainsString('Kyōto', $rendered);
        $this->assertStringNotContainsString('Byodo-in', $rendered);
    }

    public function test_it_searches_on_either_name(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha', 'kanjiName' => '伏見稲荷大社']);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺']);
        $component = $this->combobox();

        $this->assertStringContainsString('Fushimi Inari-taisha', $component->set('term', '伏見')->render()->toString(), 'The Japanese name was not searched.');
        $this->assertStringContainsString('Kiyomizu-dera', $component->set('term', 'KIYOMIZU')->render()->toString(), 'The search was case-sensitive.');
    }

    public function test_it_says_so_only_when_it_found_nothing(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺']);
        $component = $this->combobox();

        $found = $component->set('term', 'kiyomizu')->render()->toString();
        $this->assertStringNotContainsString('No result', $found, 'It claimed nothing was found while listing a result.');

        $nothing = $component->set('term', 'zzz nowhere')->render()->toString();
        $this->assertStringContainsString('No result', $nothing, 'An empty search said nothing at all.');
    }

    public function test_a_term_that_matches_nothing_offers_to_create_it(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺']);

        $this->assertSame('伏見稲荷大社', $this->combobox()->set('term', '伏見稲荷大社')->component()->getCreatable());
    }

    public function test_an_exact_match_is_not_offered_for_creation(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺']);

        $this->assertNull($this->combobox()->set('term', 'Kiyomizu-dera')->component()->getCreatable(), 'An existing location was offered for creation.');
    }

    public function test_an_exact_match_on_a_japanese_name_is_not_offered_for_creation(): void
    {
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺', 'kanaName' => 'きよみずでら']);

        $this->assertNull($this->combobox()->set('term', '清水寺')->component()->getCreatable(), 'A location named in kanji was offered for creation.');
        $this->assertNull($this->combobox()->set('term', 'きよみずでら')->component()->getCreatable(), 'A location named in kana was offered for creation.');
    }

    public function test_choosing_a_location_puts_its_id_in_the_form_field(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺']);
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

    public function test_creating_hands_the_typed_name_to_the_form(): void
    {
        $component = $this->combobox()->set('term', '伏見稲荷大社')->call('startCreating');

        $this->assertTrue($component->component()->creating);
        $this->assertSame('伏見稲荷大社', $component->component()->named, 'The typed name was not handed over.');
        $this->assertStringContainsString('伏見稲荷大社', $component->render()->toString(), 'The form did not start from what was typed.');
    }

    public function test_the_form_it_opens_infers_the_type_from_the_typed_name(): void
    {
        $rendered = $this->combobox()->set('term', '伏見稲荷大社')->call('startCreating')->render()->toString();

        $this->assertMatchesRegularExpression('/<option value="shrine"[^>]*selected/', $rendered, 'The suffix was not read.');
    }

    public function test_a_name_with_no_recognised_suffix_infers_nothing(): void
    {
        $rendered = $this->combobox()->set('term', 'Some Place')->call('startCreating')->render()->toString();

        $this->assertDoesNotMatchRegularExpression('/<option value="(shrine|temple|other)"[^>]*selected/', $rendered, 'A type was preselected for a name with no recognised suffix.');
    }

    public function test_it_selects_the_location_the_form_reports_as_created(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $component = $this->combobox()
            ->set('term', 'Kiyomizu-dera')
            ->call('startCreating')
            ->emit('location:created', ['location' => $location->getId()])
        ;

        $this->assertSame($location->getId(), $component->component()->selected, 'The created location was not selected.');
        $this->assertFalse($component->component()->creating, 'The creation panel stayed open.');
        $this->assertSame('', $component->component()->term, 'The typed term survived the creation.');
    }

    public function test_cancelling_closes_the_panel_and_keeps_the_field_empty(): void
    {
        $component = $this->combobox()
            ->set('term', '清水寺')
            ->call('startCreating')
            ->emit('location:cancelled')
        ;

        $this->assertCount(0, $this->locations()->findAll(), 'Cancelling still created a location.');
        $this->assertFalse($component->component()->creating);
        $this->assertNull($component->component()->selected);
        $this->assertSame('', $component->component()->named, 'The abandoned name was kept.');
    }

    public function test_clearing_releases_the_choice(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'kanjiName' => '清水寺']);

        $component = $this->combobox(['selected' => $location->getId()])->call('clear');

        $this->assertNull($component->component()->selected);
        $this->assertStringContainsString('role="combobox"', $component->render()->toString(), 'Clearing did not bring the search back.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function combobox(array $data = []): TestLiveComponent
    {
        return $this->createLiveComponent(LocationCombobox::class, ['name' => 'goshuincho[boughtAt]'] + $data)
            ->actingAs($this->collector)
        ;
    }

    private function locations(): LocationRepository
    {
        static::getContainer()->get('doctrine')->getManager()->clear();

        return static::getContainer()->get(LocationRepository::class);
    }
}
