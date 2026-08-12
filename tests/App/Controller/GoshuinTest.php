<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Entity\Photo;
use App\Enum\GoshuinType;
use App\Enum\PhotoType;
use App\Repository\GoshuinRepository;
use App\Repository\PhotoRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GoshuinTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_a_place_and_a_photograph_are_enough(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[imageFile]' => $this->image(),
        ]);

        $this->assertResponseRedirects('/goshuincho/'.$goshuincho->getSlug());

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertNotNull($created, 'Two fields were not enough.');
        $this->assertNull($created->getReceivedOn(), 'A date was invented for a goshuin recorded without one.');
        $this->assertSame($goshuincho->getId(), $created->getGoshuincho()->getId(), 'The parent was not taken from the context.');
        $this->assertSame($user->getId(), $created->getOwner()->getId(), 'The owner was not assigned.');
        $this->assertSame(1, $created->getPosition(), 'No position was assigned.');

        $this->discard($created);
    }

    public function test_the_image_is_stored_in_its_four_columns(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
            'goshuin[imageFile]' => $this->image(),
        ]);

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $root = $this->uploadsDir();

        foreach ([$created->getImage(), $created->getImageMini(), $created->getImageCard(), $created->getImageFull()] as $path) {
            $this->assertNotNull($path, 'An image slot was left unrecorded.');
            $this->assertFileExists($root.'/'.$path, 'A recorded image path has no file behind it.');
        }

        $this->assertSame(384, getimagesize($root.'/'.$created->getImageCard())[0], 'The card column does not hold the card derivative.');

        $this->discard($created);
    }

    public function test_a_goshuin_without_a_photograph_is_refused(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $crawler = $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'A goshuin was accepted with no image.');
        $this->assertStringContainsString('A photograph is required.', $crawler->filter('main')->text(), 'The refusal did not say what was missing.');
        $this->assertSame(0, $this->repository()->count([]), 'A goshuin without an image reached the table.');
    }

    public function test_a_date_in_the_future_is_kept_as_it_was_entered(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => $tomorrow->format('Y-m-d'),
            'goshuin[imageFile]' => $this->image(),
        ]);

        $this->assertResponseRedirects();

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertNotNull($created, 'A date in the future was rejected.');
        $this->assertSame($tomorrow->format('Y-m-d'), $created->getReceivedOn()->format('Y-m-d'));

        $this->discard($created);
    }

    public function test_a_goshuin_without_a_date_shows_no_date_anywhere(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[imageFile]' => $this->image(),
        ]);

        $card = $this->client->followRedirect()
            ->filter('main ol li a')
            ->reduce(static fn (Crawler $node): bool => $node->filter('img.image-frame')->count() === 1)
            ->first()
        ;
        $this->assertSame('Fushimi Inari-taisha', trim($card->text()), 'The goshuincho page reserved room for a date that does not exist.');

        $page = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1))->filter('main');
        $this->assertStringNotContainsString('Received on', $page->text(), 'A row was labelled for a date that does not exist.');
        $this->assertStringContainsString('Page', $page->text(), 'The page does not state which page of the goshuincho it is.');

        $this->discard($this->repository()->findOneBy(['location' => $place->getId()]));
    }

    public function test_the_goshuincho_comes_from_the_context_and_stays_changeable(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'The one in context']);
        $other = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Somewhere else']);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $selected = $crawler->filter('#goshuin_goshuincho option[selected]');
        $this->assertCount(1, $selected, 'The goshuincho was asked for rather than taken from the context.');
        $this->assertSame($goshuincho->getId(), $selected->attr('value'));

        $this->client->submitForm('goshuin_submit', [
            'goshuin[goshuincho]' => $other->getId(),
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
            'goshuin[imageFile]' => $this->image(),
        ]);

        $this->assertResponseRedirects('/goshuincho/'.$other->getSlug(), Response::HTTP_FOUND, 'The context goshuincho could not be changed.');

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertSame($other->getId(), $created->getGoshuincho()->getId());

        $this->discard($created);
    }

    public function test_each_goshuincho_numbers_its_goshuin_from_one(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $other = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        $this->discard($this->collect($goshuincho));
        $this->discard($this->collect($goshuincho));
        $this->discard($this->collect($other));

        $this->assertSame([1, 2], $this->positions($goshuincho), 'A goshuincho did not number its goshuin from one without holes.');
        $this->assertSame([1], $this->positions($other), 'A second goshuincho carried on the first one\'s numbering.');
    }

    public function test_the_image_thumbnail_is_drawn_in_its_frame(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
            'goshuin[imageFile]' => $this->image(),
        ]);

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $crawler = $this->client->followRedirect();
        $thumbnail = $crawler->filter('main ol li img');

        $this->assertCount(1, $thumbnail, 'The goshuin was not drawn on the goshuincho page.');
        $this->assertSame('/uploads/'.$created->getImageCard(), $thumbnail->attr('src'), 'The thumbnail is not served from the card derivative.');
        $this->assertStringContainsString('image-frame', (string) $thumbnail->attr('class'), 'The thumbnail is not in its frame.');
        $this->assertNotSame('', (string) $thumbnail->attr('alt'), 'The thumbnail carries no alternative text.');
        $this->assertSame(
            $this->page($goshuincho, 1),
            $crawler->filter('main ol li a')->attr('href'),
            'The thumbnail does not open the page it stands for.',
        );

        $this->discard($created);
    }

    public function test_the_page_gives_the_goshuin_the_room_a_page_in_the_goshuincho_gives_it(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $goshuin = $this->collect($goshuincho, LocationFactory::createOne([
            'romanizedName' => 'Fushimi Inari-taisha',
            'japaneseName' => '伏見稲荷大社',
            'locality' => 'Fushimi-ku, Kyōto',
            'latitude' => 34.9671,
            'longitude' => 135.7727,
        ]));

        $crawler = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1));

        $this->assertResponseIsSuccessful();

        $image = $crawler->filter('main img');
        $this->assertCount(1, $image, 'The goshuin is not the one image on its own page.');
        $this->assertSame('/uploads/'.$goshuin->getImageFull(), $image->attr('src'), 'The page does not serve the image at size.');
        $this->assertStringContainsString('image-page', (string) $image->attr('class'), 'The image is not on its stage.');
        $this->assertStringContainsString('Fushimi Inari-taisha', (string) $image->attr('alt'), 'The image carries no meaningful alternative text.');

        $text = $crawler->filter('main')->text();
        $this->assertStringContainsString('伏見稲荷大社', $text);
        $this->assertStringContainsString('Fushimi-ku, Kyōto', $text);
        $this->assertStringContainsString('March 15, 2025', $text, 'The record does not read the date received back.');
        $this->assertStringContainsString('34.9671, 135.7727', $text, 'The record does not read the coordinates back.');

        $map = $crawler->filter('main [data-controller="map"]');
        $this->assertCount(1, $map, 'The location is not placed on a map.');
        $this->assertSame('numbered', $map->attr('data-map-mode-value'));
        $this->assertJsonStringEqualsJsonString(
            '[{"latitude":34.9671,"longitude":135.7727}]',
            (string) $map->attr('data-map-markers-value'),
            'The pin is not placed where the location is.',
        );
        $this->assertStringContainsString('Fushimi Inari-taisha', $map->filter('ul')->text(), 'The map has no readable equivalent.');

        $this->discard($goshuin);
    }

    public function test_each_optional_attribute_is_optional_on_its_own(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        foreach ([
            'goshuin[type]' => 'seasonal',
            'goshuin[price]' => '500',
            'goshuin[notes]' => "The climb.\nThe rain.",
        ] as $field => $value) {
            $place = LocationFactory::createOne();
            $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
            $this->client->submitForm('goshuin_submit', [
                'goshuin[location]' => $place->getId(),
                'goshuin[imageFile]' => $this->image(),
                $field => $value,
            ]);

            $this->assertResponseRedirects(null, Response::HTTP_FOUND, sprintf('A goshuin carrying only %s was refused.', $field));
            $this->discard($this->repository()->findOneBy(['location' => $place->getId()]));
        }
    }

    public function test_the_optional_attributes_are_stored_and_read_back(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[imageFile]' => $this->image(),
            'goshuin[type]' => 'seasonal',
            'goshuin[price]' => '500',
            'goshuin[notes]' => "The climb under the rain.\nThe counter halfway up.",
        ]);

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertSame(GoshuinType::Seasonal, $created->getType());
        $this->assertSame(500, $created->getPrice());
        $this->assertSame('JPY', $created->getCurrency(), 'The currency did not default to yen.');
        $this->assertSame("The climb under the rain.\nThe counter halfway up.", $created->getNotes(), 'The notes lost their line break.');

        $main = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1))->filter('main');
        $this->assertStringContainsString('Seasonal edition', $main->text(), 'The type is not read back.');
        $this->assertStringContainsString('¥500', $main->text(), 'The price is not read back.');
        $this->assertStringContainsString('The counter halfway up.', $main->text(), 'The notes are not read back.');
        $this->assertCount(1, $main->filter('br'), 'The notes lost their line break on the way out.');

        $this->discard($created);
    }

    public function test_the_form_opens_the_essentials_and_one_section_and_folds_the_rest(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        $folds = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add')->filter('main details');

        $this->assertGreaterThan(1, $folds->count(), 'The form has nothing folded away.');
        $this->assertCount(1, $folds->filter('[open]'), 'Either nothing or everything was left open.');
        $this->assertSame(
            ['The goshuin'],
            $folds->filter('[open] summary')->each(static fn (Crawler $summary): string => trim($summary->filter('span span')->first()->text())),
        );

        foreach ($folds->each(static fn (Crawler $fold): string => $fold->filter('[data-fold-target="state"]')->text()) as $state) {
            $this->assertSame('Empty', trim($state), 'A section claimed something was entered in an empty form.');
        }
    }

    public function test_a_section_says_it_is_filled_once_it_holds_something(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[imageFile]' => $this->createTextFile(),
            'goshuin[price]' => '500',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $states = $this->client->getCrawler()->filter('main details [data-fold-target="state"]')
            ->each(static fn (Crawler $state): string => trim($state->text()));

        $this->assertSame(['Filled', 'Empty', 'Empty'], $states, 'A section did not state what it holds after the form came back.');
    }

    public function test_the_form_measures_nothing_about_how_filled_it_is(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        $main = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add')->filter('main');

        $this->assertStringNotContainsString('<progress', $main->html(), 'A progress bar reached the form.');
        $this->assertStringNotContainsString('progressbar', $main->html(), 'A progress bar reached the form.');
        $this->assertStringNotContainsString('%', $main->text(), 'A share of something completed was rendered.');
        $this->assertDoesNotMatchRegularExpression('/\d+\s*\/\s*\d+/', $main->text(), 'A count against a total reached the form.');
    }

    public function test_photographs_land_in_their_set_with_their_labels(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);

        $this->create($goshuincho, $place, [
            'photo_add' => ['location' => [$this->shot(), $this->shot()], 'other' => [$this->shot()]],
            'photo_add_label' => ['location' => ['The torii', ''], 'other' => ['Omamori']],
        ]);

        $goshuin = $this->repository()->findOneBy(['location' => $place->getId()]);

        $this->assertSame([1, 2], $this->spots($goshuin, PhotoType::Location), 'The place photographs are not numbered from one.');
        $this->assertSame([1], $this->spots($goshuin, PhotoType::Other), 'The other set carried on the first one’s numbering.');
        $this->assertSame(['The torii', null], $this->captions($goshuin, PhotoType::Location), 'A label was lost or invented.');
        $this->assertSame(['Omamori'], $this->captions($goshuin, PhotoType::Other));

        foreach ($this->shots($goshuin) as $photo) {
            $this->assertNotNull($photo->getOwner(), 'A photograph landed without an owner of its own.');
            $this->assertFileExists($this->uploadsDir().'/'.$photo->getImageCard(), 'A photograph has no card derivative behind it.');
        }

        $this->scrap($goshuin);
    }

    public function test_the_page_shows_the_photographs_and_offers_nothing_to_change(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->create($goshuincho, $place, [
            'photo_add' => ['location' => [$this->shot()]],
            'photo_add_label' => ['location' => ['The torii']],
        ]);
        $goshuin = $this->repository()->findOneBy(['location' => $place->getId()]);

        $main = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1))->filter('main');

        $this->assertSame('The torii', trim($main->filter('figcaption')->text()), 'The label does not caption the photograph.');
        $this->assertSame('The torii', $main->filter('.shot img')->attr('alt'), 'The label is not the alternative text.');
        $this->assertCount(1, $main->filter('.shot'), 'A frame was reserved for a photograph that does not exist.');
        $this->assertCount(1, $main->filter('ol'), 'A set with nothing in it rendered a gallery anyway.');
        $this->assertCount(0, $main->filter('form, input, button'), 'The page offered something to change.');

        $this->scrap($goshuin);
    }

    public function test_a_file_that_is_not_a_photograph_is_refused_and_said_so(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);

        $this->create($goshuincho, $place, ['photo_add' => ['location' => [$this->createTextFile()]]]);

        $goshuin = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertSame([], $this->spots($goshuin, PhotoType::Location), 'A text file became a photograph.');
        $this->assertStringContainsString(
            'One photograph was refused.',
            $this->client->followRedirect()->filter('body')->text(),
            'The refusal was silent.',
        );

        $this->discard($goshuin);
    }

    public function test_the_type_of_a_photograph_never_comes_from_the_payload(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);

        $this->create($goshuincho, $place, [
            'photo_add' => ['location' => [$this->shot()]],
            'type' => 'other',
            'photo_type' => 'other',
        ]);

        $goshuin = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertCount(1, $this->photos()->ofType($goshuin, PhotoType::Location), 'The field name was not what decided the set.');
        $this->assertCount(0, $this->photos()->ofType($goshuin, PhotoType::Other), 'A payload chose the set.');

        $this->scrap($goshuin);
    }

    public function test_a_photograph_is_relabelled_reordered_and_removed_from_the_form(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->create($goshuincho, $place, [
            'photo_add' => ['location' => [$this->shot(), $this->shot(), $this->shot()]],
            'photo_add_label' => ['location' => ['first', 'second', 'third']],
        ]);
        $goshuin = $this->repository()->findOneBy(['location' => $place->getId()]);
        $ids = array_map(static fn (Photo $photo): string => $photo->getId(), $this->photos()->ofType($goshuin, PhotoType::Location));
        $doomed = $this->photos()->ofType($goshuin, PhotoType::Location)[1];
        $paths = [$doomed->getImage(), $doomed->getImageMini(), $doomed->getImageCard(), $doomed->getImageFull()];

        $this->correct($goshuincho, 1, [
            'photo_order' => ['location' => [$ids[2], $ids[0], $ids[1]]],
            'photo_label' => ['location' => [$ids[2] => 'the last one, first']],
            'photo_remove' => ['location' => [$ids[1]]],
        ]);

        $goshuin = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertSame(['the last one, first', 'first'], $this->captions($goshuin, PhotoType::Location), 'The new order or the new label did not take.');
        $this->assertSame([1, 2], $this->spots($goshuin, PhotoType::Location), 'Removing one left a hole in the set.');

        foreach ($paths as $path) {
            $this->assertNotNull($path);
            $this->assertFileDoesNotExist($this->uploadsDir().'/'.$path, 'A derivative of the removed photograph survived.');
        }

        $this->scrap($goshuin);
    }

    public function test_deleting_a_goshuin_takes_its_photographs_and_their_files(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->create($goshuincho, $place, [
            'photo_add' => ['location' => [$this->shot(), $this->shot()], 'other' => [$this->shot()]],
        ]);
        $goshuin = $this->repository()->findOneBy(['location' => $place->getId()]);
        $paths = array_map(static fn (Photo $photo): ?string => $photo->getImageCard(), $this->shots($goshuin));

        $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1).'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects();
        $this->assertSame(0, $this->photos()->count([]), 'The photographs outlived their goshuin.');

        foreach ($paths as $path) {
            $this->assertNotNull($path);
            $this->assertFileDoesNotExist($this->uploadsDir().'/'.$path, 'A photograph file outlived its goshuin.');
        }
    }

    public function test_the_page_states_which_one_of_how_many_it_is(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $collected = [$this->collect($goshuincho), $this->collect($goshuincho), $this->collect($goshuincho)];

        $crawler = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 2));

        $this->assertStringContainsString('Page 02 / 03', $crawler->filter('header')->text(), 'The page does not say which one of how many it is.');

        foreach ($collected as $goshuin) {
            $this->discard($goshuin);
        }
    }

    public function test_at_either_end_the_missing_direction_is_absent(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $collected = [$this->collect($goshuincho), $this->collect($goshuincho)];

        $first = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1));
        $this->assertCount(1, $first->filter('header a[href="'.$this->page($goshuincho, 2).'"]'), 'The first page does not offer the next one.');
        $this->assertCount(0, $first->filter('header a[href="'.$this->page($goshuincho, 0).'"]'), 'The first page offers a page before it.');

        $last = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 2));
        $this->assertCount(1, $last->filter('header a[href="'.$this->page($goshuincho, 1).'"]'), 'The last page does not offer the one before it.');
        $this->assertCount(0, $last->filter('header a[href="'.$this->page($goshuincho, 3).'"]'), 'The last page offers a page after it.');
        $this->assertCount(0, $last->filter('header [aria-disabled], header [disabled]'), 'A direction that does not exist was rendered as unavailable instead of left out.');

        foreach ($collected as $goshuin) {
            $this->discard($goshuin);
        }
    }

    public function test_a_goshuin_with_only_what_is_required_reads_as_a_complete_object(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $goshuin = $this->collect($goshuincho);

        $main = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1))->filter('main');
        $text = $main->text();

        $this->assertStringNotContainsString('Coordinates', $text, 'A row was labelled for a value that does not exist.');
        $this->assertCount(0, $main->filter('[data-controller="map"]'), 'A map was drawn for a place with no coordinates.');
        $this->assertSame($main->filter('dt')->count(), $main->filter('dd')->count(), 'A labelled row lost its value.');

        foreach ($main->filter('dd')->each(static fn (Crawler $value): string => trim($value->text())) as $value) {
            $this->assertNotSame('', $value, 'A row was rendered with nothing in it.');
        }

        foreach (['—', '–', '--', 'N/A'] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $text, 'A value that does not exist was stood in for.');
        }

        $this->discard($goshuin);
    }

    public function test_the_page_measures_nothing_about_how_filled_it_is(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $goshuin = $this->collect($goshuincho);

        $main = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1))->filter('main');

        $this->assertStringNotContainsString('<progress', $main->html(), 'A progress bar reached the page.');
        $this->assertStringNotContainsString('progressbar', $main->html(), 'A progress bar reached the page.');
        $this->assertStringNotContainsString('%', $main->text(), 'A share of something completed was rendered.');

        $this->discard($goshuin);
    }

    public function test_saving_and_adding_another_comes_back_to_an_empty_form_on_the_same_goshuincho(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_again', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
            'goshuin[price]' => '500',
            'goshuin[notes]' => 'The rain.',
            'goshuin[imageFile]' => $this->image(),
        ]);

        $this->assertResponseRedirects('/goshuincho/'.$goshuincho->getSlug().'/goshuin/add', Response::HTTP_FOUND, 'Saving and adding another left the form.');

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertNotNull($created, 'Saving and adding another did not save.');

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Fushimi Inari-taisha', $crawler->filter('body')->text(), 'The save was not confirmed.');

        $this->assertSame($goshuincho->getId(), $crawler->filter('#goshuin_goshuincho option[selected]')->attr('value'), 'The goshuincho was not retained.');
        $this->assertSame('', (string) $crawler->filter('#goshuin_receivedOn')->attr('value'), 'The date came back filled.');
        $this->assertSame('', (string) $crawler->filter('#goshuin_price')->attr('value'), 'The price came back filled.');
        $this->assertSame('', trim($crawler->filter('#goshuin_notes')->text()), 'The notes came back filled.');
        $this->assertCount(0, $crawler->filter('main [data-upload-target="preview"][src]'), 'The photograph came back with the form.');
        $this->assertCount(0, $crawler->filter('#goshuin_location option[selected], #goshuin_location [value]:not([value=""])'), 'The location came back chosen.');

        $this->discard($created);
    }

    public function test_saving_and_adding_another_follows_the_goshuincho_that_was_chosen(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $other = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_again', [
            'goshuin[goshuincho]' => $other->getId(),
            'goshuin[location]' => $place->getId(),
            'goshuin[imageFile]' => $this->image(),
        ]);

        $this->assertResponseRedirects('/goshuincho/'.$other->getSlug().'/goshuin/add', Response::HTTP_FOUND, 'The next form was opened on the wrong goshuincho.');

        $this->discard($this->repository()->findOneBy(['location' => $place->getId()]));
    }

    public function test_the_form_being_corrected_does_not_offer_to_add_another(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $goshuin = $this->collect($goshuincho);

        $add = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
        $this->assertCount(1, $add->filter('button[name="goshuin_again"]'), 'The add form does not offer to add another.');

        $edit = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1).'/edit');
        $this->assertCount(0, $edit->filter('button[name="goshuin_again"]'), 'A correction offered to add another.');

        $this->discard($goshuin);
    }

    public function test_pressing_return_saves_rather_than_asking_for_another(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        $submits = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add')
            ->filter('form button[type="submit"]')
            ->each(static fn (Crawler $button): string => (string) $button->attr('name'));

        $this->assertSame('goshuin_submit', $submits[0], 'The first submit in the document is not the plain save, so Return would ask for another goshuin.');
    }

    public function test_the_page_offers_its_own_form(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $goshuin = $this->collect($goshuincho);

        $edit = $this->page($goshuincho, 1).'/edit';
        $this->assertCount(
            1,
            $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1))->filter('header a[href="'.$edit.'"]'),
            'The page does not offer to correct itself.',
        );

        $this->assertResponseIsSuccessful();
        $this->client->request(Request::METHOD_GET, $edit);
        $this->assertResponseIsSuccessful();

        $this->discard($goshuin);
    }

    public function test_the_form_comes_back_with_its_values_and_keeps_the_image_it_already_has(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $goshuin = $this->collect($goshuincho, $place);
        $image = $goshuin->getImage();

        $crawler = $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1).'/edit');
        $this->assertSame('2025-03-15', $crawler->filter('#goshuin_receivedOn')->attr('value'), 'The form came back empty.');

        $this->client->submitForm('goshuin_submit', ['goshuin[price]' => '500']);

        $this->assertResponseRedirects($this->page($goshuincho, 1), Response::HTTP_FOUND, 'A goshuin that already has its image was asked for one again.');

        $saved = $this->repository()->find($goshuin->getId());
        $this->assertSame(500, $saved->getPrice(), 'The price was not saved.');
        $this->assertSame($image, $saved->getImage(), 'The image was replaced by nothing.');
        $this->assertSame(1, $saved->getPosition(), 'The position moved on a correction.');

        $this->discard($saved);
    }

    public function test_another_collector_cannot_correct_a_page_that_is_not_theirs(): void
    {
        $owner = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $owner]);
        $this->client->loginUser($owner);
        $goshuin = $this->collect($goshuincho);

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1).'/edit');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'A foreign page was open to correction.');

        $this->discard($goshuin);
    }

    public function test_deleting_a_goshuin_destroys_its_image_and_closes_the_gap(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $collected = $this->fill($goshuincho, ['Alpha', 'Beta', 'Gamma']);
        $removed = $collected[1];
        $paths = [$removed->getImage(), $removed->getImageMini(), $removed->getImageCard(), $removed->getImageFull()];
        $root = $this->uploadsDir();

        $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 2).'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects('/goshuincho/'.$goshuincho->getSlug());
        $this->assertSame(['Alpha', 'Gamma'], $this->order($goshuincho), 'The goshuincho page still shows the deleted goshuin.');
        $this->assertSame([1, 2], $this->positions($goshuincho), 'Deleting left a hole in the numbering.');

        foreach ($paths as $path) {
            $this->assertNotNull($path);
            $this->assertFileDoesNotExist($root.'/'.$path, 'A derivative of the deleted goshuin survived.');
        }

        $this->discard($collected[0]);
        $this->discard($collected[2]);
    }

    public function test_deleting_a_goshuin_leaves_its_location_alone(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']);
        $id = $place->getId();
        $this->client->loginUser($user);
        $this->collect($goshuincho, $place);

        $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1).'/delete');
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects();
        $this->assertNotNull(
            $this->manager()->getRepository(Location::class)->find($id),
            'Deleting a goshuin took its location with it.',
        );
    }

    public function test_the_page_asks_before_it_deletes(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);
        $goshuin = $this->collect($goshuincho, LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']));

        $delete = $this->page($goshuincho, 1).'/delete';
        $this->assertCount(
            1,
            $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1))->filter('header a[href="'.$delete.'"]'),
            'The page does not offer to remove itself.',
        );

        $crawler = $this->client->request(Request::METHOD_GET, $delete);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Fushimi Inari-taisha', $crawler->filter('main')->text(), 'The confirmation does not say what is about to go.');
        $this->assertSame(1, $this->repository()->count([]), 'Asking was enough to delete.');

        $this->discard($goshuin);
    }

    public function test_another_collector_cannot_delete_a_page_that_is_not_theirs(): void
    {
        $owner = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $owner]);
        $this->client->loginUser($owner);
        $goshuin = $this->collect($goshuincho);

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1).'/delete');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'A foreign page was open to deletion.');

        $this->discard($goshuin);
    }

    public function test_a_page_the_goshuincho_does_not_have_is_not_found(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'A page that was never filled answered.');
    }

    public function test_another_collector_cannot_open_a_page_that_is_not_theirs(): void
    {
        $owner = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $owner]);
        $this->client->loginUser($owner);
        $goshuin = $this->collect($goshuincho);

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, $this->page($goshuincho, 1));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'A foreign page was reachable.');

        $this->discard($goshuin);
    }

    public function test_another_collector_cannot_add_to_a_goshuincho_that_is_not_theirs(): void
    {
        $goshuincho = GoshuinchoFactory::createOne(['owner' => UserFactory::createOne()]);
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'A foreign goshuincho was reachable.');
    }

    private function collect(Goshuincho $goshuincho, ?Location $place = null): Goshuin
    {
        $place ??= LocationFactory::createOne();
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
            'goshuin[imageFile]' => $this->image(),
        ]);
        $this->assertResponseRedirects();

        return $this->repository()->findOneBy(['location' => $place->getId()]);
    }

    /**
     * @param list<string> $places
     *
     * @return list<Goshuin>
     */
    private function fill(Goshuincho $goshuincho, array $places): array
    {
        return array_map(
            fn (string $place): Goshuin => $this->collect($goshuincho, LocationFactory::createOne(['romanizedName' => $place])),
            $places,
        );
    }

    /**
     * @return list<string>
     */
    private function order(Goshuincho $goshuincho): array
    {
        return $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug())
            ->filter('main ol li img')
            ->each(static fn (Crawler $image): string => (string) $image->attr('alt'));
    }

    private function create(Goshuincho $goshuincho, Location $place, array $extra): void
    {
        $form = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add')
            ->selectButton('goshuin_submit')
            ->form()
        ;

        $form['goshuin[location]'] = $place->getId();
        $form['goshuin[imageFile]']->upload($this->image()->getPathname());

        $this->beyond($form, $extra);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function correct(Goshuincho $goshuincho, int $position, array $extra): void
    {
        $this->beyond(
            $this->client->request(Request::METHOD_GET, $this->page($goshuincho, $position).'/edit')
                ->selectButton('goshuin_submit')
                ->form(),
            $extra,
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function beyond(Form $form, array $extra): void
    {
        $sent = $form->getPhpValues();
        $uploaded = $form->getPhpFiles();

        foreach ($extra as $field => $value) {
            $sent[$field] = $value;
        }

        if (isset($sent['photo_add'])) {
            $uploaded['photo_add'] = $sent['photo_add'];
            unset($sent['photo_add']);
        }

        $this->client->request(Request::METHOD_POST, $form->getUri(), $sent, $uploaded);
        $this->assertResponseRedirects();
    }

    /**
     * @return list<int>
     */
    private function spots(Goshuin $goshuin, PhotoType $type): array
    {
        return array_map(
            static fn (Photo $photo): int => (int) $photo->getPosition(),
            $this->photos()->ofType($goshuin, $type),
        );
    }

    /**
     * @return list<?string>
     */
    private function captions(Goshuin $goshuin, PhotoType $type): array
    {
        return array_map(
            static fn (Photo $photo): ?string => $photo->getLabel(),
            $this->photos()->ofType($goshuin, $type),
        );
    }

    /**
     * @return list<Photo>
     */
    private function shots(Goshuin $goshuin): array
    {
        return array_merge(
            $this->photos()->ofType($goshuin, PhotoType::Location),
            $this->photos()->ofType($goshuin, PhotoType::Other),
        );
    }

    private function photos(): PhotoRepository
    {
        $this->manager();

        return static::getContainer()->get(PhotoRepository::class);
    }

    private function shot(): UploadedFile
    {
        return $this->createImage(600, 450);
    }

    private function scrap(Goshuin $goshuin): void
    {
        foreach ($this->shots($goshuin) as $photo) {
            $this->removeUploads($photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull());
        }

        $this->discard($goshuin);
    }

    private function page(Goshuincho $goshuincho, int $position): string
    {
        return '/goshuincho/'.$goshuincho->getSlug().'/goshuin/'.$position;
    }

    /**
     * @return list<int>
     */
    private function positions(Goshuincho $goshuincho): array
    {
        $id = $goshuincho->getId();

        return $this->repository()->positions($this->manager()->getRepository(Goshuincho::class)->find($id));
    }

    private function repository(): GoshuinRepository
    {
        $this->manager();

        return static::getContainer()->get(GoshuinRepository::class);
    }

    private function image(): UploadedFile
    {
        return $this->createImage(900, 1230);
    }

    private function discard(Goshuin $goshuin): void
    {
        $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
    }
}
