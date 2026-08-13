<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Repository\GoshuinRepository;
use App\Repository\LocationRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
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
            'locality' => 'Kyōto',
            'prefecture' => 'Kyōto',
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
            'locality' => 'Kyōto',
            'prefecture' => 'Kyōto',
            'address' => 'Higashiyama-ku, Kyōto',
            'latitude' => 34.9948,
            'longitude' => 135.7850,
        ]);

        $this->collect($goshuincho, $location, '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSame('Kiyomizu-dera 清水寺', preg_replace('/\s+/', ' ', trim($crawler->filter('h1')->text())), 'The page does not name the location in both scripts.');

        $text = $crawler->filter('main')->text();
        $this->assertStringContainsString('Kyōto', $text, 'The page does not read its locality back.');
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
            ->each(static fn (Crawler $row): string => trim($row->filter('span span')->first()->text()))
        ;

        $this->assertSame(['Aaa-jinja', 'Mmm-ji', 'Zzz-dera'], $names, 'The index is not ordered by the romanized name.');
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

    public function test_a_location_is_corrected_from_its_own_form_and_the_correction_shows_everywhere(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $location = LocationFactory::createOne([
            'romanizedName' => 'Kiyomizudera',
            'japaneseName' => null,
            'locality' => null,
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
            'location[locality]' => 'Kyōto',
            'location[prefecture]' => 'Kyōto',
            'location[latitude]' => '34.9948',
            'location[longitude]' => '135.7850',
            'location[notes]' => 'Reached by the Chawan-zaka slope.',
        ]);

        $this->assertResponseRedirects('/location/'.$location->getId());
        $page = $this->client->followRedirect()->filter('main')->text();

        $this->assertStringContainsString('清水寺', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Temple', $page, 'The corrected type does not read back.');
        $this->assertStringContainsString('Kyōto', $page, 'The corrected locality does not read back.');
        $this->assertStringContainsString('34.9948, 135.7850', $page, 'The coordinates given by hand do not read back.');
        $this->assertStringContainsString('Chawan-zaka', $page, 'The notes do not read back.');

        $goshuin = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/1');
        $this->assertStringContainsString('Kiyomizu-dera', $goshuin->filter('main')->text(), 'The corrected name did not reach the goshuin that came from there.');

        $this->emptyUploads();
    }

    public function test_a_location_carries_a_photograph_of_the_place(): void
    {
        $this->client->loginUser(UserFactory::createOne());
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
        $this->assertCount(1, $crawler->filter('main img'), 'The page does not show the photograph of the place.');
        $this->assertSame('/uploads/'.$stored->getPhotographFull(), $crawler->filter('main img')->attr('src'), 'The page does not serve the photograph at size.');

        $this->removeUploads($stored->getPhotograph(), $stored->getPhotographMini(), $stored->getPhotographCard(), $stored->getPhotographFull());
    }

    public function test_a_location_still_in_use_cannot_be_deleted_whoever_uses_it(): void
    {
        $owner = UserFactory::createOne();
        $this->client->loginUser($owner);
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $owner]);
        $this->collect($goshuincho, $location, '2025-03-14');
        $id = $location->getId();

        $this->client->loginUser(UserFactory::createOne());
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
        $this->client->loginUser(UserFactory::createOne());
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
        $this->client->loginUser(UserFactory::createOne());
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

    private function emptyUploads(): void
    {
        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
        }
    }
}
