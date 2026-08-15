<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuincho;
use App\Entity\User;
use App\Repository\GoshuinRepository;
use App\Repository\GoshuinchoRepository;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Crawler;
use App\Tests\Factory\CityFactory;
use App\Tests\Factory\GoshuinFactory;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\PrefectureFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GoshuinchoTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_a_title_alone_creates_one(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Kyoto 2024']);

        $this->assertResponseRedirects();
        $created = $this->repository()->findOneBy(['title' => 'Kyoto 2024']);
        $this->assertNotNull($created, 'A title alone was not enough.');
        $this->assertNull($created->getPurchasedAt());
        $this->assertNull($created->getPrice());
        $this->assertNull($created->getDescription());
    }

    public function test_the_optional_fields_are_kept_when_given(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'Nara',
            'goshuincho[purchasedAt]' => '2024-04-12',
            'goshuincho[price]' => '1500',
            'goshuincho[description]' => 'Bought at the temple gate.',
        ]);

        $this->assertResponseRedirects();
        $created = $this->repository()->findOneBy(['title' => 'Nara']);
        $this->assertSame('2024-04-12', $created->getPurchasedAt()->format('Y-m-d'));
        $this->assertSame(1500, $created->getPrice());
        $this->assertSame('JPY', $created->getCurrency());
        $this->assertSame('Bought at the temple gate.', $created->getDescription());
    }

    public function test_the_place_of_purchase_is_recorded_and_shown(): void
    {
        $location = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera', 'japaneseName' => '清水寺']);
        $locationId = $location->getId();
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'Kyoto',
            'goshuincho[boughtAt]' => $locationId,
        ]);

        $this->assertResponseRedirects();
        $created = $this->repository()->findOneBy(['title' => 'Kyoto']);
        $this->assertNotNull($created->getBoughtAt(), 'The place of purchase was not recorded.');
        $this->assertSame($locationId, $created->getBoughtAt()->getId());

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Kiyomizu-dera', $crawler->filter('main dl')->text(), 'The place of purchase is not shown.');
        $this->assertCount(1, $crawler->filter('main dl a[href="/location/'.$location->getSlug().'"]'), 'The place of purchase does not lead to its page.');
    }

    public function test_the_place_of_purchase_stays_optional(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Nowhere in particular']);

        $this->assertResponseRedirects();
        $this->assertNull($this->repository()->findOneBy(['title' => 'Nowhere in particular'])->getBoughtAt());
    }

    public function test_a_created_goshuincho_belongs_to_the_signed_in_user(): void
    {
        $user = UserFactory::createOne();
        $userId = $user->getId();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Unowned on submit']);

        $created = $this->repository()->findOneBy(['title' => 'Unowned on submit']);
        $this->assertNotNull($created->getOwner(), 'The created goshuincho has no owner.');
        $this->assertSame($userId, $created->getOwner()->getId());
    }

    public function test_two_users_may_name_a_goshuincho_the_same_thing(): void
    {
        $first = UserFactory::createOne();
        $secondId = UserFactory::createOne()->getId();

        $this->client->loginUser($first);
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Kumano Kodo']);
        $this->assertResponseRedirects();

        $this->client->loginUser($this->unfiltered()->getRepository(User::class)->find($secondId));
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Kumano Kodo']);
        $this->assertResponseRedirects();

        $slugs = array_map(
            static fn (Goshuincho $goshuincho): string => $goshuincho->getSlug(),
            $this->ignoringOwnership(),
        );

        $this->assertCount(2, $slugs);
        $this->assertSame(['kumano-kodo', 'kumano-kodo'], $slugs, 'The slug was scoped globally instead of per owner.');
    }

    public function test_one_user_naming_two_goshuincho_the_same_gets_distinct_slugs(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach ([1, 2] as $ignored) {
            $this->client->request(Request::METHOD_GET, '/goshuincho/add');
            $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Ise']);
            $this->assertResponseRedirects();
        }

        $slugs = array_map(
            static fn (Goshuincho $goshuincho): string => $goshuincho->getSlug(),
            $this->ignoringOwnership(),
        );

        $this->assertCount(2, $slugs);
        $this->assertNotSame($slugs[0], $slugs[1], 'One owner ended up with two identical slugs.');
    }

    public function test_a_goshuincho_holding_no_goshuin_says_so(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Nothing in it']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No goshuin yet', $crawler->filter('main')->text(), 'The empty state did not state itself.');
    }

    public function test_a_recorded_price_is_read_back_formatted(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'price' => 1500, 'currency' => 'JPY']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertStringContainsString('1,500', $crawler->filter('main')->text(), 'The price was not read back in minor units of yen.');
    }

    public function test_every_stored_field_can_be_changed(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Before']);
        $slug = $goshuincho->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'After',
            'goshuincho[purchasedAt]' => '2023-11-03',
            'goshuincho[price]' => '900',
            'goshuincho[description]' => 'Rebound.',
        ]);

        $this->assertResponseRedirects();
        $stored = $this->repository()->findOneBy(['title' => 'After']);
        $this->assertNotNull($stored);
        $this->assertSame('2023-11-03', $stored->getPurchasedAt()->format('Y-m-d'));
        $this->assertSame(900, $stored->getPrice());
        $this->assertSame('Rebound.', $stored->getDescription());
    }

    public function test_deletion_goes_through_a_posted_form(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $slug = $goshuincho->getSlug();
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/delete');
        $this->assertCount(1, $crawler->filter('form[method="post"]'));
        $this->assertCount(1, $crawler->filter('form input[name$="[_token]"]'), 'The delete form carries no CSRF token.');

        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects();
        $this->assertNull($this->repository()->findOneBy(['slug' => $slug]), 'The Goshuincho survived its deletion.');
    }

    public function test_another_users_goshuincho_is_not_found(): void
    {
        $theirs = GoshuinchoFactory::createOne();
        $slug = $theirs->getSlug();
        $this->client->loginUser(UserFactory::createOne());

        foreach (['', '/edit', '/delete'] as $suffix) {
            $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.$suffix);
            $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, sprintf('%s exposed a foreign goshuincho.', $suffix ?: '/show'));
        }
    }

    public function test_a_hue_is_assigned_without_being_asked_for(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->assertCount(0, $crawler->filter('#goshuincho_hue'), 'Creation asked for a colour.');

        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Coloured on its own']);

        $created = $this->repository()->findOneBy(['title' => 'Coloured on its own']);
        $this->assertNotNull($created->getHue(), 'No hue was assigned.');
        $this->assertGreaterThanOrEqual(0, $created->getHue());
        $this->assertLessThanOrEqual(360, $created->getHue());
    }

    public function test_changing_one_hue_changes_no_other(): void
    {
        $user = UserFactory::createOne();
        $mine = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Mine', 'hue' => 10]);
        $other = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Other', 'hue' => 200]);
        $mineSlug = $mine->getSlug();
        $otherId = $other->getId();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$mineSlug.'/edit');
        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'Mine',
            'goshuincho[hue]' => '300',
        ]);

        $this->assertResponseRedirects();
        $this->assertSame(300, $this->repository()->findOneBy(['slug' => $mineSlug])->getHue());
        $this->assertSame(200, $this->repository()->find($otherId)->getHue(), 'Editing one hue moved another.');
    }

    public function test_an_uploaded_cover_is_stored_with_its_derivatives(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'With a cover']);
        $slug = $goshuincho->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'With a cover',
            'goshuincho[coverFrontFile]' => $this->createImage(),
        ]);

        $this->assertResponseRedirects();
        $stored = $this->repository()->findOneBy(['slug' => $slug]);
        $this->assertNotNull($stored->getCoverFront(), 'The cover was not recorded on the goshuincho.');

        $root = $this->uploadsDir();
        $paths = [$stored->getCoverFront(), $stored->getCoverFrontMini(), $stored->getCoverFrontCard(), $stored->getCoverFrontFull()];

        foreach ($paths as $path) {
            $this->assertNotNull($path, 'A slot was left unrecorded on the row.');
            $this->assertFileExists($root.'/'.$path, 'A recorded path has no file behind it.');
        }

        $this->assertSame(96, getimagesize($root.'/'.$stored->getCoverFrontMini())[0], 'The mini column does not hold the mini derivative.');
        $this->assertSame(384, getimagesize($root.'/'.$stored->getCoverFrontCard())[0], 'The card column does not hold the card derivative.');

        $this->removeUploads(...$paths);
    }

    public function test_a_refused_upload_keeps_the_other_fields_and_stores_nothing(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Untouched']);
        $slug = $goshuincho->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $crawler = $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'Renamed in the same submission',
            'goshuincho[coverFrontFile]' => $this->createTextFile(),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'A text file was accepted as a cover.');
        $this->assertStringContainsString('JPEG, PNG and WebP', $crawler->filter('main')->text(), 'The refusal did not name what is accepted.');
        $this->assertSame('Renamed in the same submission', $crawler->filter('#goshuincho_title')->attr('value'), 'The other fields were lost with the refusal.');

        $stored = $this->repository()->findOneBy(['slug' => $slug]);
        $this->assertNull($stored->getCoverFront(), 'A refused upload was recorded.');
        $this->assertSame('Untouched', $stored->getTitle(), 'A refused submission was persisted anyway.');
    }

    public function test_a_cover_can_be_removed_on_its_own(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne([
            'owner' => $user,
            'title' => 'Two covers',
            'coverFront' => 'ab/cd/front.jpg',
            'coverFrontCard' => 'ab/cd/front-384.jpg',
            'coverBack' => 'ab/cd/back.jpg',
            'coverBackCard' => 'ab/cd/back-384.jpg',
        ]);
        $slug = $goshuincho->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'Two covers',
            'goshuincho[removeCoverFront]' => true,
        ]);

        $this->assertResponseRedirects();
        $stored = $this->repository()->findOneBy(['slug' => $slug]);
        $this->assertNull($stored->getCoverFront(), 'The front cover was not removed.');
        $this->assertNull($stored->getCoverFrontCard(), 'The removed cover kept a derivative column.');
        $this->assertSame('ab/cd/back.jpg', $stored->getCoverBack(), 'Removing one cover took the other with it.');
        $this->assertSame('ab/cd/back-384.jpg', $stored->getCoverBackCard(), 'Removing one cover took the other derivative with it.');
    }

    public function test_a_cover_is_served_from_its_derivative(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'coverFront' => 'ab/cd/front.jpg', 'coverFrontFull' => 'ab/cd/front-1200.jpg']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertStringContainsString('/uploads/ab/cd/front-1200.jpg', $crawler->filter('main figure img')->attr('src'), 'The cover is not served from its derivative.');
    }

    public function test_the_cities_and_prefectures_visited_are_counted_once_and_lead_to_their_pages(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        $kanagawa = PrefectureFactory::createOne(['name' => 'Kanagawa']);
        $kamakura = CityFactory::createOne(['name' => 'Kamakura', 'prefecture' => $kanagawa]);
        $kyoto = PrefectureFactory::createOne(['name' => 'Kyōto']);
        $kyotoCity = CityFactory::createOne(['name' => 'Kyōto', 'prefecture' => $kyoto]);

        foreach ([1, 2] as $page) {
            GoshuinFactory::new()->in($goshuincho, $page)->on('2025-03-1'.$page)->create([
                'location' => LocationFactory::new(['romanizedName' => 'Hase-dera '.$page, 'city' => $kamakura, 'prefecture' => $kanagawa]),
            ]);
        }

        GoshuinFactory::new()->in($goshuincho, 3)->on('2025-04-02')->create([
            'location' => LocationFactory::new(['romanizedName' => 'Kiyomizu-dera', 'city' => $kyotoCity, 'prefecture' => $kyoto]),
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertCount(1, $crawler->filter('main a[href="/city/'.$kamakura->getSlug().'"]'), 'A city visited twice is named twice, or does not lead to its page.');
        $this->assertCount(1, $crawler->filter('main a[href="/city/'.$kyotoCity->getSlug().'"]'));
        $this->assertCount(1, $crawler->filter('main a[href="/prefecture/'.$kanagawa->getSlug().'"]'), 'A prefecture visited twice is named twice, or does not lead to its page.');

    }

    public function test_the_order_names_each_goshuin_by_its_place_and_its_day(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        GoshuinFactory::new()->in($goshuincho, 1)->on('2025-03-14')
            ->create(['location' => LocationFactory::new(['romanizedName' => 'Kiyomizu-dera'])]);
        GoshuinFactory::new()->in($goshuincho, 2)->on('2025-04-02')
            ->create(['location' => LocationFactory::new(['romanizedName' => 'Kiyomizu-dera'])]);

        $rows = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/edit')
            ->filter('[data-order-target="row"]');

        $this->assertCount(2, $rows);
        $this->assertStringContainsString('March 14, 2025', $rows->eq(0)->text(), 'The order does not say which day a goshuin was received.');
        $this->assertStringContainsString('April 2, 2025', $rows->eq(1)->text(), 'Two visits to the same place cannot be told apart.');
    }

    public function test_a_goshuin_with_no_day_is_still_named_in_the_order(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        GoshuinFactory::new()->in($goshuincho, 1)
            ->create(['receivedOn' => null, 'location' => LocationFactory::new(['romanizedName' => 'Undated'])]);
        GoshuinFactory::new()->in($goshuincho, 2)
            ->create(['location' => LocationFactory::new(['romanizedName' => 'Dated'])]);

        $rows = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/edit')
            ->filter('[data-order-target="row"]');

        $this->assertStringContainsString('Undated', $rows->eq(0)->text(), 'A goshuin with no day fell out of the order.');
    }

    public function test_the_form_puts_the_goshuin_back_in_the_order_it_submits(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $slug = (string) $goshuincho->getSlug();
        $this->client->loginUser($user);

        $spot = 0;

        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            GoshuinFactory::new()->in($goshuincho, ++$spot)
                ->create(['location' => LocationFactory::new(['romanizedName' => $name])]);
        }

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');
        $rows = $crawler->filter('[data-order-target="row"]');

        $this->assertCount(3, $rows, 'The form does not list the goshuin to order.');
        $this->assertSame(
            ['Alpha', 'Beta', 'Gamma'],
            $rows->each(static fn (Crawler $row): string => trim($row->filter('span.font-semibold')->text())),
            'The form does not list them in their current order.',
        );

        $ids = $crawler->filter('input[name="goshuin_order[]"]')->each(static fn (Crawler $field): string => (string) $field->attr('value'));
        $form = $crawler->selectButton('goshuincho_submit')->form();
        $sent = $form->getPhpValues();
        $sent['goshuin_order'] = [$ids[2], $ids[0], $ids[1]];

        $this->client->request(Request::METHOD_POST, $form->getUri(), $sent, $form->getPhpFiles());

        $this->assertResponseRedirects('/goshuincho/'.$slug);
        $this->assertSame(
            ['Gamma', 'Alpha', 'Beta'],
            $this->client->followRedirect()->filter('main ol li img')->each(static fn (Crawler $image): string => (string) $image->attr('alt')),
            'The goshuincho page does not follow the order the form submitted.',
        );

        $goshuins = static::getContainer()->get(GoshuinRepository::class)->positions(
            $this->manager()->getRepository(Goshuincho::class)->findOneBy(['slug' => $slug]),
        );
        $this->assertSame([1, 2, 3], $goshuins, 'Reordering left the numbering broken.');
    }

    public function test_a_goshuincho_holding_one_goshuin_is_not_asked_to_order_it(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $slug = (string) $goshuincho->getSlug();
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);

        GoshuinFactory::new()->in($goshuincho)->create(['location' => $place]);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->assertCount(0, $crawler->filter('[data-order-target="row"]'), 'A goshuincho with one goshuin offered to reorder it.');
    }

    public function test_the_derived_attributes_are_read_back_from_the_goshuin(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $slug = (string) $goshuincho->getSlug();
        $this->client->loginUser($user);

        $spot = 0;

        foreach ([
            ['Fushimi Inari-taisha', '2025-03-14', 500, 34.9671, 135.7727],
            ['Kiyomizu-dera', '2025-03-14', 300, 34.9949, 135.7850],
            ['Tōdai-ji', '2025-03-21', 500, 34.6889, 135.8398],
        ] as [$name, $day, $price, $latitude, $longitude]) {
            GoshuinFactory::new()->in($goshuincho, ++$spot)->create([
                'location' => LocationFactory::new([
                    'romanizedName' => $name,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]),
                'receivedOn' => new \DateTimeImmutable($day),
                'price' => $price,
            ]);
        }

        $main = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug)->filter('main');
        $text = $main->text();

        $this->assertStringContainsString('3', $text, 'The goshuin are not counted.');
        $this->assertStringContainsString('8 days', $text, 'The eight days between the first and the last were not counted.');
        $groups = $main->filter('ol > li > ol');
        $this->assertCount(2, $groups, 'The trip did not group the goshuin under one separator per day.');
        $this->assertCount(2, $groups->eq(0)->filter('li'), 'Two goshuin received the same day were not grouped together.');
        $this->assertStringContainsString('7 days later', $text, 'The interval between two goshuin is not named.');
        $this->assertStringContainsString('3 places', $text, 'The map does not say how many places it carries.');

        $markers = json_decode((string) $main->filter('[data-controller="map"]')->attr('data-map-markers-value'), true);
        $this->assertCount(3, $markers, 'The map does not carry one marker per goshuin with coordinates.');
        $this->assertSame([1, 2, 3], array_column($markers, 'number'), 'The pins are not numbered in position order.');
        $this->assertSame('numbered', $main->filter('[data-controller="map"]')->attr('data-map-mode-value'));
        $this->assertCount(3, $main->filter('[data-controller="map"] ul.sr li'), 'The marker set has no readable list.');

        $this->assertSame(
            ['1', '2', '3'],
            array_slice($main->filter('[data-index]')->each(static fn (Crawler $node): string => (string) $node->attr('data-index')), 0, 3),
            'The trip rows and the cards do not share the index the highlight is driven by.',
        );
    }

    public function test_the_map_pins_take_the_colour_the_goshuincho_was_given(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'hue' => 210]);
        $this->client->loginUser($user);

        GoshuinFactory::new()->in($goshuincho)->create([
            'location' => LocationFactory::new([
                'romanizedName' => 'Kiyomizu-dera',
                'latitude' => 34.9949,
                'longitude' => 135.7850,
            ]),
        ]);

        $map = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug())->filter('main [data-controller="map"]');
        $markers = json_decode((string) $map->attr('data-map-markers-value'), true);

        $this->assertSame(210, $markers[0]['hue'], 'The pin does not carry the colour chosen for the goshuincho.');
    }

    public function test_the_index_lists_the_goshuincho_held_and_opens_each_one(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']);
        $kansai = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho');

        $this->assertResponseIsSuccessful();
        $cards = $crawler->filter('main ul li a');
        $this->assertCount(2, $cards, 'The index does not list every goshuincho.');
        $this->assertSame('/goshuincho/'.$kansai->getSlug(), $cards->first()->attr('href'), 'A goshuincho does not open its own page.');
    }

    public function test_the_index_holds_no_other_collectors_goshuincho(): void
    {
        $owner = UserFactory::createOne();
        GoshuinchoFactory::createOne(['owner' => $owner, 'title' => 'Not yours']);
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho');

        $this->assertStringNotContainsString('Not yours', $crawler->filter('main')->text(), 'A foreign goshuincho reached the index.');
    }

    public function test_the_index_narrows_to_the_searched_title(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kantō']);
        GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho?q=kans');

        $this->assertCount(1, $crawler->filter('main ul li a'), 'The search does not narrow the index.');
        $this->assertStringContainsString('Kansai', $crawler->filter('main ul li a')->text());
    }

    private function repository(): GoshuinchoRepository
    {
        $this->manager();

        return static::getContainer()->get(GoshuinchoRepository::class);
    }

    /**
     * @return list<Goshuincho>
     */
    private function ignoringOwnership(): array
    {
        return $this->unfiltered()->getRepository(Goshuincho::class)->findBy([], ['createdAt' => 'ASC']);
    }
}
