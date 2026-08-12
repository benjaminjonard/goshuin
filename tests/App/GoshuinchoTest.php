<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Entity\Goshuincho;
use App\Repository\GoshuinchoRepository;
use App\Service\ImageStore;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\IgnoresOwnership;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GoshuinchoTest extends WebTestCase
{
    use Factories;
    use IgnoresOwnership;
    use ResetDatabase;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_a_title_alone_creates_one(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Kyoto 2024']);

        $this->assertResponseRedirects();
        $created = $this->books()->findOneBy(['title' => 'Kyoto 2024']);
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
        $created = $this->books()->findOneBy(['title' => 'Nara']);
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
        $this->assertStringNotContainsString('yen, without a separator', $crawler->filter('main')->text(), 'The explanatory help text survived.');
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
        $created = $this->books()->findOneBy(['title' => 'Kyoto']);
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
        $this->assertNull($this->books()->findOneBy(['title' => 'Nowhere in particular'])->getBoughtAt());
    }

    public function test_the_identifier_is_a_uuid_v7_string(): void
    {
        $book = GoshuinchoFactory::createOne();

        $id = $book->getId();
        $this->assertSame(36, \strlen($id));
        $this->assertTrue(Uuid::isValid($id));
        $this->assertInstanceOf(UuidV7::class, Uuid::fromString($id));
    }

    public function test_the_owner_is_assigned_without_the_controller(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $userId = $user->getId();
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');

        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Unowned on submit']);

        $created = $this->books()->findOneBy(['title' => 'Unowned on submit']);
        $this->assertNotNull($created->getOwner(), 'The prePersist listener did not assign the owner.');
        $this->assertSame($userId, $created->getOwner()->getId());
    }

    public function test_two_users_may_name_a_book_the_same_thing(): void
    {
        $first = UserFactory::createOne();
        $secondId = UserFactory::createOne()->getId();

        $this->client->loginUser($first);
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Kumano Kodo']);
        $this->assertResponseRedirects();

        $this->client->loginUser($this->unfiltered()->getRepository(\App\Entity\User::class)->find($secondId));
        $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Kumano Kodo']);
        $this->assertResponseRedirects();

        $slugs = array_map(
            static fn (Goshuincho $book): string => $book->getSlug(),
            $this->booksIgnoringOwnership(),
        );

        $this->assertCount(2, $slugs);
        $this->assertSame(['kumano-kodo', 'kumano-kodo'], $slugs, 'The slug was scoped globally instead of per owner.');
    }

    public function test_one_user_naming_two_books_the_same_gets_distinct_slugs(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach ([1, 2] as $ignored) {
            $this->client->request(Request::METHOD_GET, '/goshuincho/add');
            $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Ise']);
            $this->assertResponseRedirects();
        }

        $slugs = array_map(
            static fn (Goshuincho $book): string => $book->getSlug(),
            $this->booksIgnoringOwnership(),
        );

        $this->assertCount(2, $slugs);
        $this->assertNotSame($slugs[0], $slugs[1], 'One owner ended up with two identical slugs.');
    }

    public function test_a_book_holding_no_seal_draws_nothing_but_the_statement(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Empty book']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$book->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertStringContainsString('No goshuin yet', $crawler->filter('main')->text(), 'The empty state did not state itself.');
        $this->assertCount(0, $crawler->filter('main figure'), 'A cover slot was drawn for a book with no cover.');
        $this->assertStringContainsString('No cover photographed', $crawler->filter('main')->text(), 'No stand-in was shown for the missing covers.');
        $this->assertCount(0, $crawler->filter('[data-controller="map"]'), 'An empty map was drawn.');
        $this->assertCount(0, $crawler->filter('main ol'), 'An empty sequence was drawn.');
        $this->assertCount(0, $crawler->filter('main dl'), 'A record with no values was drawn.');
        $this->assertStringNotContainsString('0', $crawler->filter('main')->text(), 'A zeroed statistic reached the page.');
    }

    public function test_a_purchase_record_appears_only_where_there_is_one(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'price' => 1500, 'currency' => 'JPY']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$book->getSlug());

        $this->assertCount(1, $crawler->filter('main dl'));
        $this->assertStringContainsString('1,500', $crawler->filter('main dl')->text());
        $this->assertCount(1, $crawler->filter('main dl > div'), 'An absent field produced a row.');
    }

    public function test_every_stored_field_can_be_changed(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Before']);
        $slug = $book->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'After',
            'goshuincho[purchasedAt]' => '2023-11-03',
            'goshuincho[price]' => '900',
            'goshuincho[description]' => 'Rebound.',
        ]);

        $this->assertResponseRedirects();
        $stored = $this->books()->findOneBy(['title' => 'After']);
        $this->assertNotNull($stored);
        $this->assertSame('2023-11-03', $stored->getPurchasedAt()->format('Y-m-d'));
        $this->assertSame(900, $stored->getPrice());
        $this->assertSame('Rebound.', $stored->getDescription());
    }

    public function test_deletion_goes_through_a_posted_form(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user]);
        $slug = $book->getSlug();
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/delete');
        $this->assertCount(1, $crawler->filter('form[method="post"]'));
        $this->assertCount(1, $crawler->filter('form input[name$="[_token]"]'), 'The delete form carries no CSRF token.');

        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects();
        $this->assertNull($this->books()->findOneBy(['slug' => $slug]), 'The Goshuincho survived its deletion.');
    }

    public function test_another_users_book_is_not_found(): void
    {
        $theirs = GoshuinchoFactory::createOne();
        $slug = $theirs->getSlug();
        $this->client->loginUser(UserFactory::createOne());

        foreach (['', '/edit', '/delete'] as $suffix) {
            $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.$suffix);
            $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, sprintf('%s exposed a foreign book.', $suffix ?: '/show'));
        }
    }

    public function test_a_hue_is_assigned_without_being_asked_for(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->assertCount(0, $crawler->filter('#goshuincho_hue'), 'Creation asked for a colour.');

        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Coloured on its own']);

        $created = $this->books()->findOneBy(['title' => 'Coloured on its own']);
        $this->assertNotNull($created->getHue(), 'No hue was assigned.');
        $this->assertGreaterThanOrEqual(0, $created->getHue());
        $this->assertLessThanOrEqual(360, $created->getHue());
    }

    public function test_consecutive_books_do_not_share_a_hue(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        foreach (['Ise', 'Nara', 'Koya', 'Nikko'] as $title) {
            $this->client->request(Request::METHOD_GET, '/goshuincho/add');
            $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => $title]);
        }

        $hues = array_map(
            static fn (Goshuincho $book): int => $book->getHue(),
            $this->booksIgnoringOwnership(),
        );

        $this->assertCount(4, $hues);
        $this->assertSame($hues, array_unique($hues), 'Two books share a hue.');
    }

    public function test_adding_a_book_recolours_nothing(): void
    {
        $user = UserFactory::createOne();
        $first = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'First', 'hue' => 200]);
        $firstId = $first->getId();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/goshuincho/add');
        $this->client->submitForm('goshuincho_submit', ['goshuincho[title]' => 'Second']);

        $this->assertSame(200, $this->books()->find($firstId)->getHue(), 'Adding a book recoloured an existing one.');
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
        $this->assertSame(300, $this->books()->findOneBy(['slug' => $mineSlug])->getHue());
        $this->assertSame(200, $this->books()->find($otherId)->getHue(), 'Editing one hue moved another.');
    }

    public function test_the_hue_reaches_the_swatch_as_an_angle_only(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'hue' => 137]);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$book->getSlug().'/edit');

        $swatch = $crawler->filter('[data-hue-target="preview"]');
        $this->assertCount(1, $swatch, 'The colour is not shown.');
        $style = $swatch->attr('style');
        $this->assertSame('--hue: 137', $style);
        $this->assertStringNotContainsString('#', $style, 'A hex colour reached the page.');
        $this->assertStringNotContainsString('oklch', $style, 'The colour was computed outside the stylesheet.');
    }

    public function test_the_book_page_carries_no_second_header(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kumano Kodo', 'price' => 1500]);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$book->getSlug());

        $this->assertCount(1, $crawler->filter('h1'), 'The title appears more than once.');
        $this->assertStringNotContainsString('Kumano Kodo', $crawler->filter('main')->text(), 'The bar title is repeated inside the page.');
        $this->assertCount(0, $crawler->filter('main .hue-fill'), 'The book page painted a hue the design does not use.');
    }

    public function test_the_hue_column_holds_nothing_but_an_angle(): void
    {
        $metadata = static::getContainer()->get('doctrine')->getManager()->getClassMetadata(Goshuincho::class);

        $this->assertSame('integer', $metadata->getTypeOfField('hue'));

        foreach (['colour', 'color', 'hex', 'palette', 'swatch'] as $forbidden) {
            $this->assertNotContains($forbidden, $metadata->getColumnNames(), sprintf('%s was given a column.', $forbidden));
        }
    }

    public function test_an_uploaded_cover_is_stored_with_its_derivatives(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'With a cover']);
        $slug = $book->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'With a cover',
            'goshuincho[coverFrontFile]' => $this->photograph(),
        ]);

        $this->assertResponseRedirects();
        $stored = $this->books()->findOneBy(['slug' => $slug]);
        $this->assertNotNull($stored->getCoverFront(), 'The cover was not recorded on the goshuincho.');

        $root = static::getContainer()->getParameter('app.uploads_dir');
        $this->assertFileExists($root.'/'.$stored->getCoverFront(), 'The original is not on disk.');

        foreach (ImageStore::WIDTHS as $width) {
            $this->assertFileExists($root.'/'.$this->images()->derivative($stored->getCoverFront(), $width), sprintf('The %d px derivative is missing.', $width));
        }

        $this->images()->remove($stored->getCoverFront());
    }

    public function test_a_refused_upload_keeps_the_other_fields_and_stores_nothing(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Untouched']);
        $slug = $book->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $crawler = $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'Renamed in the same submission',
            'goshuincho[coverFrontFile]' => $this->notAnImage(),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'A text file was accepted as a cover.');
        $this->assertStringContainsString('JPEG, PNG and WebP', $crawler->filter('main')->text(), 'The refusal did not name what is accepted.');
        $this->assertSame('Renamed in the same submission', $crawler->filter('#goshuincho_title')->attr('value'), 'The other fields were lost with the refusal.');

        $stored = $this->books()->findOneBy(['slug' => $slug]);
        $this->assertNull($stored->getCoverFront(), 'A refused upload was recorded.');
        $this->assertSame('Untouched', $stored->getTitle(), 'A refused submission was persisted anyway.');
    }

    public function test_a_cover_can_be_removed_on_its_own(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Two covers', 'coverFront' => 'ab/cd/front.jpg', 'coverBack' => 'ab/cd/back.jpg']);
        $slug = $book->getSlug();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$slug.'/edit');

        $this->client->submitForm('goshuincho_submit', [
            'goshuincho[title]' => 'Two covers',
            'goshuincho[removeCoverFront]' => true,
        ]);

        $this->assertResponseRedirects();
        $stored = $this->books()->findOneBy(['slug' => $slug]);
        $this->assertNull($stored->getCoverFront(), 'The front cover was not removed.');
        $this->assertSame('ab/cd/back.jpg', $stored->getCoverBack(), 'Removing one cover took the other with it.');
    }

    private function photograph(): UploadedFile
    {
        $image = imagecreatetruecolor(900, 600);
        imagefilledrectangle($image, 0, 0, 900, 600, imagecolorallocate($image, 180, 90, 70));
        $path = sys_get_temp_dir().'/cover-'.bin2hex(random_bytes(5)).'.jpg';
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, 'cover.jpg', 'image/jpeg', test: true);
    }

    private function notAnImage(): UploadedFile
    {
        $path = sys_get_temp_dir().'/note-'.bin2hex(random_bytes(5)).'.txt';
        file_put_contents($path, 'not a photograph');

        return new UploadedFile($path, 'note.txt', 'text/plain', test: true);
    }

    private function images(): ImageStore
    {
        return static::getContainer()->get(ImageStore::class);
    }

    public function test_only_the_covers_that_exist_are_drawn(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'coverFront' => 'ab/cd/front.jpg']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$book->getSlug());

        $this->assertCount(1, $crawler->filter('main figure'), 'A slot was drawn for the missing back cover.');
        $this->assertStringContainsString('/uploads/ab/cd/front-1200.jpg', $crawler->filter('main figure img')->attr('src'), 'The cover is not served from its derivative.');
        $this->assertStringNotContainsString('No cover photographed', $crawler->filter('main')->text(), 'The stand-in appeared beside a real cover.');
    }

    public function test_both_covers_are_drawn_as_two_fixed_slots(): void
    {
        $user = UserFactory::createOne();
        $book = GoshuinchoFactory::createOne(['owner' => $user, 'coverFront' => 'ab/cd/front.jpg', 'coverBack' => 'ab/cd/back.jpg']);
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$book->getSlug());

        $this->assertCount(2, $crawler->filter('main figure'));
        $this->assertSame(['Front cover', 'Back cover'], $crawler->filter('main figcaption')->each(fn ($node) => $node->text()), 'The two slots are not fixed and captioned.');
    }

    public function test_no_derived_attribute_has_a_column(): void
    {
        $metadata = static::getContainer()->get('doctrine')->getManager()->getClassMetadata(Goshuincho::class);
        $columns = $metadata->getColumnNames();

        foreach (['period', 'region', 'spread', 'seal_count', 'goshuin_count', 'location_count', 'spend', 'total'] as $derived) {
            $this->assertNotContains($derived, $columns, sprintf('%s was given a column.', $derived));
        }
    }

    private function books(): GoshuinchoRepository
    {
        $container = static::getContainer();
        $container->get('doctrine')->getManager()->clear();

        return $container->get(GoshuinchoRepository::class);
    }

    /**
     * @return list<Goshuincho>
     */
    private function booksIgnoringOwnership(): array
    {
        return $this->unfiltered()->getRepository(Goshuincho::class)->findBy([], ['createdAt' => 'ASC']);
    }
}
