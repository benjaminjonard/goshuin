<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinFactory;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
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
        $this->fill($goshuincho, ['Fushimi Inari-taisha', 'Kiyomizu-dera'], '2025-03-14', 'Kyōto', 'Kyōto');

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
    private function fill(Goshuincho $goshuincho, array $places, string $day, ?string $city = null, ?string $prefecture = null): void
    {
        foreach ($places as $spot => $name) {
            $this->collect(
                $goshuincho,
                LocationFactory::createOne(['romanizedName' => $name, 'locality' => $city, 'prefecture' => $prefecture]),
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
        $shared = LocationFactory::createOne(['romanizedName' => 'Twice over', 'locality' => 'Kyōto', 'prefecture' => 'Kyōto']);

        $this->collect($kansai, $shared, '2025-03-14');
        $this->collect($kansai, $shared, '2025-03-15');
        $this->collect($kanto, LocationFactory::createOne(['romanizedName' => 'Elsewhere', 'locality' => 'Kamakura', 'prefecture' => 'Kanagawa']), '2025-04-01');

        $totals = $this->client->request(Request::METHOD_GET, '/')->filter('main .tile.flex');

        $stated = $totals->each(static fn (Crawler $tile): string => preg_replace('/\s+/', ' ', trim($tile->text())));

        $this->assertSame(
            ['2 goshuincho', '3 goshuin', '2 cities', '2 prefectures'],
            $stated,
            'The totals do not add up to what is held, counting a place used twice only once.',
        );

    }

    public function test_the_recent_panel_lists_the_five_latest_and_says_where_each_belongs(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai, spring 2025']);

        foreach (['2025-03-10', '2025-03-11', '2025-03-12', '2025-03-13', '2025-03-14', '2025-03-15'] as $spot => $day) {
            $this->collect($goshuincho, LocationFactory::createOne(['romanizedName' => 'Place '.$spot]), $day);
        }

        $panel = $this->client->request(Request::METHOD_GET, '/')->filter('main ol li a[style*="--hue"]');

        $this->assertCount(5, $panel, 'The recent panel does not list five goshuin.');
        $this->assertSame(
            ['Place 5', 'Place 4', 'Place 3', 'Place 2', 'Place 1'],
            $panel->each(static fn (Crawler $row): string => trim($row->filter('span span')->first()->text())),
            'The recent panel is not ordered by the date received, most recent first.',
        );
        $this->assertStringContainsString('Kansai, spring 2025', $panel->first()->text(), 'A recent goshuin does not name the goshuincho it belongs to.');
        $this->assertStringContainsString('March 15, 2025', $panel->first()->text(), 'A recent goshuin does not state its date.');

    }

    public function test_the_recent_panel_shows_what_exists_without_padding(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);

        $this->collect($goshuincho, LocationFactory::createOne(['romanizedName' => 'The only one']), '2025-03-14');

        $panel = $this->client->request(Request::METHOD_GET, '/')->filter('main ol li a[style*="--hue"]');

        $this->assertCount(1, $panel, 'The recent panel padded itself out to five.');

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
