<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuincho;
use App\Entity\User;
use App\Repository\GoshuinRepository;
use App\Tests\AppTestCase;
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

    public function test_home_is_private(): void
    {
        UserFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function test_home_states_nothing_is_held_yet_and_draws_nothing_else(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('h1'), 'More than one h1 on the page.');
        $this->assertCount(1, $crawler->filter('main h2'), 'Home did not state that nothing is held yet.');
        $this->assertGreaterThan(0, $crawler->filter('main a')->count(), 'Home offered no way out.');
        $this->assertCount(0, $crawler->filter('[data-controller="map"]'), 'A map was drawn with nothing held.');
        $this->assertCount(0, $crawler->filter('main ol, main dl'), 'Home grew a list again.');
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
        $this->assertCount(0, $card->filter('li'), 'The card carries tags again.');

        $this->emptyUploads();
    }

    public function test_a_card_shows_the_cover_alone_and_no_preview_of_what_it_holds(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->fill($goshuincho, ['One', 'Two', 'Three', 'Four', 'Five', 'Six'], '2025-03-14');

        $card = $this->client->request(Request::METHOD_GET, '/')->filter('main a[href="/goshuincho/'.$goshuincho->getSlug().'"]');

        $this->assertCount(0, $card->filter('img'), 'A card without a cover still drew images, so it previews the goshuin it holds.');
        $this->assertStringNotContainsString('+', $card->text(), 'The card counts a remainder of goshuin it no longer shows.');

        $this->emptyUploads();
    }

    public function test_the_goshuincho_are_held_in_one_titled_row_that_can_be_stepped_through(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);

        foreach (['First', 'Second', 'Third'] as $title) {
            GoshuinchoFactory::createOne(['owner' => $user, 'title' => $title]);
        }

        $section = $this->client->request(Request::METHOD_GET, '/')->filter('main [data-controller="carousel"]');

        $this->assertCount(1, $section, 'The goshuincho are not held in a block of their own.');
        $this->assertSame('Goshuincho', trim($section->filter('h2')->text()), 'The block does not name what it holds.');
        $this->assertCount(3, $section->filter('a.card'), 'Not every goshuincho reached the row.');
        $this->assertCount(2, $section->filter('button'), 'The row offers no way to step left and right.');
        $this->assertStringContainsString('3 goshuincho', $section->text(), 'The block does not count what it holds.');
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
        $this->assertNotNull($bare);

        $this->emptyUploads();
    }

    public function test_a_goshuincho_holding_nothing_still_has_its_card_and_invents_no_figures(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Nothing in it yet']);

        $card = $this->client->request(Request::METHOD_GET, '/')->filter('main a[href="/goshuincho/'.$goshuincho->getSlug().'"]');

        $this->assertCount(1, $card, 'A goshuincho holding nothing lost its card.');
        $this->assertSame('Nothing in it yet', trim($card->text()), 'A count, a period or a spend was invented for a goshuincho holding nothing.');
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
        $this->assertCount(1, $crawler->filter('main h2'), 'Home did not fall back to its empty statement.');

        $this->emptyUploads();
    }

    /**
     * @param list<string> $places
     */
    private function fill(Goshuincho $goshuincho, array $places, string $day, ?string $city = null, ?string $prefecture = null): void
    {
        foreach ($places as $spot => $name) {
            $place = LocationFactory::createOne(['romanizedName' => $name, 'locality' => $city, 'prefecture' => $prefecture]);
            $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
            $this->client->submitForm('goshuin_submit', [
                'goshuin[location]' => $place->getId(),
                'goshuin[receivedOn]' => (new \DateTimeImmutable($day))->modify('+'.$spot.' days')->format('Y-m-d'),
                'goshuin[price]' => '500',
                'goshuin[imageFile]' => $this->createImage(900, 1230),
            ]);
        }
    }

    private function emptyUploads(): void
    {
        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
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

        $this->assertCount(4, $totals, 'The four totals are not stated.');

        $stated = $totals->each(static fn (Crawler $tile): string => preg_replace('/\s+/', ' ', trim($tile->text())));

        $this->assertSame(
            ['2 goshuincho', '3 goshuin', '2 cities', '2 prefectures'],
            $stated,
            'The totals do not add up to what is held, counting a place used twice only once.',
        );

        $text = implode(' ', $stated);
        $this->assertStringNotContainsString('¥', $text, 'The totals put a price forward.');

        $this->emptyUploads();
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

        $this->emptyUploads();
    }

    public function test_the_recent_panel_shows_what_exists_without_padding(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);

        $this->collect($goshuincho, LocationFactory::createOne(['romanizedName' => 'The only one']), '2025-03-14');

        $panel = $this->client->request(Request::METHOD_GET, '/')->filter('main ol li a[style*="--hue"]');

        $this->assertCount(1, $panel, 'The recent panel padded itself out to five.');

        $this->emptyUploads();
    }

    private function collect(Goshuincho $goshuincho, object $place, string $day): void
    {
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => $day,
            'goshuin[price]' => '500',
            'goshuin[imageFile]' => $this->createImage(900, 1230),
        ]);
        $this->assertResponseRedirects();
    }

    public function test_the_map_carries_a_marker_for_every_place_a_goshuin_came_from(): void
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
        $this->assertSame('cluster', $map->attr('data-map-mode-value'), 'The map is not the cluster mode of the one controller.');

        $markers = json_decode((string) $map->attr('data-map-markers-value'), true);

        $this->assertCount(2, $markers, 'The map does not carry one marker per placed location, and none for a goshuin placed nowhere.');
        $this->assertSame(['North', 'Twice over'], array_column($markers, 'label'), 'The markers do not name the places they stand on.');
        $this->assertSame(35.0, (float) $markers[0]['latitude'], 'The marker is not on the coordinates of its location.');
        $this->assertSame(135.0, (float) $markers[0]['longitude'], 'The marker is not on the coordinates of its location.');
        $this->assertNull($markers[0]['number'], 'A place visited once was given a count to display.');
        $this->assertSame(2, $markers[1]['number'], 'A place visited twice does not count both goshuin.');
        $this->assertSame(12, $markers[0]['hue'], 'The marker does not carry the colour of the goshuincho it belongs to.');
        $this->assertSame(
            '/goshuincho/'.$kansai->getSlug(),
            $markers[0]['href'],
            'A marker standing on one goshuincho does not lead to it.',
        );

        $this->assertCount(2, $map->filter('ul.sr li'), 'The marker set has no readable list.');
        $this->assertStringContainsString('Twice over', $map->filter('ul.sr')->text());
        $this->assertStringContainsString(
            '3 goshuin',
            $map->closest('section')->filter('h2 + span')->text(),
            'The map does not state how many goshuin it stands for, counting none of those placed nowhere.',
        );

        $this->emptyUploads();
    }

    public function test_a_place_two_goshuincho_share_carries_one_marker_that_claims_neither(): void
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

        $this->assertCount(1, $markers, 'One place carries two markers.');
        $this->assertSame(2, $markers[0]['number'], 'The marker does not count the goshuin of both goshuincho.');
        $this->assertNull($markers[0]['hue'], 'A place two goshuincho share took the colour of one of them.');
        $this->assertNull($markers[0]['href'], 'A place two goshuincho share leads to one of them.');

        $this->emptyUploads();
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

        $this->emptyUploads();
    }

    public function test_home_highlights_nothing_when_a_card_or_a_marker_is_pointed_at(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $this->collect($goshuincho, LocationFactory::createOne(['romanizedName' => 'Placed', 'latitude' => 34.9, 'longitude' => 135.7]), '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertCount(0, $crawler->filter('[data-controller~="linked"]'), 'Home still ties its cards and markers together.');
        $this->assertNull($crawler->filter('main a.card')->attr('data-index'), 'A card still carries the index a highlight would need.');

        $this->emptyUploads();
    }
}
