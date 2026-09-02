<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Repository\UserRepository;
use App\Service\Municipality;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinFactory;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StatsTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @var array<string, int>
     */
    private array $spots = [];

    public function test_statistics_are_private(): void
    {
        UserFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function test_the_account_menu_leads_to_the_statistics(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $panel = $this->client->request(Request::METHOD_GET, '/')->filter('header [data-menu-target="panel"]');

        $this->assertCount(1, $panel->filter('a[href="/stats"]'), 'The account menu does not lead to the statistics.');
    }

    public function test_statistics_state_nothing_is_held_yet(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('main h2'), 'The statistics did not state that nothing is held yet.');
        $this->assertCount(0, $crawler->filter('main [data-controller="map"]'), 'A map was drawn for an empty collection.');
        $this->assertCount(0, $crawler->filter('main [data-distribution]'), 'A breakdown was drawn for an empty collection.');
    }

    public function test_a_located_goshuin_highlights_its_municipality_and_its_prefecture(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');

        $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(['26'], $this->codes(0), 'The prefecture map was not given the prefecture that was visited.');
        $this->assertSame(['26100'], $this->codes(1), 'The municipality map was not given the municipality that was visited.');
    }

    public function test_a_goshuin_without_coordinates_falls_off_both_maps_and_into_the_unlocated_count(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kanto = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']);

        $this->collect($kanto, $this->place('Sensō-ji', 35.714765, 139.796655), '2025-03-14');
        $this->collect($kanto, LocationFactory::createOne(['romanizedName' => 'Nowhere in particular']), '2025-03-15');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(['13'], $this->codes(0), 'A goshuin not on the map reached the prefecture map.');
        $this->assertSame(['13106'], $this->codes(1), 'A goshuin not on the map reached the municipality map.');
        $this->assertStringContainsString(
            '1 goshuin not on the map',
            $this->text($crawler),
            'The goshuin not on the map is not counted anywhere.',
        );
    }

    public function test_a_location_outside_japan_is_counted_as_unlocated(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $abroad = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Abroad']);

        $this->collect($abroad, $this->place('Notre-Dame', 48.852968, 2.349902), '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame([], $this->codes(1), 'A location outside Japan matched a municipality.');
        $this->assertStringContainsString(
            '1 goshuin not on the map',
            $this->text($crawler),
            'A location outside Japan is not counted as unlocated.',
        );
    }

    public function test_a_goshuin_without_a_date_falls_out_of_the_breakdown_and_into_the_undated_count(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');
        $this->collect($kansai, $this->place('Itsukushima', 34.295852, 132.319740), null);

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame([1], $this->counts($crawler, 'year'), 'A goshuin without a date reached the year breakdown.');
        $this->assertSame(1, array_sum($this->counts($crawler, 'month')), 'A goshuin without a date reached the month breakdown.');
        $this->assertStringContainsString(
            '1 goshuin without a date',
            $this->text($crawler),
            'The goshuin without a date is not counted anywhere.',
        );
    }

    public function test_the_month_gathers_what_the_year_keeps_apart(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2024-03-14');
        $this->collect($kansai, $this->place('Sensō-ji', 35.714765, 139.796655), '2026-03-16');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame([1, 0, 1], $this->counts($crawler, 'year'), 'The year breakdown did not split two Marches across their years, gap included.');
        $this->assertSame(['2024', '2025', '2026'], $this->labels($crawler, 'year'), 'The year breakdown is not chronological, or drops the year nothing happened in.');
        $this->assertSame([0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0], $this->counts($crawler, 'month'), 'The month breakdown did not gather two Marches under March.');
    }

    public function test_every_month_and_every_day_of_the_week_is_drawn(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertCount(12, $this->counts($crawler, 'month'), 'The month breakdown drops the months at zero.');
        $this->assertCount(7, $this->counts($crawler, 'weekday'), 'The day breakdown drops the days at zero.');
        $this->assertSame([0, 0, 0, 0, 1, 0, 0], $this->counts($crawler, 'weekday'), 'A Friday was not read as a Friday.');
    }

    public function test_a_municipality_holding_many_goshuin_is_highlighted_once(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $kiyomizu = $this->place('Kiyomizu-dera', 34.994856, 135.784997);

        foreach (['2025-03-14', '2025-03-15', '2025-03-16'] as $day) {
            $this->collect($kansai, $kiyomizu, $day);
        }

        $this->collect($kansai, $this->place('Fushimi Inari-taisha', 34.967140, 135.772673), '2025-03-17');
        $this->collect($kansai, $this->place('Sensō-ji', 35.714765, 139.796655), '2025-03-18');

        $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(['13', '26'], $this->codes(0), 'A prefecture was carried more than once.');
        $this->assertSame(['13106', '26100'], $this->codes(1), 'A municipality was carried more than once.');
    }

    public function test_another_collector_changes_no_count(): void
    {
        $owner = UserFactory::createOne();
        $this->client->loginUser($owner);
        $theirs = GoshuinchoFactory::createOne(['owner' => $owner, 'title' => 'Not yours']);
        $this->collect($theirs, $this->place('Sensō-ji', 35.714765, 139.796655), '2025-03-14');

        $mine = UserFactory::createOne();
        $this->client->loginUser($mine);
        $ours = GoshuinchoFactory::createOne(['owner' => $mine, 'title' => 'Mine']);
        $this->collect($ours, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(['26'], $this->codes(0), "Another collector's prefecture reached the map.");
        $this->assertSame(['26100'], $this->codes(1), "Another collector's municipality reached the map.");
        $this->assertSame([1], $this->counts($crawler, 'year'), "Another collector's goshuin reached the breakdown.");
    }

    public function test_editing_the_coordinates_moves_the_code(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $place = $this->place('Somewhere', 34.994856, 135.784997);

        $this->assertSame('26100', $place->getMunicipalityCode(), 'The code was not derived when the location was created.');

        $manager = $this->manager();
        $moved = $manager->find(Location::class, $place->getId());
        $moved->setLatitude(35.714765);
        $moved->setLongitude(139.796655);
        $manager->flush();

        $this->assertSame('13106', $this->manager()->find(Location::class, $place->getId())->getMunicipalityCode(), 'The code did not follow the coordinates.');
    }

    public function test_a_missing_boundary_file_leaves_the_code_null_and_still_saves(): void
    {
        static::getContainer()->set(Municipality::class, new Municipality(static::getContainer()->getParameter('kernel.project_dir').'/var/no-such-place'));

        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $place = $this->place('Kiyomizu-dera', 34.994856, 135.784997);
        $this->collect($kansai, $place, '2025-03-14');

        $this->assertNull(
            $this->manager()->find(Location::class, $place->getId())->getMunicipalityCode(),
            'A location was coded although the boundary file could not be read.',
        );

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            '1 goshuin not on the map',
            $this->text($crawler),
            'A goshuin the boundary file could not place is not counted as unlocated.',
        );
    }

    public function test_the_page_is_read_in_japanese(): void
    {
        UserFactory::createOne(['email' => 'ja@example.com', 'locale' => 'ja']);

        $login = $this->client->request(Request::METHOD_GET, '/login');
        $this->client->submit($login->filter('form')->form(), [
            '_username' => 'ja@example.com',
            '_password' => 'a-long-enough-password',
        ]);
        $this->client->followRedirect();

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'ja@example.com']);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');
        $this->collect($kansai, LocationFactory::createOne(['romanizedName' => 'Nowhere in particular']), '2025-03-15');
        $this->collect($kansai, $this->place('Sensō-ji', 35.714765, 139.796655), null);

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');
        $read = $this->text($crawler);

        $this->assertStringContainsString('統計', $crawler->filter('header h1')->text(), 'The Japanese page is not titled in Japanese.');

        foreach (['訪問状況', '都道府県', '市区町村', '時期別', '年別', '月別', '曜日別', '3月', '金'] as $label) {
            $this->assertStringContainsString($label, $read, 'The Japanese page does not carry '.$label.'.');
        }

        $this->assertStringContainsString('地図にない御朱印1件', $read, 'The unlocated line is not read in Japanese.');
        $this->assertStringContainsString('日付のない御朱印1件', $read, 'The undated line is not read in Japanese.');
    }

    public function test_the_map_credits_the_boundary_data(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');

        $map = $this->client->request(Request::METHOD_GET, '/stats')->filter('main [data-map-mode-value="regions"]');

        $this->assertCount(1, $map, 'The coverage section does not hold exactly one map.');

        $credit = (string) $map->attr('data-map-attribution-value');

        $this->assertStringContainsString('OpenStreetMap', $credit, 'The map dropped the OpenStreetMap credit.');
        $this->assertStringContainsString('国土数値情報（行政区域データ）国土交通省', $credit, 'The map dropped the credit the boundary data requires.');
    }

    public function test_one_map_carries_both_layers_and_offers_a_toggle(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');
        $this->collect($kansai, $this->place('Sensō-ji', 35.714765, 139.796655), '2025-03-15');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');
        $switches = $crawler->filter('main [data-map-target="switch"]');

        $this->assertCount(2, $switches, 'The map does not offer a switch per layer.');
        $this->assertSame(
            ['Prefectures', 'Cities'],
            $switches->each(static fn (Crawler $button): string => trim($button->text())),
            'The toggle does not name the two levels, prefectures first.',
        );
        $this->assertSame(
            ['0', '1'],
            $switches->each(static fn (Crawler $button): string => (string) $button->attr('data-map-level-param')),
            'A switch does not name the layer it draws.',
        );

        $levels = $this->levels();

        $this->assertStringContainsString('/geo/prefectures.topo.json?v=', $levels[0]['url'], 'The first layer is not the prefectures, or is served unversioned.');
        $this->assertStringContainsString('/geo/municipalities.topo.json?v=', $levels[1]['url'], 'The second layer is not the municipalities, or is served unversioned.');
        $this->assertSame(['13', '26'], $this->codes(0), 'The prefecture layer was not given the prefectures that were visited.');
        $this->assertSame(['13106', '26100'], $this->codes(1), 'The municipality layer was not given the municipalities that were visited.');
        $this->assertSame('2', $levels[0]['count'], 'The prefecture layer carries no count for the toggle to show.');
        $this->assertSame('2', $levels[1]['count'], 'The municipality layer carries no count for the toggle to show.');
        $this->assertFalse($levels[0]['frames'], 'The opening view is computed from prefecture geometry, which reaches islands nobody visited.');
        $this->assertTrue($levels[1]['frames'], 'The municipality layer is not named as the one the opening view is framed on.');
    }

    public function test_the_prefecture_layer_shows_first(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(
            ['true', 'false'],
            $crawler->filter('main [data-map-target="switch"]')->each(static fn (Crawler $button): string => (string) $button->attr('aria-pressed')),
            'The page does not open on the prefecture layer.',
        );
        $this->assertSame(
            '1',
            trim($crawler->filter('main [data-map-target="held"]')->text()),
            'The count beside the toggle does not follow the layer showing.',
        );
    }

    public function test_a_single_visited_unit_is_the_only_one_the_map_is_given_to_frame_on(): void
    {
        $this->openStats();

        $this->assertSame(['26'], $this->codes(0), 'The prefecture layer was given more than the one unit that was visited.');
        $this->assertSame(['26100'], $this->codes(1), 'The municipality layer was given more than the one unit that was visited.');
    }

    public function test_the_opening_view_is_framed_on_municipalities_even_when_prefectures_are_shown(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kanto = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']);
        $this->collect($kanto, $this->place('Sensō-ji', 35.714765, 139.796655), '2025-03-14');

        $this->client->request(Request::METHOD_GET, '/stats');

        $levels = $this->levels();

        $this->assertSame(['13'], $this->codes(0), 'Tokyo is not among the visited prefectures.');
        $this->assertSame(['13106'], $this->codes(1), 'Taitō is not among the visited municipalities.');
        $this->assertTrue($levels[1]['frames'], 'The framing layer is not the municipality one.');
        $this->assertNotSame([], $levels[1]['zones'], 'The framing layer carries no zone to frame on.');
    }

    public function test_a_collection_with_nothing_located_gives_the_map_no_codes_to_frame_on(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $this->collect($kansai, LocationFactory::createOne(['romanizedName' => 'Nowhere in particular']), '2025-03-14');
        $this->collect($kansai, LocationFactory::createOne(['romanizedName' => 'Nowhere either']), '2025-03-15');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertResponseIsSuccessful();

        $levels = $this->levels();

        $this->assertSame([], $levels[0]['zones'], 'The prefecture layer was given a zone by an unlocated collection.');
        $this->assertSame([], $levels[1]['zones'], 'The municipality layer was given a zone by an unlocated collection.');
        $this->assertSame('0', $levels[0]['count'], 'The count beside the toggle does not read zero.');
        $this->assertSame(
            '0',
            trim($crawler->filter('main [data-map-target="held"]')->text()),
            'The rendered count does not read zero.',
        );
        $this->assertStringContainsString(
            '2 goshuin not on the map',
            $this->text($crawler),
            'An unlocated collection is not counted.',
        );
    }

    public function test_with_no_filter_both_populations_are_counted_everywhere(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Kansai',
            'boughtAt' => $this->place('Kiyomizu-dera', 34.994856, 135.784997),
            'purchasedAt' => new \DateTimeImmutable('2025-03-14'),
        ]);
        $this->collect($kansai, $this->place('Fushimi Inari-taisha', 34.967140, 135.772673), '2025-03-14');
        $this->collect($kansai, $this->place('Kōdai-ji', 34.999722, 135.780556), '2025-03-14');
        $this->collect($kansai, $this->place('Sensō-ji', 35.714765, 139.796655), '2025-03-15');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(
            ['3 goshuin', '1 goshuincho', '2 prefectures', '2 cities'],
            $this->tiles($crawler),
            'The tiles do not count both populations, or add them up.',
        );
        $this->assertSame(['13', '26'], $this->codes(0), 'The prefecture layer does not light on either population.');
        $this->assertSame('2 goshuin · 1 goshuincho', $this->held(0, '26'), 'A zone holding both does not state a pair.');
        $this->assertSame('1 goshuin', $this->held(0, '13'), 'A zone holding one population states the other at zero.');
        $this->assertSame([0, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0], $this->counts($crawler, 'month'), 'The month distribution lost its goshuin series.');
        $this->assertSame([0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0], $this->counts($crawler, 'month', 'goshuincho'), 'The month distribution lost its goshuincho series.');
    }

    public function test_the_filter_narrows_the_whole_page_to_goshuin(): void
    {
        $this->both();

        $crawler = $this->client->request(Request::METHOD_GET, '/stats?show=goshuin');

        $this->assertSame(['2 goshuin', '1 prefecture', '1 city'], $this->tiles($crawler), 'The tiles still count goshuincho.');
        $this->assertSame(['13'], $this->codes(0), 'A zone lit for a goshuincho alone survived the goshuin filter.');
        $this->assertSame('1 goshuin', $this->held(0, '13'), 'A zone counter still states goshuincho.');
        $this->assertSame([], $this->counts($crawler, 'month', 'goshuincho'), 'The goshuincho series survived the goshuin filter.');
        $this->assertSame(
            ['1 goshuin not on the map'],
            $this->missing($crawler),
            'The missing lines do not follow the filter.',
        );
    }

    public function test_the_filter_narrows_the_whole_page_to_goshuincho(): void
    {
        $this->both();

        $crawler = $this->client->request(Request::METHOD_GET, '/stats?show=goshuincho');

        $this->assertSame(['1 goshuincho', '1 prefecture', '1 city'], $this->tiles($crawler), 'The tiles still count goshuin.');
        $this->assertSame(['26'], $this->codes(0), 'A zone lit for a goshuin alone survived the goshuincho filter.');
        $this->assertSame('1 goshuincho', $this->held(0, '26'), 'A zone counter still states goshuin.');
        $this->assertSame([], $this->counts($crawler, 'month'), 'The goshuin series survived the goshuincho filter.');
        $this->assertSame(
            ['1 goshuincho without a date'],
            $this->missing($crawler),
            'The missing lines do not follow the filter.',
        );
    }

    public function test_a_filter_nobody_offers_falls_back_to_both(): void
    {
        $this->both();

        $crawler = $this->client->request(Request::METHOD_GET, '/stats?show=nonsense');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['2 goshuin', '1 goshuincho', '2 prefectures', '2 cities'], $this->tiles($crawler), 'An unrecognised filter did not fall back to both.');
        $this->assertSame(['13', '26'], $this->codes(0), 'An unrecognised filter narrowed the map.');
    }

    public function test_a_filter_that_is_not_even_a_string_falls_back_to_both(): void
    {
        $this->both();

        $crawler = $this->client->request(Request::METHOD_GET, '/stats?show[]=goshuin');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['2 goshuin', '1 goshuincho', '2 prefectures', '2 cities'], $this->tiles($crawler), 'An array filter did not fall back to both.');
    }

    public function test_the_empty_state_names_the_population_the_filter_asked_for(): void
    {
        $holder = UserFactory::createOne();
        $bare = UserFactory::createOne();

        $this->client->loginUser($holder);
        GoshuinchoFactory::createOne(['owner' => $holder, 'title' => 'Empty so far']);

        $this->assertSame(
            'No goshuin yet.',
            $this->stated('?show=goshuin'),
            'The empty state names the wrong population under the goshuin filter.',
        );

        $this->client->loginUser($bare);

        $this->assertSame(
            'No goshuincho yet.',
            $this->stated('?show=goshuincho'),
            'The empty state names the wrong population under the goshuincho filter.',
        );
        $this->assertSame(
            'Nothing to count yet.',
            $this->stated(''),
            'The unfiltered empty state names one population.',
        );
    }

    private function stated(string $query): string
    {
        return trim($this->client->request(Request::METHOD_GET, '/stats'.$query)->filter('main h2')->text());
    }

    public function test_a_location_losing_its_coordinates_loses_its_code_and_its_zone(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $place = $this->place('Kiyomizu-dera', 34.994856, 135.784997);
        $this->collect($kansai, $place, '2025-03-14');

        $this->client->request(Request::METHOD_GET, '/stats');
        $this->assertSame(['26100'], $this->codes(1), 'The zone was never lit.');

        $manager = $this->manager();
        $cleared = $manager->find(Location::class, $place->getId());
        $cleared->setLatitude(null);
        $cleared->setLongitude(null);
        $manager->flush();

        $this->assertNull(
            $this->manager()->find(Location::class, $place->getId())->getMunicipalityCode(),
            'A location that lost its coordinates kept its code.',
        );

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame([], $this->codes(1), 'A stale zone stayed lit after the coordinates were cleared.');
        $this->assertContains('1 goshuin not on the map', $this->missing($crawler), 'The goshuin did not fall into the missing count.');
    }

    public function test_a_location_moved_abroad_loses_its_code_and_its_zone(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $place = $this->place('Kiyomizu-dera', 34.994856, 135.784997);
        $this->collect($kansai, $place, '2025-03-14');

        $manager = $this->manager();
        $moved = $manager->find(Location::class, $place->getId());
        $moved->setLatitude(48.852968);
        $moved->setLongitude(2.349902);
        $manager->flush();

        $this->assertNull(
            $this->manager()->find(Location::class, $place->getId())->getMunicipalityCode(),
            'A location moved outside Japan kept its code.',
        );

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame([], $this->codes(1), 'A stale zone stayed lit after the location moved abroad.');
        $this->assertContains('1 goshuin not on the map', $this->missing($crawler), 'The goshuin did not fall into the missing count.');
    }

    public function test_the_filter_pills_mark_the_state_the_page_is_in(): void
    {
        $this->both();

        foreach (['' => 0, '?show=goshuin' => 1, '?show=goshuincho' => 2] as $query => $lit) {
            $pills = $this->client->request(Request::METHOD_GET, '/stats'.$query)->filter('main > ul > li');

            $this->assertSame(
                ['All', 'Goshuin', 'Goshuincho'],
                $pills->each(static fn (Crawler $pill): string => trim($pill->text())),
                'The page does not offer the three filter pills.',
            );

            $hot = $pills->each(static fn (Crawler $pill): bool => str_contains((string) $pill->attr('class'), 'bg-accent-soft'));

            $this->assertSame($lit, array_search(true, $hot, true), 'The lit pill does not match the filter in force.');
            $this->assertSame(1, array_sum(array_map(intval(...), $hot)), 'More than one filter pill is lit.');

            $current = $pills->each(static fn (Crawler $pill): ?string => $pill->filter('a')->attr('aria-current'));

            $this->assertSame($lit, array_search('page', $current, true), 'The filter in force is not marked aria-current.');
            $this->assertSame([null, null], array_values(array_filter($current, static fn (?string $mark): bool => $mark !== 'page')), 'More than one pill is marked aria-current.');
        }
    }

    public function test_a_goshuincho_bought_nowhere_lands_in_the_unlocated_goshuincho_line(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Bought somewhere unplaced',
            'boughtAt' => LocationFactory::createOne(['romanizedName' => 'Nowhere in particular']),
            'purchasedAt' => new \DateTimeImmutable('2025-03-14'),
        ]);
        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(['26'], $this->codes(0), 'A goshuincho with nowhere to sit reached the map.');
        $this->assertContains(
            '2 goshuincho not on the map',
            $this->missing($crawler),
            'A null boughtAt and a boughtAt without coordinates do not land in the same count.',
        );
    }

    public function test_a_goshuincho_with_no_purchase_date_lands_in_the_undated_goshuincho_line(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Kansai',
            'boughtAt' => $this->place('Kiyomizu-dera', 34.994856, 135.784997),
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame([], $this->counts($crawler, 'year', 'goshuincho'), 'An undated goshuincho reached the distributions.');
        $this->assertContains('1 goshuincho without a date', $this->missing($crawler), 'The undated goshuincho is not counted.');
    }

    public function test_a_filter_that_leaves_nothing_states_nothing_is_held_yet(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Empty so far']);

        $crawler = $this->client->request(Request::METHOD_GET, '/stats?show=goshuin');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('main h2'), 'A filter leaving nothing did not state that nothing is held yet.');
        $this->assertCount(0, $crawler->filter('main [data-controller="map"]'), 'A map was drawn for a filter leaving nothing.');
    }

    public function test_another_collectors_goshuincho_changes_no_count(): void
    {
        $owner = UserFactory::createOne();
        $this->client->loginUser($owner);
        GoshuinchoFactory::createOne([
            'owner' => $owner,
            'title' => 'Not yours',
            'boughtAt' => $this->place('Sensō-ji', 35.714765, 139.796655),
            'purchasedAt' => new \DateTimeImmutable('2025-03-14'),
        ]);

        $mine = UserFactory::createOne();
        $this->client->loginUser($mine);
        GoshuinchoFactory::createOne([
            'owner' => $mine,
            'title' => 'Mine',
            'boughtAt' => $this->place('Kiyomizu-dera', 34.994856, 135.784997),
            'purchasedAt' => new \DateTimeImmutable('2025-03-14'),
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, '/stats?show=goshuincho');

        $this->assertSame(['26'], $this->codes(0), "Another collector's goshuincho reached the map.");
        $this->assertSame('1 goshuincho', $this->held(0, '26'), "Another collector's goshuincho reached a zone counter.");
        $this->assertSame([], $this->missing($crawler), "Another collector's goshuincho reached a missing line.");
    }

    private function both(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Kansai',
            'boughtAt' => $this->place('Kiyomizu-dera', 34.994856, 135.784997),
        ]);

        $this->collect($kansai, $this->place('Sensō-ji', 35.714765, 139.796655), '2025-03-14');
        $this->collect($kansai, LocationFactory::createOne(['romanizedName' => 'Nowhere in particular']), '2025-03-15');
    }

    /**
     * @return list<string>
     */
    private function tiles(Crawler $crawler): array
    {
        return $crawler->filter('main .tile.flex')
            ->each(static fn (Crawler $tile): string => preg_replace('/\s+/', ' ', trim($tile->text())));
    }

    /**
     * @return list<string>
     */
    private function missing(Crawler $crawler): array
    {
        return $crawler->filter('main [data-missing]')
            ->each(static fn (Crawler $line): string => preg_replace('/\s+/', ' ', trim($line->text())));
    }

    public function test_a_bucket_holding_both_draws_one_bar_split_in_two(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Kansai',
            'boughtAt' => $this->place('Kiyomizu-dera', 34.994856, 135.784997),
            'purchasedAt' => new \DateTimeImmutable('2025-03-14'),
        ]);

        foreach (['2025-03-14', '2025-03-15', '2025-03-16', '2025-03-17'] as $day) {
            $this->collect($kansai, $this->place('Somewhere '.$day, 34.994856, 135.784997), $day);
        }

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(['goshuin', 'goshuincho'], $this->segments($crawler, 'month', 3), 'March does not draw one segment per population.');
        $this->assertSame([80.0, 20.0], $this->widths($crawler, 'month', 3), 'The bar of March is not one bar of five split four to one.');
        $this->assertSame([4, 1], $this->printed($crawler, 'month', 3), 'The counters beside the bar do not print a pair.');
    }

    public function test_the_same_bucket_filtered_draws_one_bar_of_one_colour(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Kansai',
            'boughtAt' => $this->place('Kiyomizu-dera', 34.994856, 135.784997),
            'purchasedAt' => new \DateTimeImmutable('2025-03-14'),
        ]);

        foreach (['2025-03-14', '2025-03-15', '2025-03-16', '2025-03-17'] as $day) {
            $this->collect($kansai, $this->place('Somewhere '.$day, 34.994856, 135.784997), $day);
        }

        $crawler = $this->client->request(Request::METHOD_GET, '/stats?show=goshuin');

        $this->assertSame(['goshuin'], $this->segments($crawler, 'month', 3), 'A filtered bucket still draws the other population.');
        $this->assertSame([100.0], $this->widths($crawler, 'month', 3), 'A filtered bar is not scaled to its own population alone.');
        $this->assertSame([4], $this->printed($crawler, 'month', 3), 'A filtered bucket still prints a pair.');
    }

    public function test_a_bucket_holding_one_kind_draws_one_segment_and_no_empty_second(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Kansai',
            'boughtAt' => $this->place('Kiyomizu-dera', 34.994856, 135.784997),
            'purchasedAt' => new \DateTimeImmutable('2025-07-01'),
        ]);

        foreach (['2025-03-14', '2025-03-15', '2025-03-16'] as $day) {
            $this->collect($kansai, $this->place('Somewhere '.$day, 34.994856, 135.784997), $day);
        }

        $crawler = $this->client->request(Request::METHOD_GET, '/stats');

        $this->assertSame(['goshuin'], $this->segments($crawler, 'month', 3), 'A bucket holding goshuin alone drew an empty second segment.');
        $this->assertSame(['goshuincho'], $this->segments($crawler, 'month', 7), 'A bucket holding a goshuincho alone drew an empty second segment.');
        $this->assertSame([], $this->segments($crawler, 'month', 1), 'An empty bucket drew a segment.');
        $this->assertSame([3, 0], $this->printed($crawler, 'month', 3), 'A bucket holding one kind stopped printing the pair.');
    }

    /**
     * @return list<string>
     */
    private function segments(Crawler $crawler, string $kind, int $bucket): array
    {
        return $this->bucket($crawler, $kind, $bucket)->filter('[data-segment]')
            ->each(static fn (Crawler $segment): string => (string) $segment->attr('data-segment'));
    }

    /**
     * @return list<float>
     */
    private function widths(Crawler $crawler, string $kind, int $bucket): array
    {
        return $this->bucket($crawler, $kind, $bucket)->filter('[data-segment]')
            ->each(static fn (Crawler $segment): float => (float) trim(str_replace(['width:', '%'], '', (string) $segment->attr('style'))));
    }

    /**
     * @return list<int>
     */
    private function printed(Crawler $crawler, string $kind, int $bucket): array
    {
        return $this->bucket($crawler, $kind, $bucket)->filter('[data-series]')
            ->each(static fn (Crawler $cell): int => (int) trim($cell->text()));
    }

    private function bucket(Crawler $crawler, string $kind, int $bucket): Crawler
    {
        return $crawler->filter('main [data-distribution="'.$kind.'"] li')->eq($bucket - 1);
    }

    public function test_every_unit_on_both_layers_has_a_name_to_show(): void
    {
        $this->openStats();

        foreach ([0, 1] as $level) {
            foreach ($this->layer($level) as $properties) {
                $this->assertNotSame('', $this->named($properties, 'en'), 'A unit has no name to show outside Japanese: '.$properties['code']);
                $this->assertNotSame('', $this->named($properties, 'ja'), 'A unit has no name to show in Japanese: '.$properties['code']);
            }
        }
    }

    public function test_a_prefecture_reads_romanized_outside_japanese_and_kanji_inside_it(): void
    {
        $this->openStats();

        $kyoto = $this->unit(0, '26');

        $this->assertSame('Kyoto', $this->named($kyoto, 'en'), 'A prefecture does not read romanized outside Japanese.');
        $this->assertSame('Kyoto', $this->named($kyoto, 'fr'), 'A prefecture does not read romanized in French.');
        $this->assertSame('京都府', $this->named($kyoto, 'ja'), 'A prefecture does not read kanji in Japanese.');
        $this->assertCount(47, $this->layer(0), 'The prefecture layer no longer draws all 47.');
    }

    public function test_a_municipality_inside_a_district_is_named_by_the_town_alone(): void
    {
        $this->openStats();

        $matsumae = $this->unit(1, '01331');

        $this->assertSame('松前町', $this->named($matsumae, 'ja'), 'A town under a district carries its district in Japanese.');
        $this->assertSame('Matsumae', $this->named($matsumae, 'en'), 'A town under a district carries its district in English.');

        foreach ($this->layer(1) as $properties) {
            $this->assertDoesNotMatchRegularExpression('/.+郡.+[町村]$/u', $properties['name'], 'A municipality is named by its district: '.$properties['code']);
        }
    }

    public function test_a_municipality_reads_romanized_outside_japanese_and_kanji_inside_it(): void
    {
        $this->openStats();

        foreach (['26100' => ['Kyoto', '京都市'], '13106' => ['Taito', '台東区'], '01331' => ['Matsumae', '松前町']] as $code => [$romanized, $kanji]) {
            $unit = $this->unit(1, (string) $code);

            $this->assertSame($romanized, $this->named($unit, 'en'), 'A municipality does not read romanized in English.');
            $this->assertSame($romanized, $this->named($unit, 'fr'), 'A municipality does not read romanized in French.');
            $this->assertSame($kanji, $this->named($unit, 'ja'), 'A municipality does not read kanji in Japanese.');
        }
    }

    public function test_a_municipality_with_no_reading_falls_back_to_its_kanji(): void
    {
        $this->openStats();

        $shikotan = $this->unit(1, '01695');

        $this->assertArrayNotHasKey('romanized', $shikotan, 'The readingless unit grew a romanisation.');

        foreach (['en', 'fr', 'ja'] as $locale) {
            $this->assertSame('色丹村', $this->named($shikotan, $locale), 'A municipality with no reading does not fall back to its kanji under '.$locale.'.');
        }
    }

    public function test_only_the_northern_territories_go_without_a_reading(): void
    {
        $this->openStats();

        $readingless = [];

        foreach ($this->layer(1) as $properties) {
            if (($properties['romanized'] ?? '') === '') {
                $readingless[] = $properties['code'];
            }
        }

        sort($readingless);

        $this->assertSame(['01695', '01696', '01697', '01698', '01699', '01700'], $readingless, 'The municipalities without a reading are no longer the six Northern Territories villages.');
    }

    private function openStats(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $this->collect($kansai, $this->place('Kiyomizu-dera', 34.994856, 135.784997), '2025-03-14');

        $this->client->request(Request::METHOD_GET, '/stats');
    }

    /**
     * @param array<string, string> $properties
     */
    private function named(array $properties, string $locale): string
    {
        $chain = $locale === 'ja'
            ? [$properties['name'] ?? '', $properties['romanized'] ?? '']
            : [$properties['romanized'] ?? '', $properties['name'] ?? ''];

        foreach ($chain as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function unit(int $level, string $code): array
    {
        foreach ($this->layer($level) as $properties) {
            if ($properties['code'] === $code) {
                return $properties;
            }
        }

        $this->fail('The layer carries no unit coded '.$code.'.');
    }

    /**
     * @return list<array<string, string>>
     */
    private function layer(int $level): array
    {
        $path = static::getContainer()->getParameter('kernel.project_dir').'/public'.parse_url($this->levels()[$level]['url'], PHP_URL_PATH);

        $this->assertFileExists($path, 'The map names a boundary file that is not served.');

        $topology = json_decode((string) file_get_contents($path), true);
        $collection = array_key_first($topology['objects']);

        return array_map(
            static fn (array $geometry): array => $geometry['properties'],
            $topology['objects'][$collection]['geometries'],
        );
    }

    /**
     * @return list<string>
     */
    private function codes(int $level): array
    {
        $codes = array_map(
            static fn (array $zone): string => $zone['code'],
            $this->levels()[$level]['zones'],
        );

        sort($codes);

        return $codes;
    }

    private function held(int $level, string $code): ?string
    {
        foreach ($this->levels()[$level]['zones'] as $zone) {
            if ($zone['code'] === $code) {
                return $zone['held'];
            }
        }

        return null;
    }

    /**
     * @return list<array{url: string, codes: list<string>, held: string}>
     */
    private function levels(): array
    {
        $map = $this->client->getCrawler()->filter('main [data-map-mode-value="regions"]');

        $this->assertCount(1, $map, 'The coverage section does not hold exactly one map.');

        $levels = json_decode((string) $map->attr('data-map-layers-value'), true);

        $this->assertCount(2, $levels, 'The map does not carry both layers.');

        return $levels;
    }

    /**
     * @return list<int>
     */
    private function counts(Crawler $crawler, string $kind, string $series = 'goshuin'): array
    {
        return $crawler->filter('main [data-distribution="'.$kind.'"] [data-series="'.$series.'"]')
            ->each(static fn (Crawler $cell): int => (int) trim($cell->text()));
    }

    /**
     * @return list<string>
     */
    private function labels(Crawler $crawler, string $kind): array
    {
        return $crawler->filter('main [data-distribution="'.$kind.'"] li > span:first-child')
            ->each(static fn (Crawler $cell): string => trim($cell->text()));
    }

    private function text(Crawler $crawler): string
    {
        return preg_replace('/\s+/', ' ', $crawler->filter('main')->text());
    }

    private function place(string $name, float $latitude, float $longitude): Location
    {
        return LocationFactory::createOne([
            'romanizedName' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    private function collect(Goshuincho $goshuincho, Location $place, ?string $day): void
    {
        $id = $goshuincho->getId();
        $this->spots[$id] = ($this->spots[$id] ?? 0) + 1;

        GoshuinFactory::new()->in($goshuincho, $this->spots[$id])->create([
            'location' => $place,
            'receivedOn' => $day === null ? null : new \DateTimeImmutable($day),
        ]);
    }
}
