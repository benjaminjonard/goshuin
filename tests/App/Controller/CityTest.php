<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\City;
use App\Entity\CityPhoto;
use App\Repository\CityRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\CityFactory;
use App\Tests\Factory\GoshuinFactory;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\PrefectureFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CityTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_the_cities_are_private(): void
    {
        $city = CityFactory::createOne();

        foreach (['/cities', '/city/'.$city->getSlug(), '/city/'.$city->getSlug().'/edit', '/city/'.$city->getSlug().'/delete'] as $url) {
            $this->client->request(Request::METHOD_GET, $url);

            $this->assertResponseRedirects();
            $this->client->followRedirect();
            $this->assertRouteSame('app_login', [], sprintf('%s is reachable signed out.', $url));
        }
    }

    public function test_a_collector_may_not_change_a_city(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $city = CityFactory::createOne();

        foreach (['/city/'.$city->getSlug().'/edit', '/city/'.$city->getSlug().'/delete'] as $url) {
            $this->client->request(Request::METHOD_GET, $url);

            $this->assertResponseStatusCodeSame(403, sprintf('%s is open to a collector.', $url));
        }
    }

    public function test_the_index_lists_the_cities_and_opens_each_one(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        CityFactory::createOne(['name' => 'Nara']);
        $kamakura = CityFactory::createOne(['name' => 'Kamakura', 'prefecture' => PrefectureFactory::createOne(['name' => 'Kanagawa'])]);

        $crawler = $this->client->request(Request::METHOD_GET, '/cities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('main ul li a'), 'The index does not list every city.');

        $first = $crawler->filter('main ul li a')->first();
        $this->assertSame('/city/'.$kamakura->getSlug(), $first->attr('href'), 'A city does not open its own page.');
        $this->assertStringContainsString('Kamakura', $first->text());
        $this->assertStringContainsString('Kanagawa', $first->text(), 'The index does not say which prefecture holds the city.');
    }

    public function test_the_index_is_ordered_by_the_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['Yokohama', 'Kamakura', 'Nara'] as $name) {
            CityFactory::createOne(['name' => $name]);
        }

        $names = $this->client->request(Request::METHOD_GET, '/cities')
            ->filter('main ul li a')
            ->each(static fn (Crawler $row): string => trim($row->text()))
        ;

        $this->assertSame(['Kamakura', 'Nara', 'Yokohama'], $names, 'The index is not ordered by the name.');
    }

    public function test_the_index_pages_through_the_cities(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 26) as $rank) {
            CityFactory::createOne(['name' => sprintf('Machi %02d', $rank)]);
        }

        $first = $this->client->request(Request::METHOD_GET, '/cities');

        $this->assertCount(24, $first->filter('main ul li a'), 'The index does not hold a full page of cities.');
        $this->assertStringNotContainsString('Machi 25', $first->filter('main ul')->text(), 'The first page reaches past its own end.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $second->filter('main ul li a'), 'The last page does not hold what the first left.');
    }

    public function test_a_page_of_cities_beyond_the_last_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        CityFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/cities?page=2');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_the_index_states_that_no_city_exists_yet(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/cities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'The index invented a row.');
        $this->assertStringContainsString('No city yet', $crawler->filter('main')->text());
    }

    public function test_the_search_matches_any_part_of_a_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        CityFactory::createOne(['name' => 'Nara']);
        CityFactory::createOne(['name' => 'Kamakura']);

        foreach (['makur' => 'Kamakura', 'KAMA' => 'Kamakura', 'nar' => 'Nara'] as $term => $expected) {
            $rows = $this->client->request(Request::METHOD_GET, '/cities?q='.urlencode((string) $term))->filter('main ul li a');

            $this->assertCount(1, $rows, sprintf('Searching "%s" does not match exactly one city.', $term));
            $this->assertStringContainsString($expected, $rows->text(), sprintf('Searching "%s" matched the wrong city.', $term));
        }
    }

    public function test_a_search_matching_nothing_says_so_and_leads_back(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        CityFactory::createOne(['name' => 'Nara']);

        $crawler = $this->client->request(Request::METHOD_GET, '/cities?q=nobody');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'A search matching nothing still listed a city.');
        $this->assertSame('/cities', $crawler->filter('main p a')->attr('href'), 'A fruitless search is a dead end.');
    }

    public function test_a_city_page_names_it_counts_its_locations_and_leads_to_them(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kanagawa = PrefectureFactory::createOne(['name' => 'Kanagawa']);
        $kamakura = CityFactory::createOne(['name' => 'Kamakura', 'prefecture' => $kanagawa]);
        LocationFactory::createOne(['romanizedName' => 'Tsurugaoka Hachimangū', 'city' => $kamakura]);
        LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => $kamakura]);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/city/'.$kamakura->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertSame('Kamakura', trim($crawler->filter('h1')->text()), 'The page does not name the city.');
        $tiles = $crawler->filter('main a.tile')->each(
            static fn (Crawler $tile): array => [$tile->attr('href'), preg_replace('/\s+/', ' ', trim($tile->text()))],
        );

        $this->assertSame([
            ['/prefecture/'.$kanagawa->getSlug(), 'Kanagawa Prefecture'],
            ['/locations?city='.$kamakura->getSlug(), '2 locations'],
        ], $tiles, 'The page does not name its prefecture and count its locations, nor lead to either.');
    }

    public function test_a_city_holding_no_location_counts_nothing(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $city = CityFactory::createOne(['name' => 'Nara']);

        $crawler = $this->client->request(Request::METHOD_GET, '/city/'.$city->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main a.tile'), 'A count was invented for a city holding nothing.');
    }

    public function test_the_locations_of_a_city_are_listed_on_their_own_page(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kamakura = CityFactory::createOne(['name' => 'Kamakura']);
        LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => $kamakura]);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/locations?city='.$kamakura->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('main ul li a'), 'The list is not narrowed to the locations of the city.');
        $this->assertStringContainsString('Hase-dera', $crawler->filter('main ul li a')->text());
        $this->assertCount(1, $crawler->filter('main a[href="/locations"]'), 'Nothing leads back to the whole list.');
    }

    public function test_an_unknown_city_narrows_nothing(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/locations?city='.Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(404, 'An unknown city was accepted as a filter.');
    }

    public function test_an_unknown_city_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/city/'.Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_the_city_page_offers_a_collector_nothing_to_change(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $city = CityFactory::createOne(['notes' => 'The old capital.']);

        $crawler = $this->client->request(Request::METHOD_GET, '/city/'.$city->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main form'), 'A collector was offered something to change.');
        $this->assertCount(0, $crawler->filter('input[type="file"]'), 'A collector was offered a way to add a photograph.');
    }

    public function test_a_city_is_renamed_and_moved_to_another_prefecture(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        PrefectureFactory::createOne(['name' => 'Kyōto']);
        $kanagawa = PrefectureFactory::createOne(['name' => 'Kanagawa']);
        $city = CityFactory::createOne(['name' => 'Kamakura']);
        $location = LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => $city]);

        $this->client->request(Request::METHOD_GET, '/city/'.$city->getSlug().'/edit');
        $this->client->submitForm('city_submit', [
            'city[name]' => 'Kamakura-shi',
            'city[prefecture]' => $kanagawa->getId(),
            'city[notes]' => 'Seat of the shogunate.',
        ]);

        $this->assertResponseRedirects('/city/'.$city->getSlug());

        $this->manager()->clear();
        $stored = static::getContainer()->get(CityRepository::class)->find($city->getId());

        $this->assertSame('Kamakura-shi', $stored->getName(), 'The new name was not stored.');
        $this->assertSame('Kanagawa', $stored->getPrefecture()->getName(), 'The city was not moved.');
        $this->assertSame('Seat of the shogunate.', $stored->getNotes(), 'The notes were not stored.');

        $page = $this->client->request(Request::METHOD_GET, '/location/'.$location->getSlug())->filter('main')->text();
        $this->assertStringContainsString('Kamakura-shi', $page, 'The renamed city did not reach the location sitting in it.');
    }

    public function test_the_form_refuses_a_city_with_no_name(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $city = CityFactory::createOne(['name' => 'Kamakura']);

        $this->client->request(Request::METHOD_GET, '/city/'.$city->getSlug().'/edit');
        $this->client->submitForm('city_submit', ['city[name]' => '']);

        $this->assertResponseStatusCodeSame(422);

        $this->manager()->clear();
        $this->assertSame('Kamakura', static::getContainer()->get(CityRepository::class)->find($city->getId())->getName(), 'A refused submission still wrote the city away without a name.');
    }

    public function test_the_form_refuses_a_name_another_city_already_bears(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        CityFactory::createOne(['name' => 'Nara']);
        $kamakura = CityFactory::createOne(['name' => 'Kamakura']);

        $this->client->request(Request::METHOD_GET, '/city/'.$kamakura->getSlug().'/edit');
        $crawler = $this->client->submitForm('city_submit', ['city[name]' => 'Nara']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('already bears that name', $crawler->filter('main')->text(), 'The collision is not stated.');

        $this->manager()->clear();
        $this->assertSame('Kamakura', static::getContainer()->get(CityRepository::class)->find($kamakura->getId())->getName(), 'The colliding name was stored anyway.');
    }

    public function test_a_city_still_holding_a_location_cannot_be_deleted(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $city = CityFactory::createOne(['name' => 'Kamakura']);
        LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => $city]);

        $crawler = $this->client->request(Request::METHOD_GET, '/city/'.$city->getSlug().'/delete');

        $this->assertStringContainsString('still holds a location', $crawler->filter('main')->text(), 'The refusal is not stated.');
        $this->assertCount(0, $crawler->filter('main form'), 'A city still in use was offered for deletion anyway.');

        $this->manager()->clear();
        $this->assertNotNull(static::getContainer()->get(CityRepository::class)->find($city->getId()), 'The city was removed.');
    }

    public function test_a_city_no_location_holds_is_deleted(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $city = CityFactory::createOne(['name' => 'Kamakura']);
        $id = $city->getId();
        $slug = $city->getSlug();

        $this->client->request(Request::METHOD_GET, '/city/'.$slug.'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects('/cities');

        $this->manager()->clear();
        $this->assertNull(static::getContainer()->get(CityRepository::class)->find($id), 'The city survived.');
    }

    public function test_a_city_carries_a_main_photograph_and_a_gallery(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $city = CityFactory::createOne(['name' => 'Kamakura']);

        $this->correct($city, [
            'photo_add' => ['city' => [$this->createImage(900, 600), $this->createImage(900, 600)]],
            'photo_add_label' => ['city' => ['The great buddha', '  ']],
        ]);

        $stored = $this->stored($city->getId());
        $photos = $stored->getPhotos();

        $this->assertNotNull($stored->getPhotographFull(), 'The main photograph was not stored.');
        $this->assertCount(2, $photos, 'The gallery did not keep both photographs.');
        $this->assertSame([1, 2], $photos->map(static fn (CityPhoto $p): ?int => $p->getPosition())->toArray(), 'The gallery is not numbered from one.');
        $this->assertSame(['The great buddha', null], $photos->map(static fn (CityPhoto $p): ?string => $p->getLabel())->toArray(), 'A blank label was stored as a string.');

        $page = $this->client->request(Request::METHOD_GET, '/city/'.$city->getSlug());
        $this->assertCount(1, $page->filter('main .stage img'), 'The page does not show the main photograph.');
        $this->assertCount(2, $page->filter('main .gallery img'), 'The page does not show the gallery.');

        $this->discard($city->getId());
    }

    public function test_a_photograph_that_is_not_an_image_is_refused_without_taking_the_others_down(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $city = CityFactory::createOne(['name' => 'Kamakura']);

        $this->correct($city, [
            'photo_add' => ['city' => [$this->createTextFile(), $this->createImage(900, 600)]],
        ]);

        $this->assertCount(1, $this->stored($city->getId())->getPhotos(), 'The refused file was stored, or it took the valid one down with it.');

        $this->discard($city->getId());
    }

    /**
     * @param array<string, mixed> $photographs
     */
    private function correct(City $city, array $photographs): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/city/'.$city->getSlug().'/edit');
        $form = $crawler->selectButton('city_submit')->form();

        $added = $photographs['photo_add'] ?? [];
        unset($photographs['photo_add']);

        $values = $form->getPhpValues();
        $values['city']['photographFile'] = $this->createImage(1600, 1100);

        $this->client->request(
            Request::METHOD_POST,
            $form->getUri(),
            [...$values, ...$photographs],
            ['city' => ['photographFile' => $values['city']['photographFile']], ...($added === [] ? [] : ['photo_add' => $added])],
        );

        $this->assertResponseRedirects();
        $this->manager()->clear();
    }

    public function test_the_index_narrows_to_the_cities_one_goshuincho_names(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kyoto = CityFactory::createOne(['name' => 'Kyōto']);
        CityFactory::createOne(['name' => 'Kamakura']);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        GoshuinFactory::new()->in($goshuincho)->create(['location' => LocationFactory::createOne(['city' => $kyoto])]);

        $crawler = $this->client->request(Request::METHOD_GET, '/cities?goshuincho='.$goshuincho->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('main ul li a'), 'The list is not narrowed to the cities the goshuincho names.');
        $this->assertStringContainsString('Kyōto', $crawler->filter('main ul li a')->text());
        $this->assertCount(1, $crawler->filter('main a[href="/cities"]'), 'Nothing leads back to the whole list.');
        $this->assertCount(1, $crawler->filter('main a[href="/goshuincho/'.$goshuincho->getSlug().'"]'), 'The narrowed list does not name the goshuincho it follows.');
    }

    public function test_the_index_refuses_a_goshuincho_it_does_not_hold(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/cities?goshuincho=never-bought');

        $this->assertResponseStatusCodeSame(404, 'An unknown goshuincho was accepted as a filter.');
    }

    public function test_the_page_shows_the_goshuincho_and_the_goshuin_that_come_from_the_city(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kyoto = CityFactory::createOne(['name' => 'Kyōto']);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        GoshuinFactory::new()->in($goshuincho)->create(['location' => LocationFactory::createOne([
            'romanizedName' => 'Kiyomizu-dera',
            'city' => $kyoto,
        ])]);
        GoshuinFactory::new()->in(GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']))->create([
            'location' => LocationFactory::createOne(['romanizedName' => 'Sensō-ji', 'city' => CityFactory::createOne(['name' => 'Tōkyō'])]),
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, '/city/'.$kyoto->getSlug());

        $this->assertResponseIsSuccessful();
        $body = $crawler->filter('main')->text();
        $this->assertStringContainsString('Kansai', $body, 'The page does not name the goshuincho the city was collected in.');
        $this->assertStringNotContainsString('Kantō', $body, 'A goshuincho naming another city reached the page.');
        $this->assertCount(1, $crawler->filter('main a[href="/goshuincho/'.$goshuincho->getSlug().'/goshuin/1"]'), 'The page does not show the goshuin received in the city.');
    }

    public function test_the_page_holds_no_other_collectors_goshuin(): void
    {
        $kyoto = CityFactory::createOne(['name' => 'Kyōto']);
        $owner = UserFactory::createOne();
        GoshuinFactory::new()->in(GoshuinchoFactory::createOne(['owner' => $owner, 'title' => 'Not yours']))->create([
            'location' => LocationFactory::createOne(['romanizedName' => 'Hidden away', 'city' => $kyoto]),
        ]);
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/city/'.$kyoto->getSlug());

        $this->assertStringNotContainsString('Not yours', $crawler->filter('main')->text(), 'A foreign goshuincho reached the city page.');
        $this->assertCount(0, $crawler->filter('main a[href*="/goshuin/"]'), 'A foreign goshuin reached the city page.');
    }

    private function stored(string $id): City
    {
        return static::getContainer()->get(CityRepository::class)->find($id);
    }

    private function discard(string $id): void
    {
        $city = $this->stored($id);

        $this->removeUploads($city->getPhotograph(), $city->getPhotographMini(), $city->getPhotographCard(), $city->getPhotographFull());

        foreach ($city->getPhotos() as $photo) {
            $this->removeUploads($photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull());
        }
    }
}
