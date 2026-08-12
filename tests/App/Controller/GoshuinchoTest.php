<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuincho;
use App\Entity\User;
use App\Entity\Goshuin;
use App\Repository\GoshuinRepository;
use App\Repository\GoshuinchoRepository;
use App\Tests\AppTestCase;
use Symfony\Component\DomCrawler\Crawler;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
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

    public function test_the_price_field_carries_the_currency_symbol(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->assertCount(1, $crawler->filter('#goshuincho_price'), 'Creation cannot record a price.');
        $this->assertCount(1, $crawler->filter('#goshuincho_price')->ancestors()->filter('div')->first()->filter('use[href="#i-yen"]'), 'The price field shows no currency symbol.');
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

    public function test_a_goshuincho_holding_no_goshuin_draws_nothing_but_the_statement(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Nothing in it']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertStringContainsString('No goshuin yet', $crawler->filter('main')->text(), 'The empty state did not state itself.');
        $this->assertCount(0, $crawler->filter('main figure'), 'A cover slot was drawn for a goshuincho with no cover.');
        $this->assertStringContainsString('No cover photographed', $crawler->filter('main')->text(), 'No stand-in was shown for the missing covers.');
        $this->assertCount(0, $crawler->filter('[data-controller="map"]'), 'An empty map was drawn.');
        $this->assertCount(0, $crawler->filter('main ol'), 'An empty sequence was drawn.');
        $this->assertCount(0, $crawler->filter('main dl'), 'A record with no values was drawn.');
    }

    public function test_a_purchase_record_appears_only_where_there_is_one(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'price' => 1500, 'currency' => 'JPY']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertCount(1, $crawler->filter('main dl'));
        $this->assertStringContainsString('1,500', $crawler->filter('main dl')->text());
        $this->assertCount(1, $crawler->filter('main dl > div'), 'An absent field produced a row.');
    }

    public function test_the_goshuincho_page_carries_no_second_header(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kumano Kodo', 'price' => 1500]);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertCount(1, $crawler->filter('h1'), 'The title appears more than once.');
        $this->assertStringNotContainsString('Kumano Kodo', $crawler->filter('main')->text(), 'The bar title is repeated inside the page.');
        $this->assertCount(0, $crawler->filter('main .hue-fill'), 'The goshuincho page painted a hue the design does not use.');
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

    public function test_consecutive_goshuincho_do_not_share_a_hue(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['Ise', 'Nara', 'Koya', 'Nikko'] as $title) {
            $this->client->request(Request::METHOD_GET, '/goshuincho/add');
            $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => $title]);
        }

        $hues = array_map(
            static fn (Goshuincho $goshuincho): int => $goshuincho->getHue(),
            $this->ignoringOwnership(),
        );

        $this->assertCount(4, $hues);
        $this->assertSame($hues, array_unique($hues), 'Two goshuincho share a hue.');
    }

    public function test_adding_a_goshuincho_recolours_nothing(): void
    {
        $user = UserFactory::createOne();
        $first = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'First', 'hue' => 200]);
        $firstId = $first->getId();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Second']);

        $this->assertSame(200, $this->repository()->find($firstId)->getHue(), 'Adding a goshuincho recoloured an existing one.');
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

    public function test_the_hue_reaches_the_swatch_as_an_angle_only(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'hue' => 137]);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/edit');

        $swatch = $crawler->filter('[data-hue-target="preview"]');
        $this->assertCount(1, $swatch, 'The colour is not shown.');
        $this->assertSame('--hue: 137', $swatch->attr('style'));
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

    public function test_only_the_covers_that_exist_are_drawn(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'coverFront' => 'ab/cd/front.jpg', 'coverFrontFull' => 'ab/cd/front-1200.jpg']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertCount(1, $crawler->filter('main figure'), 'A slot was drawn for the missing back cover.');
        $this->assertStringContainsString('/uploads/ab/cd/front-1200.jpg', $crawler->filter('main figure img')->attr('src'), 'The cover is not served from its derivative.');
        $this->assertStringNotContainsString('No cover photographed', $crawler->filter('main')->text(), 'The stand-in appeared beside a real cover.');
    }

    public function test_both_covers_are_drawn_as_two_fixed_slots(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne([
            'owner' => $user,
            'coverFront' => 'ab/cd/front.jpg',
            'coverFrontFull' => 'ab/cd/front-1200.jpg',
            'coverBack' => 'ab/cd/back.jpg',
            'coverBackFull' => 'ab/cd/back-1200.jpg',
        ]);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug());

        $this->assertCount(2, $crawler->filter('main figure'));
        $this->assertSame(['Front cover', 'Back cover'], $crawler->filter('main figcaption')->each(fn ($node) => $node->text()), 'The two slots are not fixed and captioned.');
    }

    public function test_the_form_puts_the_goshuin_back_in_the_order_it_submits(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $slug = (string) $goshuincho->getSlug();
        $this->client->loginUser($user);

        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $place = LocationFactory::createOne(['romanizedName' => $name]);
            $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/goshuin/add');
            $this->client->submitForm('goshuin_submit', [
                'goshuin[location]' => $place->getId(),
                'goshuin[imageFile]' => $this->createImage(900, 1230),
            ]);
        }

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');
        $rows = $crawler->filter('[data-order-target="row"]');

        $this->assertCount(3, $rows, 'The form does not list the goshuin to order.');
        $this->assertSame(
            ['Alpha', 'Beta', 'Gamma'],
            $rows->each(static fn (Crawler $row): string => trim($row->filter('span')->text())),
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

        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
        }
    }

    public function test_a_goshuincho_holding_one_goshuin_is_not_asked_to_order_it(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $slug = (string) $goshuincho->getSlug();
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/goshuin/add');
        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[imageFile]' => $this->createImage(900, 1230),
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->assertCount(0, $crawler->filter('[data-order-target="row"]'), 'A goshuincho with one goshuin offered to reorder it.');

        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
        }
    }

    public function test_the_page_costs_the_same_number_of_queries_at_five_goshuin_and_at_fifty(): void
    {
        $user = UserFactory::createOne();
        $small = $this->filled($user, 5);
        $large = $this->filled($user, 50);

        $this->assertSame(4, $this->queries($small), 'The page no longer costs the four queries it is allowed.');
        $this->assertSame(
            4,
            $this->queries($large),
            'The number of queries grows with the number of goshuin, which is an N+1 the day someone adds a fiftieth.',
        );

        $this->emptyUploads();
    }

    public function test_the_derived_attributes_are_read_back_from_the_goshuin(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai']);
        $slug = (string) $goshuincho->getSlug();
        $this->client->loginUser($user);

        foreach ([
            ['Fushimi Inari-taisha', '2025-03-14', 500, 34.9671, 135.7727],
            ['Kiyomizu-dera', '2025-03-14', 300, 34.9949, 135.7850],
            ['Tōdai-ji', '2025-03-21', 500, 34.6889, 135.8398],
        ] as [$name, $day, $price, $latitude, $longitude]) {
            $place = LocationFactory::createOne([
                'romanizedName' => $name,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
            $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/goshuin/add');
            $this->client->submitForm('goshuin_submit', [
                'goshuin[location]' => $place->getId(),
                'goshuin[receivedOn]' => $day,
                'goshuin[price]' => (string) $price,
                'goshuin[imageFile]' => $this->createImage(900, 1230),
            ]);
        }

        $main = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug)->filter('main');
        $text = $main->text();

        $this->assertStringContainsString('3', $text, 'The goshuin are not counted.');
        $this->assertStringContainsString('¥1,300', $text, 'The spend was not summed from the goshuin.');
        $this->assertStringContainsString('関西 Kansai', $text, 'The region was not derived from the coordinates.');
        $this->assertStringContainsString('March 2025', $text, 'The period was not derived from the dates.');
        $this->assertStringContainsString('8 days', $text, 'The eight days between the first and the last were not counted.');
        $this->assertStringContainsString('The same day', $text, 'Two goshuin received the same day are not marked as such.');
        $this->assertStringContainsString('7 days later', $text, 'The interval between two goshuin is not named.');
        $this->assertStringContainsString('3 pins', $text, 'The map does not say how many pins it carries.');

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

        $this->emptyUploads();
    }

    public function test_a_goshuincho_with_no_goshuin_derives_nothing_and_says_nothing(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        $main = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug())->filter('main');

        $this->assertCount(0, $main->filter('[data-controller="map"]'), 'A map was drawn for a goshuincho with nowhere to point.');
        $this->assertStringNotContainsString('The trip', $main->text(), 'A trip was named for a goshuincho holding nothing.');
        $this->assertStringNotContainsString('spent', $main->text(), 'A spend was stated for a goshuincho holding nothing.');
        $this->assertStringNotContainsString('0', $main->text(), 'A count of nothing was rendered.');
    }

    private function filled(User $user, int $count): string
    {
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $slug = (string) $goshuincho->getSlug();
        $this->client->loginUser($user);

        for ($taken = 1; $taken <= $count; ++$taken) {
            $place = LocationFactory::createOne(['latitude' => 34.9 + $taken / 100, 'longitude' => 135.7 + $taken / 100]);
            $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/goshuin/add');
            $this->client->submitForm('goshuin_submit', [
                'goshuin[location]' => $place->getId(),
                'goshuin[receivedOn]' => (new \DateTimeImmutable('2025-03-01'))->modify('+'.$taken.' days')->format('Y-m-d'),
                'goshuin[price]' => '500',
                'goshuin[imageFile]' => $this->createImage(600, 820),
            ]);
        }

        return $slug;
    }

    private function queries(string $slug): int
    {
        $this->client->enableProfiler();
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug);

        $this->assertResponseIsSuccessful();

        return $this->client->getProfile()->getCollector('db')->getQueryCount();
    }

    private function emptyUploads(): void
    {
        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
        }
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
