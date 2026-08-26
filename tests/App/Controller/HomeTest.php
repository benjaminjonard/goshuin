<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\City;
use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Entity\Prefecture;
use App\Repository\UserRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\CityFactory;
use App\Tests\Factory\GoshuinFactory;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\PrefectureFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HomeTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @var array<string, int>
     */
    private array $spots = [];

    public function test_home_is_private(): void
    {
        UserFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function test_home_states_nothing_is_held_yet(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('main h2'), 'Home did not state that nothing is held yet.');
    }

    public function test_the_account_menu_leads_to_the_locations_and_the_deities(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $panel = $this->client->request(Request::METHOD_GET, '/')->filter('header [data-menu-target="panel"]');

        $this->assertCount(1, $panel->filter('a[href="/locations"]'), 'The account menu does not lead to the locations.');
        $this->assertCount(1, $panel->filter('a[href="/deities"]'), 'The account menu does not lead to the deities.');
    }

    public function test_an_authenticated_page_is_never_cached(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/');

        $cacheControl = $this->client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function test_a_card_names_its_goshuincho_and_states_nothing_more(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Kansai, spring 2025',
            'purchasedAt' => new \DateTimeImmutable('2025-03-12'),
            'boughtAt' => LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']),
        ]);
        $kyoto = PrefectureFactory::createOne(['name' => 'Kyōto']);
        $this->fill($goshuincho, ['Fushimi Inari-taisha', 'Kiyomizu-dera'], '2025-03-14', CityFactory::createOne(['name' => 'Kyōto', 'prefecture' => $kyoto]), $kyoto);

        $card = $this->client->request(Request::METHOD_GET, '/')->filter('main a[href="/goshuincho/'.$goshuincho->getSlug().'"]');

        $this->assertCount(1, $card, 'The goshuincho has no card, or more than one.');
        $this->assertSame('Kansai, spring 2025', trim($card->text()), 'The card states more than the name of its goshuincho.');

    }

    public function test_the_cards_are_ordered_by_period_and_fall_back_to_the_purchase_date(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);

        $recent = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Recent goshuin']);
        $older = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Older goshuin']);
        $bought = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Bought between them',
            'purchasedAt' => new \DateTimeImmutable('2025-01-20'),
        ]);
        $bare = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Nothing at all']);

        $this->fill($older, ['Older place'], '2024-06-01');
        $this->fill($recent, ['Recent place'], '2025-06-01');

        $titles = $this->client->request(Request::METHOD_GET, '/')
            ->filter('main h3')
            ->each(static fn (Crawler $heading): string => trim($heading->text()))
        ;

        $this->assertSame(
            ['Recent goshuin', 'Bought between them', 'Older goshuin', 'Nothing at all'],
            $titles,
            'The cards are not ordered by period, with the purchase date standing in and nothing at all last.',
        );

    }

    public function test_a_goshuincho_holding_nothing_still_has_its_card_and_invents_no_figures(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Nothing in it yet']);

        $card = $this->client->request(Request::METHOD_GET, '/')->filter('main a[href="/goshuincho/'.$goshuincho->getSlug().'"]');

        $this->assertCount(1, $card, 'A goshuincho holding nothing lost its card.');
        $this->assertSame('Nothing in it yet', trim($card->text()), 'A count or a period was invented for a goshuincho holding nothing.');
    }

    public function test_another_collector_sees_none_of_it(): void
    {
        $owner = UserFactory::createOne();
        $this->client->loginUser($owner);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $owner, 'title' => 'Not yours']);
        $this->fill($goshuincho, ['Somewhere'], '2025-03-14');

        $this->client->loginUser(UserFactory::createOne());
        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertStringNotContainsString('Not yours', $crawler->filter('body')->text(), 'A foreign goshuincho reached Home.');

    }

    /**
     * @param list<string> $places
     */
    private function fill(Goshuincho $goshuincho, array $places, string $day, ?City $city = null, ?Prefecture $prefecture = null): void
    {
        foreach ($places as $spot => $name) {
            $this->collect(
                $goshuincho,
                LocationFactory::createOne(['romanizedName' => $name, 'city' => $city, 'prefecture' => $prefecture]),
                new \DateTimeImmutable($day)->modify('+'.$spot.' days')->format('Y-m-d'),
            );
        }
    }

    public function test_the_totals_state_what_is_held_altogether(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $kanto = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']);
        $kyoto = PrefectureFactory::createOne(['name' => 'Kyōto']);
        $shared = LocationFactory::createOne([
            'romanizedName' => 'Twice over',
            'city' => CityFactory::createOne(['name' => 'Kyōto', 'prefecture' => $kyoto]),
            'prefecture' => $kyoto,
        ]);

        $this->collect($kansai, $shared, '2025-03-14');
        $this->collect($kansai, $shared, '2025-03-15');
        $this->collect($kanto, LocationFactory::createOne([
            'romanizedName' => 'Elsewhere',
            'city' => CityFactory::createOne(['name' => 'Kamakura']),
            'prefecture' => PrefectureFactory::createOne(['name' => 'Kanagawa']),
        ]), '2025-04-01');

        $totals = $this->client->request(Request::METHOD_GET, '/')->filter('main .tile.flex');

        $stated = $totals->each(static fn (Crawler $tile): string => preg_replace('/\s+/', ' ', trim($tile->text())));

        $this->assertSame(
            ['2 goshuincho', '3 goshuin', '2 cities', '2 prefectures'],
            $stated,
            'The totals do not add up to what is held, counting a place used twice only once.',
        );

    }

    public function test_a_total_of_one_reads_in_the_singular(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $kyoto = PrefectureFactory::createOne(['name' => 'Kyōto']);

        $this->collect($goshuincho, LocationFactory::createOne([
            'romanizedName' => 'Kiyomizu-dera',
            'city' => CityFactory::createOne(['name' => 'Kyōto', 'prefecture' => $kyoto]),
            'prefecture' => $kyoto,
        ]), '2025-03-14');

        $totals = $this->client->request(Request::METHOD_GET, '/')->filter('main .tile.flex');

        $stated = $totals->each(static fn (Crawler $tile): string => preg_replace('/\s+/', ' ', trim($tile->text())));

        $this->assertSame(
            ['1 goshuincho', '1 goshuin', '1 city', '1 prefecture'],
            $stated,
            'A total of one did not read in the singular, or a label with one form grew a plural.',
        );
    }

    public function test_each_total_leads_to_the_list_it_counts(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $this->collect($goshuincho, LocationFactory::createOne([
            'romanizedName' => 'Somewhere',
            'city' => CityFactory::createOne(['name' => 'Kyōto']),
            'prefecture' => PrefectureFactory::createOne(['name' => 'Kyōto']),
        ]), '2025-03-14');

        $links = $this->client->request(Request::METHOD_GET, '/')
            ->filter('main a.tile')
            ->each(static fn (Crawler $tile): string => $tile->attr('href'))
        ;

        $this->assertSame(['/goshuincho', '/goshuin', '/cities', '/prefectures'], $links, 'The totals do not lead to the lists they count.');
    }

    public function test_japanese_totals_read_the_same_at_every_count(): void
    {
        UserFactory::createOne(['email' => 'user@example.com', 'locale' => 'ja']);

        $login = $this->client->request(Request::METHOD_GET, '/login');
        $this->client->submit($login->filter('form')->form(), [
            '_username' => 'user@example.com',
            '_password' => 'a-long-enough-password',
        ]);
        $this->client->followRedirect();

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user@example.com']);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $kyoto = PrefectureFactory::createOne(['name' => 'Kyōto']);

        $this->collect($kansai, LocationFactory::createOne([
            'romanizedName' => 'Kiyomizu-dera',
            'city' => CityFactory::createOne(['name' => 'Kyōto', 'prefecture' => $kyoto]),
            'prefecture' => $kyoto,
        ]), '2025-03-14');

        $this->assertSame(
            ['1 御朱印帳', '1 御朱印', '1 市区町村', '1 都道府県'],
            $this->tiles(),
            'The Japanese totals are not served.',
        );

        $kanto = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']);
        $kanagawa = PrefectureFactory::createOne(['name' => 'Kanagawa']);

        $this->collect($kanto, LocationFactory::createOne([
            'romanizedName' => 'Tsurugaoka Hachimangū',
            'city' => CityFactory::createOne(['name' => 'Kamakura', 'prefecture' => $kanagawa]),
            'prefecture' => $kanagawa,
        ]), '2025-04-01');

        $this->assertSame(
            ['2 御朱印帳', '2 御朱印', '2 市区町村', '2 都道府県'],
            $this->tiles(),
            'A Japanese label changed form with its count.',
        );
    }

    /**
     * @return list<string>
     */
    private function tiles(): array
    {
        return $this->client->request(Request::METHOD_GET, '/')
            ->filter('main .tile.flex')
            ->each(static fn (Crawler $tile): string => preg_replace('/\s+/', ' ', trim($tile->text())));
    }

    private function collect(Goshuincho $goshuincho, Location $place, string $day): void
    {
        $id = $goshuincho->getId();
        $this->spots[$id] = ($this->spots[$id] ?? 0) + 1;

        GoshuinFactory::new()->in($goshuincho, $this->spots[$id])->create([
            'location' => $place,
            'receivedOn' => new \DateTimeImmutable($day),
            'price' => 500,
        ]);
    }

    public function test_the_map_carries_a_marker_for_every_placed_goshuin(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai', 'hue' => 12]);
        $nowhere = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Nowhere placed']);
        $twice = LocationFactory::createOne(['romanizedName' => 'Twice over', 'latitude' => 34.0, 'longitude' => 136.0]);

        $this->collect($kansai, LocationFactory::createOne(['romanizedName' => 'North', 'latitude' => 35.0, 'longitude' => 135.0]), '2025-03-14');
        $this->collect($kansai, $twice, '2025-03-15');
        $this->collect($kansai, $twice, '2025-03-16');
        $this->collect($nowhere, LocationFactory::createOne(['romanizedName' => 'Unplaced']), '2025-03-17');

        $map = $this->client->request(Request::METHOD_GET, '/')->filter('main [data-controller="map"]');

        $this->assertCount(1, $map, 'The places are not put on a map.');
        $this->assertSame('numbered', $map->attr('data-map-mode-value'), 'The map is not the numbered mode of the one controller.');

        $markers = json_decode((string) $map->attr('data-map-markers-value'), true);

        $this->assertCount(3, $markers, 'The map does not carry one marker per placed goshuin, and none for a goshuin placed nowhere.');
        $this->assertSame(['North', 'Twice over', 'Twice over'], array_column($markers, 'label'), 'The markers do not name the places they stand on.');
        $this->assertSame(35.0, (float) $markers[0]['latitude'], 'The marker is not on the coordinates of its location.');
        $this->assertSame(135.0, (float) $markers[0]['longitude'], 'The marker is not on the coordinates of its location.');
        $this->assertSame([1, 2, 3], array_column($markers, 'number'), 'The markers are not numbered after the page each goshuin sits on.');
        $this->assertSame(12, $markers[0]['hue'], 'The marker does not carry the colour of the goshuincho it belongs to.');
        $this->assertSame(
            '/goshuincho/'.$kansai->getSlug().'/goshuin/1',
            $markers[0]['href'],
            'The marker does not lead to the goshuin it stands for.',
        );

        $this->assertCount(3, $map->filter('ul.sr li'), 'The marker set has no readable list.');
        $this->assertStringContainsString('Twice over', $map->filter('ul.sr')->text());
        $this->assertStringContainsString(
            '3 goshuin',
            $map->closest('section')->filter('h2 + span')->text(),
            'The map does not state how many goshuin it stands for, counting none of those placed nowhere.',
        );

    }

    public function test_a_place_two_goshuincho_share_carries_a_marker_for_each(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai', 'hue' => 12]);
        $kanto = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō', 'hue' => 210]);
        $shared = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'latitude' => 34.9, 'longitude' => 135.7]);

        $this->collect($kansai, $shared, '2025-03-14');
        $this->collect($kanto, $shared, '2025-04-01');

        $map = $this->client->request(Request::METHOD_GET, '/')->filter('main [data-controller="map"]');
        $markers = json_decode((string) $map->attr('data-map-markers-value'), true);

        $this->assertCount(2, $markers, 'A place two goshuincho share does not carry a marker for each goshuin.');
        $this->assertSame([12, 210], array_column($markers, 'hue'), 'The markers do not each take the colour of their own goshuincho.');
        $this->assertSame(
            ['/goshuincho/'.$kansai->getSlug().'/goshuin/1', '/goshuincho/'.$kanto->getSlug().'/goshuin/1'],
            array_column($markers, 'href'),
            'The markers do not each lead to their own goshuin.',
        );

    }

    public function test_goshuin_placed_nowhere_draw_no_map(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);

        $this->collect($goshuincho, LocationFactory::createOne(['romanizedName' => 'Unplaced']), '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful('Goshuin with no coordinates broke Home.');
        $this->assertCount(0, $crawler->filter('main [data-controller="map"]'), 'A map was drawn with nothing to point at.');
    }
}
