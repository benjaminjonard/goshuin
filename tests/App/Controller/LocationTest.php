<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Deity;
use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Entity\LocationPhoto;
use App\Repository\CityRepository;
use App\Repository\DeityRepository;
use App\Repository\GoshuinRepository;
use App\Repository\LocationPhotoRepository;
use App\Repository\LocationRepository;
use App\Repository\PrefectureRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\CityFactory;
use App\Tests\Factory\DeityFactory;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\PrefectureFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_the_index_is_private(): void
    {
        LocationFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/locations');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function test_the_index_lists_the_locations_and_opens_each_one(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kiyomizu = LocationFactory::createOne([
            'romanizedName' => 'Kiyomizu-dera',
            'japaneseName' => '清水寺',
            'city' => CityFactory::createOne(['name' => 'Kyōto']),
            'prefecture' => PrefectureFactory::createOne(['name' => 'Kyōto']),
        ]);
        LocationFactory::createOne(['romanizedName' => 'Sensō-ji', 'japaneseName' => null]);

        $crawler = $this->client->request(Request::METHOD_GET, '/locations');

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('main ul li a'), 'The index does not list every location.');

        $first = $crawler->filter('main ul li a')->first();
        $this->assertSame('/location/'.$kiyomizu->getId(), $first->attr('href'), 'A location does not open its own page.');
        $this->assertStringContainsString('清水寺', $first->text());
        $this->assertStringContainsString('Kiyomizu-dera', $first->text(), 'The index drops the romanized name when a Japanese one exists.');
        $this->assertStringContainsString('Kyōto', $first->text(), 'The index does not say where the location is.');
    }

    public function test_the_index_states_that_no_location_exists_yet(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/locations');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'The index invented a row.');
        $this->assertStringContainsString('No location yet', $crawler->filter('main')->text());
        $this->assertGreaterThan(0, $crawler->filter('main a, main button')->count(), 'The empty index offers nothing to do.');
    }

    public function test_the_search_matches_any_part_of_a_name_in_any_script(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);
        LocationFactory::createOne(['romanizedName' => 'Sensō-ji', 'japaneseName' => '浅草寺']);

        foreach (['mizu' => 'Kiyomizu-dera', 'KIYO' => 'Kiyomizu-dera', '清水' => 'Kiyomizu-dera', '浅草' => 'Sensō-ji'] as $term => $expected) {
            $crawler = $this->client->request(Request::METHOD_GET, '/locations?q='.urlencode((string) $term));
            $rows = $crawler->filter('main ul li a');

            $this->assertCount(1, $rows, sprintf('Searching "%s" does not match exactly one location.', $term));
            $this->assertStringContainsString($expected, $rows->text(), sprintf('Searching "%s" matched the wrong location.', $term));
        }
    }

    public function test_a_search_matching_nothing_says_so_and_leads_back(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/locations?q=nowhere');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'A search matching nothing still listed a location.');
        $this->assertStringContainsString('No result', $crawler->filter('main')->text());
        $this->assertSame('/locations', $crawler->filter('main p a')->attr('href'), 'A fruitless search is a dead end.');
    }

    public function test_a_location_page_describes_the_place_and_nothing_else(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai, spring 2025']);
        $location = LocationFactory::createOne([
            'romanizedName' => 'Kiyomizu-dera',
            'japaneseName' => '清水寺',
            'city' => CityFactory::createOne(['name' => 'Kyōto']),
            'prefecture' => PrefectureFactory::createOne(['name' => 'Kyōto']),
            'address' => 'Higashiyama-ku, Kyōto',
            'latitude' => 34.9948,
            'longitude' => 135.7850,
        ]);

        $this->collect($goshuincho, $location, '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSame('Kiyomizu-dera 清水寺', preg_replace('/\s+/', ' ', trim($crawler->filter('h1')->text())), 'The page does not name the location in both scripts.');

        $text = $crawler->filter('main')->text();
        $this->assertStringContainsString('Kyōto', $text, 'The page does not read its city back.');
        $this->assertStringContainsString('Higashiyama-ku', $text, 'The page does not read its address back.');
        $this->assertStringContainsString('34.9948, 135.7850', $text, 'The page does not read its coordinates back.');
        $this->assertCount(1, $crawler->filter('main [data-controller="map"]'), 'A placed location is not put on a map.');

        $this->assertCount(0, $crawler->filter('main img'), 'The page shows the goshuin received there again.');
        $this->assertStringNotContainsString('Kansai, spring 2025', $text, 'The page names a goshuincho it no longer lists.');

        $this->emptyUploads();
    }

    public function test_a_location_another_collector_uses_gives_nothing_away(): void
    {
        $owner = UserFactory::createOne();
        $this->client->loginUser($owner);
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $owner, 'title' => 'Not yours']);
        $this->collect($goshuincho, $location, '2025-03-14');
        $id = $location->getId();

        $this->client->loginUser(UserFactory::createOne());
        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$id);

        $this->assertResponseIsSuccessful('A location used by another collector is unreachable.');
        $this->assertStringNotContainsString('Not yours', $crawler->filter('body')->text(), 'A foreign goshuincho was named on a shared location.');

        $index = $this->client->request(Request::METHOD_GET, '/locations');
        $this->assertCount(1, $index->filter('main ul li a'), 'A location used by another collector left the index.');
        $this->assertStringNotContainsString('Not yours', $index->filter('main')->text(), 'The index named a foreign goshuincho.');

        $this->emptyUploads();
    }

    public function test_a_location_with_no_coordinates_is_simply_shown_without_a_map(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $location = LocationFactory::createOne(['romanizedName' => 'Somewhere', 'latitude' => null, 'longitude' => null]);

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('[data-controller="map"]'), 'A location with no coordinates was still put on a map.');
        $this->assertStringNotContainsString('—', $crawler->filter('main')->text(), 'The page filled an absence with a dash.');
    }

    public function test_the_index_is_ordered_by_the_romanized_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['Zzz-dera', 'Aaa-jinja', 'Mmm-ji'] as $name) {
            LocationFactory::createOne(['romanizedName' => $name, 'japaneseName' => null]);
        }

        $names = $this->client->request(Request::METHOD_GET, '/locations')
            ->filter('main ul li a')
            ->each(static fn (Crawler $row): string => trim($row->filter('h3')->text()))
        ;

        $this->assertSame(['Aaa-jinja', 'Mmm-ji', 'Zzz-dera'], $names, 'The index is not ordered by the romanized name.');
    }

    public function test_the_index_pages_through_the_locations(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 26) as $rank) {
            LocationFactory::createOne(['romanizedName' => sprintf('Place %02d', $rank), 'japaneseName' => null]);
        }

        $first = $this->client->request(Request::METHOD_GET, '/locations');
        $listed = $first->filter('main ul')->text();

        $this->assertCount(24, $first->filter('main ul li a'), 'The index does not hold a full page of locations.');
        $this->assertStringContainsString('Place 01', $listed);
        $this->assertStringNotContainsString('Place 25', $listed, 'The first page reaches past its own end.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $second->filter('main ul li a'), 'The last page does not hold what the first left.');
        $this->assertStringContainsString('Place 26', $second->filter('main ul')->text());
    }

    public function test_a_single_page_of_locations_is_not_paged(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/locations');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main nav'), 'A single page still offered a way through.');
    }

    public function test_paging_the_locations_keeps_the_search(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 25) as $rank) {
            LocationFactory::createOne(['romanizedName' => sprintf('Kyōto place %02d', $rank), 'japaneseName' => null]);
        }

        LocationFactory::createOne(['romanizedName' => 'Sensō-ji', 'japaneseName' => null]);

        $first = $this->client->request(Request::METHOD_GET, '/locations?q='.urlencode('Kyōto'));
        $this->assertCount(24, $first->filter('main ul li a'), 'The search does not fill a page.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $second->filter('main ul li a'), 'Paging a search dropped it and listed everything again.');
        $this->assertStringNotContainsString('Sensō-ji', $second->filter('main ul')->text(), 'Paging a search reached what it excluded.');
    }

    public function test_a_page_of_locations_beyond_the_last_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $this->client->request(Request::METHOD_GET, '/locations?page=2');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_a_goshuin_leads_to_the_location_it_came_from(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $this->collect($goshuincho, $location, '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/1');

        $this->assertCount(
            1,
            $crawler->filter('main a[href="/location/'.$location->getId().'"]'),
            'The goshuin does not lead to the location it came from.',
        );

        $this->emptyUploads();
    }

    public function test_photographs_are_added_ordered_labelled_and_removed(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $this->correct($location, [
            'photo_add' => ['place' => [$this->createImage(600, 450), $this->createImage(600, 450), $this->createImage(600, 450)]],
            'photo_add_label' => ['place' => ['The gate', '', 'The pagoda']],
        ]);

        $photos = $this->gallery($location);
        $this->assertCount(3, $photos, 'The three photographs did not all land.');
        $this->assertSame([1, 2, 3], array_map(static fn (LocationPhoto $p): ?int => $p->getPosition(), $photos), 'The photographs are not numbered from one.');
        $this->assertSame(['The gate', null, 'The pagoda'], array_map(static fn (LocationPhoto $p): ?string => $p->getLabel(), $photos), 'A label was lost or invented.');

        [$first, $second, $third] = array_map(static fn (LocationPhoto $p): string => $p->getId(), $photos);

        $this->correct($location, [
            'photo_order' => ['place' => [$third, $first, $second]],
            'photo_label' => ['place' => [$second => 'The spring']],
            'photo_remove' => ['place' => [$first]],
        ]);

        $left = $this->gallery($location);
        $this->assertCount(2, $left, 'The removal took the wrong number of photographs.');
        $this->assertSame([$third, $second], array_map(static fn (LocationPhoto $p): string => $p->getId(), $left), 'The order was not kept.');
        $this->assertSame([1, 2], array_map(static fn (LocationPhoto $p): ?int => $p->getPosition(), $left), 'The positions left a gap.');
        $this->assertSame('The spring', $left[1]->getLabel(), 'A label typed on an existing photograph was lost.');

        $this->discard($location);
    }

    public function test_a_photograph_that_is_not_an_image_is_refused_without_taking_the_others_down(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne();

        $this->correct($location, [
            'photo_add' => ['place' => [$this->createImage(600, 450), $this->createTextFile()]],
        ]);

        $this->assertCount(1, $this->gallery($location), 'The refused file was stored, or it took the good one down with it.');

        $this->discard($location);
    }

    public function test_every_collector_sees_the_photographs_of_a_location(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);
        $this->correct($location, [
            'photo_add' => ['place' => [$this->createImage(600, 450)]],
            'photo_add_label' => ['place' => ['The gate']],
        ]);

        $this->client->loginUser(UserFactory::createOne());
        $main = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId())->filter('main');

        $this->assertCount(1, $main->filter('.gallery img'), 'A photograph added by somebody else was hidden.');
        $this->assertSame('The gate', trim($main->filter('figcaption')->text()), 'The label does not caption the photograph.');
        $this->assertCount(0, $main->filter('form'), 'A collector was offered something to change.');

        $this->discard($location);
    }

    public function test_deleting_a_location_takes_its_photographs_with_it(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne();
        $this->correct($location, ['photo_add' => ['place' => [$this->createImage(600, 450)]]]);

        $photo = $this->gallery($location)[0];
        $paths = [$photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull()];
        $id = $location->getId();

        $confirmation = $this->client->request(Request::METHOD_GET, '/location/'.$id.'/delete');
        $this->client->submit($confirmation->selectButton('delete_submit')->form());

        $this->assertNull(static::getContainer()->get(LocationRepository::class)->find($id), 'The location survived.');
        $this->assertCount(0, static::getContainer()->get(LocationPhotoRepository::class)->findAll(), 'A photograph outlived its location.');
        $this->assertFileDoesNotExist($this->uploadsDir().'/'.$paths[0], 'The stored image outlived its location.');

        $this->removeUploads(...$paths);
    }

    public function test_the_location_page_offers_no_way_to_add_photographs(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $location = LocationFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('input[name^="photo_add"]'), 'A collector was offered a way to add photographs.');
    }

    public function test_a_location_records_several_deities_and_its_foundation(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Goryo-jinja']);

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $form = $crawler->selectButton('location_submit')->form();
        $values = $form->getPhpValues();
        $values['location']['deities'] = ['鎌倉権五郎景政', '福禄寿'];
        $values['location']['foundation'] = 'Fin de l\'époque de Heian';

        $this->client->request(Request::METHOD_POST, $form->getUri(), $values);
        $this->assertResponseRedirects();

        $this->manager()->clear();
        $stored = static::getContainer()->get(LocationRepository::class)->find($location->getId());

        $this->assertSame(['福禄寿', '鎌倉権五郎景政'], $this->named($stored), 'The deities were not all kept.');
        $this->assertSame('Fin de l\'époque de Heian', $stored->getFoundation());

        $text = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId())->filter('main')->text();

        $this->assertStringContainsString('鎌倉権五郎景政', $text, 'The page does not name the deities.');
        $this->assertStringContainsString('福禄寿', $text);
        $this->assertStringContainsString('Heian', $text, 'The page does not say when the place was founded.');
    }

    public function test_the_named_deities_lead_to_their_own_page(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Goryo-jinja']);

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $form = $crawler->selectButton('location_submit')->form();
        $values = $form->getPhpValues();
        $values['location']['deities'] = ['八幡神', '福禄寿'];

        $this->client->request(Request::METHOD_POST, $form->getUri(), $values);

        $this->manager()->clear();
        $deities = static::getContainer()->get(DeityRepository::class)->findAll();
        $this->assertCount(2, $deities);

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId());

        foreach ($deities as $deity) {
            $link = $crawler->filter('main dd a[href="/deity/'.$deity->getId().'"]');

            $this->assertCount(1, $link, 'The deity '.$deity->getName().' is named without leading anywhere.');
            $this->assertSame($deity->getName(), $link->text(), 'The link does not carry the name of the deity.');
        }

        $this->client->click($crawler->filter('main dd a')->first()->link());
        $this->assertRouteSame('app_deity_show');
    }

    public function test_two_locations_naming_the_same_deity_share_one(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $first = LocationFactory::createOne(['romanizedName' => 'Tsurugaoka Hachimangu']);
        $second = LocationFactory::createOne(['romanizedName' => 'Usa Jingu']);

        foreach ([$first, $second] as $location) {
            $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
            $form = $crawler->selectButton('location_submit')->form();
            $values = $form->getPhpValues();
            $values['location']['deities'] = ['八幡神'];

            $this->client->request(Request::METHOD_POST, $form->getUri(), $values);
            $this->assertResponseRedirects();
        }

        $this->manager()->clear();
        $deities = static::getContainer()->get(DeityRepository::class)->findAll();

        $this->assertCount(1, $deities, 'The same deity was stored twice.');

        $repository = static::getContainer()->get(LocationRepository::class);
        $this->assertSame(['八幡神'], $this->named($repository->find($first->getId())));
        $this->assertSame(['八幡神'], $this->named($repository->find($second->getId())), 'The second location did not reach the shared deity.');
    }

    public function test_a_blank_deity_is_not_kept(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $form = $crawler->selectButton('location_submit')->form();
        $values = $form->getPhpValues();
        $values['location']['deities'] = ['八幡神', '   ', ''];

        $this->client->request(Request::METHOD_POST, $form->getUri(), $values);

        $this->manager()->clear();
        $stored = static::getContainer()->get(LocationRepository::class)->find($location->getId());

        $this->assertSame(['八幡神'], $this->named($stored), 'A blank deity was stored.');
    }

    public function test_a_collector_cannot_reach_the_edit_form(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $location = LocationFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');

        $this->assertResponseStatusCodeSame(403, 'A collector reached the reference data.');
    }

    public function test_a_collector_cannot_delete_a_location(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $location = LocationFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/delete');

        $this->assertResponseStatusCodeSame(403, 'A collector could delete reference data.');
    }

    public function test_a_collector_is_offered_neither_edition_nor_deletion(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $location = LocationFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('a[href$="/edit"]'), 'A collector was offered a door they cannot open.');
        $this->assertCount(0, $crawler->filter('a[href$="/delete"]'));
    }

    public function test_an_administrator_is_offered_both(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('a[href$="/edit"]'), 'The administrator lost the way in.');
        $this->assertCount(1, $crawler->filter('a[href$="/delete"]'));
    }

    public function test_a_collector_still_creates_a_missing_location(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/locations');

        $this->assertResponseIsSuccessful('A collector lost sight of the locations.');
    }

    public function test_the_edit_form_is_the_shared_location_form(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('[data-live-name-value="LocationForm"]'), 'The edit page does not render the shared location form.');
        $this->assertCount(1, $crawler->filter('input[name="location[romanizedName]"]'));
        $this->assertCount(1, $crawler->filter('input[name="location[photographFile]"]'), 'The edit form lost the photograph.');
    }

    public function test_the_edit_form_offers_no_address_search_without_a_geocoder(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('[data-model="debounce(1000)|address"]'), 'The address search appeared without a geocoder configured.');
        $this->assertCount(1, $crawler->filter('input[name="location[romanizedName]"]'), 'The manual fields disappeared with it.');
    }

    public function test_a_location_is_corrected_from_its_own_form_and_the_correction_shows_everywhere(): void
    {
        $user = UserFactory::new()->admin()->create();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $location = LocationFactory::createOne([
            'romanizedName' => 'Kiyomizudera',
            'japaneseName' => null,
            'city' => null,
            'prefecture' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->collect($goshuincho, $location, '2025-03-14');

        $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $this->client->submitForm('location_submit', [
            'location[romanizedName]' => 'Kiyomizu-dera',
            'location[japaneseName]' => '清水寺',
            'location[type]' => 'temple',
            'location[city]' => 'Kyōto',
            'location[prefecture]' => 'Kyōto',
            'location[latitude]' => '34.9948',
            'location[longitude]' => '135.7850',
            'location[notes]' => 'Reached by the Chawan-zaka slope.',
        ]);

        $this->assertResponseRedirects('/location/'.$location->getId());
        $page = $this->client->followRedirect()->filter('main')->text();

        $this->assertStringContainsString('清水寺', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Temple', $page, 'The corrected type does not read back.');
        $this->assertStringContainsString('Kyōto', $page, 'The corrected city does not read back.');
        $this->assertStringContainsString('34.9948, 135.7850', $page, 'The coordinates given by hand do not read back.');
        $this->assertStringContainsString('Chawan-zaka', $page, 'The notes do not read back.');

        $goshuin = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/1');
        $this->assertStringContainsString('Kiyomizu-dera', $goshuin->filter('main')->text(), 'The corrected name did not reach the goshuin that came from there.');

        $this->emptyUploads();
    }

    public function test_a_typed_city_and_prefecture_that_are_unknown_are_created_and_tied_together(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => null, 'prefecture' => null]);

        $this->place($location, 'Kamakura', 'Kanagawa');

        $stored = $this->reread($location);

        $this->assertSame('Kamakura', $stored->getCity()->getName(), 'The typed city was not created.');
        $this->assertSame('Kanagawa', $stored->getPrefecture()->getName(), 'The typed prefecture was not created.');
        $this->assertSame('Kanagawa', $stored->getCity()->getPrefecture()->getName(), 'The new city was not tied to the prefecture typed beside it.');
    }

    public function test_a_typed_city_matches_what_is_stored_whatever_the_casing(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $kamakura = CityFactory::createOne(['name' => 'Kamakura']);
        $kanagawa = PrefectureFactory::createOne(['name' => 'Kanagawa']);
        $location = LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => null, 'prefecture' => null]);

        $this->place($location, '  kAmAkUrA  ', 'KANAGAWA');

        $stored = $this->reread($location);

        $this->assertSame($kamakura->getId(), $stored->getCity()->getId(), 'A second city was created for a name already stored.');
        $this->assertSame($kanagawa->getId(), $stored->getPrefecture()->getId(), 'A second prefecture was created for a name already stored.');
        $this->assertCount(1, static::getContainer()->get(CityRepository::class)->findAll(), 'The city table grew.');
        $this->assertCount(1, static::getContainer()->get(PrefectureRepository::class)->findAll(), 'The prefecture table grew.');
    }

    public function test_a_city_already_tied_to_a_prefecture_keeps_it(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $kanagawa = PrefectureFactory::createOne(['name' => 'Kanagawa']);
        CityFactory::createOne(['name' => 'Fuchū', 'prefecture' => $kanagawa]);
        PrefectureFactory::createOne(['name' => 'Tokyo']);
        $location = LocationFactory::createOne(['romanizedName' => 'Ōkunitama Jinja', 'city' => null, 'prefecture' => null]);

        $this->place($location, 'Fuchū', 'Tokyo');

        $stored = $this->reread($location);

        $this->assertSame('Tokyo', $stored->getPrefecture()->getName(), 'The prefecture typed on the location was not kept.');
        $this->assertSame('Kanagawa', $stored->getCity()->getPrefecture()->getName(), 'A stored city was moved by a prefecture typed beside it.');
    }

    public function test_clearing_the_city_lets_go_of_it_without_removing_it(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $kamakura = CityFactory::createOne(['name' => 'Kamakura']);
        $location = LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => $kamakura, 'prefecture' => null]);

        $this->place($location, '', '');

        $this->assertNull($this->reread($location)->getCity(), 'The location still points at a city.');
        $this->assertNotNull(static::getContainer()->get(CityRepository::class)->find($kamakura->getId()), 'Letting go of a city removed it.');
    }

    public function test_a_city_typed_as_whitespace_alone_creates_nothing(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => null, 'prefecture' => null]);

        $this->place($location, '   ', '   ');

        $stored = $this->reread($location);

        $this->assertNull($stored->getCity(), 'A city was made out of whitespace.');
        $this->assertNull($stored->getPrefecture(), 'A prefecture was made out of whitespace.');
        $this->assertCount(0, static::getContainer()->get(CityRepository::class)->findAll(), 'A city row was written anyway.');
        $this->assertCount(0, static::getContainer()->get(PrefectureRepository::class)->findAll(), 'A prefecture row was written anyway.');
    }

    private function place(Location $location, string $city, string $prefecture): void
    {
        $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $this->client->submitForm('location_submit', [
            'location[romanizedName]' => $location->getRomanizedName(),
            'location[city]' => $city,
            'location[prefecture]' => $prefecture,
        ]);

        $this->assertResponseRedirects();
        $this->manager()->clear();
    }

    private function reread(Location $location): Location
    {
        return static::getContainer()->get(LocationRepository::class)->find($location->getId());
    }

    public function test_a_deity_named_in_the_notes_of_a_location_leads_to_its_page(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $inari = DeityFactory::createOne(['name' => 'Inari', 'additionalNames' => ['Oinari-san']]);
        $location = LocationFactory::createOne(['notes' => 'Oinari-san watches the slope. Inarite is a word, not a deity.']);

        $notes = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId())
            ->filter('main a[href="/deity/'.$inari->getId().'"]')
        ;

        $this->assertCount(1, $notes, 'The name in the notes does not lead to the deity.');
        $this->assertSame('Oinari-san', $notes->text(), 'A name was read inside a longer word.');
    }

    public function test_a_location_carries_a_photograph_of_the_place(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $this->client->submitForm('location_submit', [
            'location[romanizedName]' => 'Kiyomizu-dera',
            'location[photographFile]' => $this->createImage(1600, 1100),
        ]);

        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $stored = static::getContainer()->get(LocationRepository::class)->find($location->getId());
        $this->assertNotNull($stored->getPhotographFull(), 'The photograph was not stored.');
        $this->assertCount(1, $crawler->filter('main .stage img'), 'The page does not show the photograph of the place.');
        $this->assertSame('/uploads/'.$stored->getPhotographFull(), $crawler->filter('main .stage img')->attr('src'), 'The page does not serve the photograph at size.');
        $this->assertSame('/uploads/'.$stored->getPhotograph(), $crawler->filter('main .stage a')->attr('href'), 'The photograph does not open at full size.');

        $this->removeUploads($stored->getPhotograph(), $stored->getPhotographMini(), $stored->getPhotographCard(), $stored->getPhotographFull());
    }

    public function test_a_location_still_in_use_cannot_be_deleted_whoever_uses_it(): void
    {
        $owner = UserFactory::new()->admin()->create();
        $this->client->loginUser($owner);
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $owner]);
        $this->collect($goshuincho, $location, '2025-03-14');
        $id = $location->getId();

        $this->client->loginUser(UserFactory::new()->admin()->create());
        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$id.'/delete');

        $this->assertStringContainsString('still in use', $crawler->filter('main')->text(), 'The refusal is not stated.');
        $this->assertCount(0, $crawler->filter('main button'), 'A location in use still offers to be deleted.');

        $unused = LocationFactory::createOne(['romanizedName' => 'Unused']);
        $confirmation = $this->client->request(Request::METHOD_GET, '/location/'.$unused->getId().'/delete');
        $forged = $confirmation->selectButton('delete_submit')->form()->getPhpValues();

        $this->client->request(Request::METHOD_POST, '/location/'.$id.'/delete', $forged);

        $this->assertResponseRedirects('/location/'.$id);
        $this->assertNotNull(static::getContainer()->get(LocationRepository::class)->find($id), 'A location in use was deleted anyway.');

        $this->emptyUploads();
    }

    public function test_a_location_nothing_uses_is_deleted_and_leaves_the_index(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Never used']);
        $id = $location->getId();

        $this->client->request(Request::METHOD_GET, '/location/'.$id.'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects('/locations');
        $crawler = $this->client->followRedirect();

        $this->assertNull(static::getContainer()->get(LocationRepository::class)->find($id), 'The location was not deleted.');
        $this->assertStringNotContainsString('Never used', $crawler->filter('main')->text(), 'The deleted location is still listed.');
    }

    public function test_the_form_refuses_a_location_with_no_romanized_name(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $this->client->submitForm('location_submit', ['location[romanizedName]' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            'Kiyomizu-dera',
            static::getContainer()->get('doctrine')->getConnection()->fetchOne(
                'SELECT romanized_name FROM gos_location WHERE id = :id',
                ['id' => $location->getId()],
            ),
            'A refused submission still wrote the location away without a name.',
        );
    }

    public function test_the_form_token_asks_the_browser_for_nothing(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $location = LocationFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');

        $this->assertCount(
            0,
            $crawler->filter('[data-controller="csrf-protection"]'),
            'The token expects a double-submit the live component cannot perform, which poisons every later submission.',
        );
    }

    private function collect(Goshuincho $goshuincho, Location $location, string $day): void
    {
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $location->getId(),
            'goshuin[receivedOn]' => $day,
            'goshuin[imageFile]' => $this->createImage(900, 1230),
        ]);
        $this->assertResponseRedirects();
    }

    /**
     * @return list<string>
     */
    private function named(Location $location): array
    {
        $names = array_map(static fn (Deity $deity): string => (string) $deity->getName(), $location->getDeities()->toArray());
        sort($names);

        return array_values($names);
    }

    /**
     * @param array<string, mixed> $photographs
     */
    private function correct(Location $location, array $photographs): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId().'/edit');
        $form = $crawler->selectButton('location_submit')->form();

        $added = $photographs['photo_add'] ?? [];
        unset($photographs['photo_add']);

        $this->client->request(
            Request::METHOD_POST,
            $form->getUri(),
            [...$form->getPhpValues(), ...$photographs],
            $added === [] ? [] : ['photo_add' => $added],
        );

        $this->assertResponseRedirects();
        $this->manager()->clear();
    }

    /**
     * @return list<LocationPhoto>
     */
    private function gallery(Location $location): array
    {
        $this->manager()->clear();

        return static::getContainer()->get(LocationPhotoRepository::class)->ofSubject(
            static::getContainer()->get(LocationRepository::class)->find($location->getId()),
        );
    }

    private function discard(Location $location): void
    {
        foreach ($this->gallery($location) as $photo) {
            $this->removeUploads($photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull());
        }
    }

    private function emptyUploads(): void
    {
        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
        }
    }
}
