<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Prefecture;
use App\Entity\PrefecturePhoto;
use App\Repository\PrefectureRepository;
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

class PrefectureTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_the_prefectures_are_private(): void
    {
        $prefecture = PrefectureFactory::createOne(['owner' => UserFactory::createOne()]);

        foreach (['/prefectures', '/prefecture/'.$prefecture->getId(), '/prefecture/'.$prefecture->getId().'/edit', '/prefecture/'.$prefecture->getId().'/delete'] as $url) {
            $this->client->request(Request::METHOD_GET, $url);

            $this->assertResponseRedirects();
            $this->client->followRedirect();
            $this->assertRouteSame('app_login', [], sprintf('%s is reachable signed out.', $url));
        }
    }

    public function test_a_prefecture_another_collector_keeps_is_out_of_reach(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $id = PrefectureFactory::createOne()->getId();

        $this->client->loginUser(UserFactory::createOne());

        foreach (['/prefecture/'.$id, '/prefecture/'.$id.'/edit', '/prefecture/'.$id.'/delete'] as $url) {
            $this->client->request(Request::METHOD_GET, $url);

            $this->assertResponseStatusCodeSame(404, sprintf('%s answered for a prefecture kept by another collector.', $url));
        }
    }

    public function test_the_index_lists_the_prefectures_and_opens_each_one(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        PrefectureFactory::createOne(['romanizedName' => 'Kyōto']);
        $kanagawa = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefectures');

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('main ul li a'), 'The index does not list every prefecture.');

        $first = $crawler->filter('main ul li a')->first();
        $this->assertSame('/prefecture/'.$kanagawa->getId(), $first->attr('href'), 'A prefecture does not open its own page.');
        $this->assertStringContainsString('Kanagawa', $first->text());
    }

    public function test_the_index_is_ordered_by_the_reading_when_it_is_read_in_japanese(): void
    {
        UserFactory::createOne(['email' => 'user@example.com', 'locale' => 'ja']);

        $login = $this->client->request(Request::METHOD_GET, '/login');
        $this->client->submit($login->filter('form')->form(), [
            '_username' => 'user@example.com',
            '_password' => 'a-long-enough-password',
        ]);
        $this->client->followRedirect();

        foreach ([
            ['romanizedName' => 'Tokyo', 'kanjiName' => '東京都', 'kanaName' => 'とうきょう'],
            ['romanizedName' => 'Kanagawa', 'kanjiName' => '神奈川県', 'kanaName' => 'かながわ'],
            ['romanizedName' => 'Nara', 'kanjiName' => '奈良県', 'kanaName' => 'なら'],
        ] as $names) {
            PrefectureFactory::createOne($names);
        }

        $named = $this->client->request(Request::METHOD_GET, '/prefectures')
            ->filter('main ul li a')
            ->each(static fn (Crawler $row): string => trim($row->text()))
        ;

        $this->assertSame(['神奈川県', '東京都', '奈良県'], $named, 'The index is not ordered by the kana reading.');
    }

    public function test_the_index_is_ordered_by_the_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['Tokyo', 'Kanagawa', 'Nara'] as $name) {
            PrefectureFactory::createOne(['romanizedName' => $name]);
        }

        $names = $this->client->request(Request::METHOD_GET, '/prefectures')
            ->filter('main ul li a')
            ->each(static fn (Crawler $row): string => trim($row->text()))
        ;

        $this->assertSame(['Kanagawa', 'Nara', 'Tokyo'], $names, 'The index is not ordered by the name.');
    }

    public function test_the_index_pages_through_the_prefectures(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (range(1, 26) as $rank) {
            PrefectureFactory::createOne(['romanizedName' => sprintf('Ken %02d', $rank)]);
        }

        $first = $this->client->request(Request::METHOD_GET, '/prefectures');

        $this->assertCount(24, $first->filter('main ul li a'), 'The index does not hold a full page of prefectures.');
        $this->assertStringNotContainsString('Ken 25', $first->filter('main ul')->text(), 'The first page reaches past its own end.');

        $second = $this->client->click($first->filter('main nav a')->last()->link());

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $second->filter('main ul li a'), 'The last page does not hold what the first left.');
    }

    public function test_a_page_of_prefectures_beyond_the_last_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        PrefectureFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/prefectures?page=2');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_the_index_states_that_no_prefecture_exists_yet(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/prefectures');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'The index invented a row.');
        $this->assertStringContainsString('No prefecture yet', $crawler->filter('main')->text());
    }

    public function test_the_search_matches_any_part_of_a_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        PrefectureFactory::createOne(['romanizedName' => 'Nara']);
        PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);

        foreach (['nagaw' => 'Kanagawa', 'KANA' => 'Kanagawa', 'nar' => 'Nara'] as $term => $expected) {
            $rows = $this->client->request(Request::METHOD_GET, '/prefectures?q='.urlencode((string) $term))->filter('main ul li a');

            $this->assertCount(1, $rows, sprintf('Searching "%s" does not match exactly one prefecture.', $term));
            $this->assertStringContainsString($expected, $rows->text(), sprintf('Searching "%s" matched the wrong prefecture.', $term));
        }
    }

    public function test_a_search_matching_nothing_says_so_and_leads_back(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        PrefectureFactory::createOne(['romanizedName' => 'Nara']);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefectures?q=nobody');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main ul li'), 'A search matching nothing still listed a prefecture.');
        $this->assertSame('/prefectures', $crawler->filter('main p a')->attr('href'), 'A fruitless search is a dead end.');
    }

    public function test_a_prefecture_page_counts_its_cities_and_locations_and_leads_to_each_list(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kanagawa = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);
        $kamakura = CityFactory::createOne(['romanizedName' => 'Kamakura', 'prefecture' => $kanagawa]);
        CityFactory::createOne(['romanizedName' => 'Yokohama', 'prefecture' => $kanagawa]);
        CityFactory::createOne(['romanizedName' => 'Nara']);
        LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'city' => $kamakura, 'prefecture' => $kanagawa]);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefecture/'.$kanagawa->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSame('Kanagawa', trim($crawler->filter('h1')->text()), 'The page does not name the prefecture.');

        $totals = $crawler->filter('main a.tile')->each(
            static fn (Crawler $tile): array => [$tile->attr('href'), preg_replace('/\s+/', ' ', trim($tile->text()))],
        );

        $this->assertSame([
            ['/cities?prefecture='.$kanagawa->getId(), '2 cities'],
            ['/locations?prefecture='.$kanagawa->getId(), '1 location'],
        ], $totals, 'The page does not count what it holds, nor lead to each list.');
    }

    public function test_a_prefecture_holding_nothing_counts_nothing(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Nara']);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main a.tile'), 'A count was invented for a prefecture holding nothing.');
    }

    public function test_the_cities_of_a_prefecture_are_listed_on_their_own_page(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kanagawa = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);
        CityFactory::createOne(['romanizedName' => 'Kamakura', 'prefecture' => $kanagawa]);
        CityFactory::createOne(['romanizedName' => 'Nara']);

        $crawler = $this->client->request(Request::METHOD_GET, '/cities?prefecture='.$kanagawa->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('main ul li a'), 'The list is not narrowed to the cities of the prefecture.');
        $this->assertStringContainsString('Kamakura', $crawler->filter('main ul li a')->text());
        $this->assertCount(1, $crawler->filter('main a[href="/cities"]'), 'Nothing leads back to the whole list.');
    }

    public function test_the_locations_of_a_prefecture_are_listed_on_their_own_page(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $kanagawa = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);
        LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'prefecture' => $kanagawa]);
        LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera']);

        $crawler = $this->client->request(Request::METHOD_GET, '/locations?prefecture='.$kanagawa->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('main ul li a'), 'The list is not narrowed to the locations of the prefecture.');
        $this->assertStringContainsString('Hase-dera', $crawler->filter('main ul li a')->text());
        $this->assertCount(1, $crawler->filter('main a[href="/locations"]'), 'Nothing leads back to the whole list.');
    }

    public function test_an_unknown_prefecture_narrows_nothing(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['/cities', '/locations'] as $url) {
            $this->client->request(Request::METHOD_GET, $url.'?prefecture='.Uuid::v7()->toRfc4122());

            $this->assertResponseStatusCodeSame(404, sprintf('%s accepted an unknown prefecture as a filter.', $url));
        }
    }

    public function test_an_unknown_prefecture_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/prefecture/'.Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_the_prefecture_page_offers_a_collector_nothing_to_change(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['notes' => 'Faces the bay.']);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('main form'), 'A collector was offered something to change.');
        $this->assertCount(0, $crawler->filter('input[type="file"]'), 'A collector was offered a way to add a photograph.');
    }

    public function test_a_prefecture_is_renamed_and_the_new_name_shows_everywhere(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);
        $location = LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'prefecture' => $prefecture]);

        $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId().'/edit');
        $this->client->submitForm('prefecture_submit', [
            'prefecture[romanizedName]' => 'Kanagawa-ken',
            'prefecture[notes]' => 'Faces the bay.',
        ]);

        $this->assertResponseRedirects('/prefecture/'.$prefecture->getId());

        $this->manager()->clear();
        $stored = $this->stored($prefecture->getId());

        $this->assertSame('Kanagawa-ken', $stored->getRomanizedName(), 'The new name was not stored.');
        $this->assertSame('Faces the bay.', $stored->getNotes(), 'The notes were not stored.');

        $page = $this->client->request(Request::METHOD_GET, '/location/'.$location->getId())->filter('main')->text();
        $this->assertStringContainsString('Kanagawa-ken', $page, 'The renamed prefecture did not reach the location sitting in it.');
    }

    public function test_the_form_refuses_a_prefecture_with_no_name(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);

        $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId().'/edit');
        $this->client->submitForm('prefecture_submit', ['prefecture[romanizedName]' => '']);

        $this->assertResponseStatusCodeSame(422);

        $this->manager()->clear();
        $this->assertSame('Kanagawa', $this->stored($prefecture->getId())->getRomanizedName(), 'A refused submission still wrote the prefecture away without a name.');
    }

    public function test_a_name_another_prefecture_already_bears_is_accepted(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        PrefectureFactory::createOne(['romanizedName' => 'Nara']);
        $subject = PrefectureFactory::createOne(['romanizedName' => 'Kamakura']);

        $this->client->request(Request::METHOD_GET, '/prefecture/'.$subject->getId().'/edit');
        $this->client->submitForm('prefecture_submit', ['prefecture[romanizedName]' => 'Nara']);

        $this->assertResponseRedirects();

        $this->manager()->clear();
        $this->assertSame('Nara', static::getContainer()->get(PrefectureRepository::class)->find($subject->getId())->getRomanizedName(), 'A name another row already bears was refused.');
    }

    public function test_a_prefecture_still_holding_a_city_cannot_be_deleted(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);
        CityFactory::createOne(['romanizedName' => 'Kamakura', 'prefecture' => $prefecture]);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId().'/delete');

        $this->assertStringContainsString('still holds a city or a location', $crawler->filter('main')->text(), 'The refusal is not stated.');
        $this->assertCount(0, $crawler->filter('main form'), 'A prefecture still in use was offered for deletion anyway.');

        $this->manager()->clear();
        $this->assertNotNull($this->stored($prefecture->getId()), 'The prefecture was removed.');
    }

    public function test_a_prefecture_still_holding_a_location_cannot_be_deleted(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);
        LocationFactory::createOne(['romanizedName' => 'Hase-dera', 'prefecture' => $prefecture]);

        $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId().'/delete');

        $this->assertCount(0, $this->client->getCrawler()->filter('main form'), 'A prefecture still in use was offered for deletion anyway.');

        $this->manager()->clear();
        $this->assertNotNull($this->stored($prefecture->getId()), 'The prefecture was removed.');
    }

    public function test_a_prefecture_nothing_holds_is_deleted(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);
        $id = $prefecture->getId();
        $id = $prefecture->getId();

        $this->client->request(Request::METHOD_GET, '/prefecture/'.$id.'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects('/prefectures');

        $this->manager()->clear();
        $this->assertNull($this->stored($id), 'The prefecture survived.');
    }

    public function test_a_prefecture_carries_a_main_photograph_and_a_gallery(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);

        $this->correct($prefecture, [
            'photo_add' => ['prefecture' => [$this->createImage(900, 600), $this->createImage(900, 600)]],
            'photo_add_label' => ['prefecture' => ['The bay', '  ']],
        ]);

        $stored = $this->stored($prefecture->getId());
        $photos = $stored->getPhotos();

        $this->assertNotNull($stored->getPhotographFull(), 'The main photograph was not stored.');
        $this->assertCount(2, $photos, 'The gallery did not keep both photographs.');
        $this->assertSame([1, 2], $photos->map(static fn (PrefecturePhoto $p): ?int => $p->getPosition())->toArray(), 'The gallery is not numbered from one.');
        $this->assertSame(['The bay', null], $photos->map(static fn (PrefecturePhoto $p): ?string => $p->getLabel())->toArray(), 'A blank label was stored as a string.');

        $page = $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId());
        $this->assertCount(1, $page->filter('main .stage img'), 'The page does not show the main photograph.');
        $this->assertCount(2, $page->filter('main .gallery img'), 'The page does not show the gallery.');

        $this->discard($prefecture->getId());
    }

    public function test_a_photograph_that_is_not_an_image_is_refused_without_taking_the_others_down(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $prefecture = PrefectureFactory::createOne(['romanizedName' => 'Kanagawa']);

        $this->correct($prefecture, [
            'photo_add' => ['prefecture' => [$this->createTextFile(), $this->createImage(900, 600)]],
        ]);

        $this->assertCount(1, $this->stored($prefecture->getId())->getPhotos(), 'The refused file was stored, or it took the valid one down with it.');

        $this->discard($prefecture->getId());
    }

    /**
     * @param array<string, mixed> $photographs
     */
    private function correct(Prefecture $prefecture, array $photographs): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/prefecture/'.$prefecture->getId().'/edit');
        $form = $crawler->selectButton('prefecture_submit')->form();

        $added = $photographs['photo_add'] ?? [];
        unset($photographs['photo_add']);

        $values = $form->getPhpValues();
        $values['prefecture']['photographFile'] = $this->createImage(1600, 1100);

        $this->client->request(
            Request::METHOD_POST,
            $form->getUri(),
            [...$values, ...$photographs],
            ['prefecture' => ['photographFile' => $values['prefecture']['photographFile']], ...($added === [] ? [] : ['photo_add' => $added])],
        );

        $this->assertResponseRedirects();
        $this->manager()->clear();
    }

    public function test_the_page_shows_the_goshuincho_and_the_goshuin_that_come_from_the_prefecture(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $kyoto = PrefectureFactory::createOne(['romanizedName' => 'Kyōto']);
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        GoshuinFactory::new()->in($goshuincho)->create(['location' => LocationFactory::createOne([
            'romanizedName' => 'Kiyomizu-dera',
            'prefecture' => $kyoto,
        ])]);
        GoshuinFactory::new()->in(GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']))->create([
            'location' => LocationFactory::createOne(['romanizedName' => 'Sensō-ji', 'prefecture' => PrefectureFactory::createOne(['romanizedName' => 'Tōkyō'])]),
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefecture/'.$kyoto->getId());

        $this->assertResponseIsSuccessful();
        $body = $crawler->filter('main')->text();
        $this->assertStringContainsString('Kansai', $body, 'The page does not name the goshuincho the prefecture was collected in.');
        $this->assertStringNotContainsString('Kantō', $body, 'A goshuincho naming another prefecture reached the page.');
        $this->assertCount(1, $crawler->filter('main a[href="/goshuincho/'.$goshuincho->getId().'/goshuin/1"]'), 'The page does not show the goshuin received in the prefecture.');
    }

    public function test_the_page_holds_no_other_collectors_goshuin(): void
    {
        $owner = UserFactory::createOne();
        $this->client->loginUser($owner);
        $theirs = PrefectureFactory::createOne(['romanizedName' => 'Kyōto']);
        GoshuinFactory::new()->in(GoshuinchoFactory::createOne(['owner' => $owner, 'title' => 'Not yours']))->create([
            'location' => LocationFactory::createOne(['romanizedName' => 'Hidden away', 'prefecture' => $theirs]),
        ]);

        $this->client->loginUser(UserFactory::createOne());
        $mine = PrefectureFactory::createOne(['romanizedName' => 'Kyōto']);

        $crawler = $this->client->request(Request::METHOD_GET, '/prefecture/'.$mine->getId());

        $this->assertStringNotContainsString('Not yours', $crawler->filter('main')->text(), 'A foreign goshuincho reached the prefecture page.');
        $this->assertCount(0, $crawler->filter('main a[href*="/goshuin/"]'), 'A foreign goshuin reached the prefecture page.');
    }

    private function stored(string $id): ?Prefecture
    {
        return static::getContainer()->get(PrefectureRepository::class)->find($id);
    }

    private function discard(string $id): void
    {
        $prefecture = $this->stored($id);

        $this->removeUploads($prefecture->getPhotograph(), $prefecture->getPhotographMini(), $prefecture->getPhotographCard(), $prefecture->getPhotographFull());

        foreach ($prefecture->getPhotos() as $photo) {
            $this->removeUploads($photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull());
        }
    }
}
