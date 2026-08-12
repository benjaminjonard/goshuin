<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Repository\GoshuinRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GoshuinTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_a_place_a_date_and_a_photograph_are_enough(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne(['romanizedName' => 'Fushimi Inari-taisha']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
            'goshuin[imageFile]' => $this->image(),
        ]);

        $this->assertResponseRedirects('/goshuincho/'.$goshuincho->getSlug());

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertNotNull($created, 'Three fields were not enough.');
        $this->assertSame('2025-03-15', $created->getReceivedOn()->format('Y-m-d'));
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

    public function test_a_date_in_the_future_is_kept_and_warned_about(): void
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
        $crawler = $this->client->followRedirect();

        $created = $this->repository()->findOneBy(['location' => $place->getId()]);
        $this->assertNotNull($created, 'A future date was rejected instead of warned about.');
        $this->assertSame($tomorrow->format('Y-m-d'), $created->getReceivedOn()->format('Y-m-d'));
        $this->assertStringContainsString('This date is in the future.', $crawler->filter('body')->text(), 'No warning was raised.');

        $this->discard($created);
    }

    public function test_a_date_today_raises_no_warning(): void
    {
        $user = UserFactory::createOne();
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user]);
        $place = LocationFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'goshuin[imageFile]' => $this->image(),
        ]);

        $crawler = $this->client->followRedirect();

        $this->assertStringNotContainsString('This date is in the future.', $crawler->filter('body')->text(), 'Today was called the future.');

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

        $this->collect($goshuincho);
        $this->collect($goshuincho);
        $this->collect($other);

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

        $this->discard($created);
    }

    public function test_another_collector_cannot_add_to_a_goshuincho_that_is_not_theirs(): void
    {
        $goshuincho = GoshuinchoFactory::createOne(['owner' => UserFactory::createOne()]);
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'A foreign goshuincho was reachable.');
    }

    private function collect(Goshuincho $goshuincho): void
    {
        $place = LocationFactory::createOne();
        $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add');
        $this->client->submitForm('goshuin_submit', [
            'goshuin[location]' => $place->getId(),
            'goshuin[receivedOn]' => '2025-03-15',
            'goshuin[imageFile]' => $this->image(),
        ]);
        $this->assertResponseRedirects();

        $this->discard($this->repository()->findOneBy(['location' => $place->getId()]));
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
