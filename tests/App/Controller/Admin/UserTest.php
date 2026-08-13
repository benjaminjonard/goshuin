<?php

declare(strict_types=1);

namespace App\Tests\App\Controller\Admin;

use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Entity\User;
use App\Repository\GoshuinRepository;
use App\Repository\LocationRepository;
use App\Repository\PhotoRepository;
use App\Repository\UserRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\GoshuinchoFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return list<array{string, string}>
     */
    public static function routes(): array
    {
        return [
            [Request::METHOD_GET, '/admin/users'],
            [Request::METHOD_GET, '/admin/users/add'],
            [Request::METHOD_GET, '/admin/users/%s/edit'],
            [Request::METHOD_GET, '/admin/users/%s/delete'],
        ];
    }

    public function test_every_administration_route_refuses_a_plain_user(): void
    {
        $plain = UserFactory::createOne();
        $target = UserFactory::new()->admin()->create();
        $targetId = $target->getId();
        $this->client->loginUser($plain);

        foreach (self::routes() as [$method, $path]) {
            $this->client->request($method, sprintf($path, $targetId));
            $this->assertResponseStatusCodeSame(
                Response::HTTP_FORBIDDEN,
                sprintf('%s did not refuse a user without ROLE_ADMIN.', $path),
            );
        }
    }

    public function test_the_administration_area_shows_every_user(): void
    {
        $admin = UserFactory::new()->admin()->create();
        UserFactory::createMany(3);
        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/users');

        $this->assertResponseIsSuccessful();
        $this->assertCount(4, $crawler->filter('main ul > li'), 'The ownership filter hid users from the administrator.');
    }

    public function test_an_administrator_creates_an_account(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $this->client->request(Request::METHOD_GET, '/admin/users/add');

        $this->client->submitForm('user_submit', [
            'user[name]' => 'Invited',
            'user[email]' => 'invited@example.com',
            'user[plainPassword][first]' => 'a-long-enough-password',
            'user[plainPassword][second]' => 'a-long-enough-password',
            'user[enabled]' => true,
        ]);

        $this->assertResponseRedirects();
        $created = $this->users()->findOneBy(['email' => 'invited@example.com']);
        $this->assertNotNull($created);
        $this->assertNotSame('a-long-enough-password', $created->getPassword(), 'The password was stored in clear.');
        $this->assertFalse($created->isAdmin());
    }

    public function test_an_edit_without_a_password_keeps_the_current_one(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $target = UserFactory::createOne(['name' => 'Before']);
        $targetId = $target->getId();
        $hashed = $target->getPassword();
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/edit', $targetId));

        $this->client->submitForm('user_submit', [
            'user[name]' => 'After',
            'user[email]' => $target->getEmail(),
            'user[enabled]' => true,
        ]);

        $this->assertResponseRedirects();
        $stored = $this->users()->find($targetId);
        $this->assertSame('After', $stored->getName());
        $this->assertSame($hashed, $stored->getPassword(), 'An empty password field wiped the password.');
    }

    public function test_an_administrator_sets_a_password_from_its_own_form(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $target = UserFactory::createOne();
        $targetId = $target->getId();
        $hashed = $target->getPassword();
        $this->client->loginUser($admin);
        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/edit', $targetId));

        $this->assertCount(2, $crawler->filter('main form'), 'The password is not on a form of its own.');
        $this->assertCount(0, $crawler->filter('form[name="user"] input[type="password"]'), 'The account form still carries a password.');

        $this->client->submitForm('password_submit', [
            'password_set[plainPassword][first]' => 'a-brand-new-password',
            'password_set[plainPassword][second]' => 'a-brand-new-password',
        ]);

        $this->assertResponseRedirects();
        $stored = $this->users()->find($targetId);
        $this->assertNotSame($hashed, $stored->getPassword(), 'The password was not changed.');
        $this->assertNotSame('a-brand-new-password', $stored->getPassword(), 'The password was stored in clear.');
    }

    public function test_a_disabled_account_keeps_its_data(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $target = UserFactory::createOne();
        $targetId = $target->getId();
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/edit', $targetId));

        $this->client->submitForm('user_submit', [
            'user[name]' => $target->getName(),
            'user[email]' => $target->getEmail(),
            'user[enabled]' => false,
        ]);

        $this->assertResponseRedirects();
        $stored = $this->users()->find($targetId);
        $this->assertNotNull($stored, 'Disabling an account destroyed it.');
        $this->assertFalse($stored->isEnabled());
    }

    public function test_the_last_administrator_cannot_be_stripped_of_the_role(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $adminId = $admin->getId();
        UserFactory::createMany(2);
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/edit', $adminId));

        $this->client->submitForm('user_submit', [
            'user[name]' => $admin->getName(),
            'user[email]' => $admin->getEmail(),
            'user[admin]' => false,
            'user[enabled]' => true,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'Stripping the last administrator was accepted.');
        $this->assertStringContainsString('administrator', $this->client->getCrawler()->filter('main')->text(), 'No reason was stated.');
        $stored = $this->users()->find($adminId);
        $this->assertTrue($stored->isAdmin(), 'The instance lost its last administrator.');
    }

    public function test_the_last_administrator_cannot_be_disabled(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $adminId = $admin->getId();
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/edit', $adminId));

        $this->client->submitForm('user_submit', [
            'user[name]' => $admin->getName(),
            'user[email]' => $admin->getEmail(),
            'user[admin]' => true,
            'user[enabled]' => false,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'Disabling the last administrator was accepted.');
        $stored = $this->users()->find($adminId);
        $this->assertTrue($stored->isEnabled(), 'The instance lost access to its last administrator.');
    }

    public function test_the_last_administrator_cannot_be_deleted(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $adminId = $admin->getId();
        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/delete', $adminId));

        $this->assertGreaterThan(0, $crawler->filter('[role="alert"]')->count(), 'Deleting the last administrator was offered.');
        $this->assertCount(0, $crawler->filter('form[method="post"]'), 'A confirmation form was still shown.');
        $this->assertNotNull($this->users()->find($adminId));
    }

    public function test_an_administrator_deletes_an_account(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $target = UserFactory::createOne();
        $targetId = $target->getId();
        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/delete', $targetId));

        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects();
        $this->assertNull($this->users()->find($targetId), 'The account survived its deletion.');
    }

    public function test_the_edit_page_states_what_the_account_holds(): void
    {
        $adminId = UserFactory::new()->admin()->create()->getId();
        $targetId = UserFactory::createOne(['name' => 'Departing'])->getId();

        $target = $this->anyone($targetId);
        $this->client->loginUser($target);
        $this->collect($target);

        $this->client->loginUser($this->anyone($adminId));
        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/edit', $targetId));

        $rows = $crawler->filter('main dl > div')->each(
            static fn (Crawler $row): string => preg_replace('/\s+/', ' ', trim($row->text())),
        );

        $this->assertSame(
            ['Goshuincho 1', 'Goshuin 2', 'Photographs 1'],
            $rows,
            'The edit page does not state what the account holds.',
        );

        $this->scrap();
    }

    public function test_an_account_holding_nothing_states_no_figures(): void
    {
        $admin = UserFactory::new()->admin()->create();
        $target = UserFactory::createOne();
        $targetId = $target->getId();
        $this->client->loginUser($admin);

        $crawler = $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/edit', $targetId));

        $this->assertCount(0, $crawler->filter('main dl'), 'An empty account was given a count of nothing.');
    }

    public function test_the_confirmation_names_what_will_be_destroyed(): void
    {
        $adminId = UserFactory::new()->admin()->create()->getId();
        $targetId = UserFactory::createOne(['name' => 'Departing', 'email' => 'departing@example.com'])->getId();

        $target = $this->anyone($targetId);
        $this->client->loginUser($target);
        $this->collect($target);

        $this->client->loginUser($this->anyone($adminId));
        $body = $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/delete', $targetId))->filter('main')->text();

        $this->assertStringContainsString('Departing', $body);
        $this->assertStringContainsString('departing@example.com', $body);
        $this->assertStringContainsString('1 goshuincho', $body, 'The confirmation does not count the goshuincho it will destroy.');
        $this->assertStringContainsString('2 goshuin', $body, 'The confirmation does not count the goshuin it will destroy.');
        $this->assertStringContainsString('1 photograph', $body, 'The confirmation does not count the photographs it will destroy.');

        $this->scrap();
    }

    public function test_deleting_an_account_destroys_its_collection_and_its_files_and_keeps_the_locations(): void
    {
        $adminId = UserFactory::new()->admin()->create()->getId();
        $targetId = UserFactory::createOne()->getId();
        $placeId = LocationFactory::createOne(['romanizedName' => 'Kiyomizu-dera'])->getId();

        $target = $this->anyone($targetId);
        $this->client->loginUser($target);
        $paths = $this->collect($target, $this->locations()->find($placeId));

        foreach ($paths as $path) {
            $this->assertFileExists($this->uploadsDir().'/'.$path, 'The fixture did not store its images.');
        }

        $this->client->loginUser($this->anyone($adminId));
        $this->client->request(Request::METHOD_GET, sprintf('/admin/users/%s/delete', $targetId));
        $this->client->submitForm('delete_submit');

        $this->assertResponseRedirects();
        $this->assertNull($this->users()->find($targetId), 'The account survived its deletion.');

        foreach (['gos_goshuincho', 'gos_goshuin', 'gos_photo'] as $table) {
            $this->assertSame(
                0,
                (int) $this->connection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE owner_id = :id', $table), ['id' => $targetId]),
                sprintf('Rows were left behind in %s.', $table),
            );
        }

        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($this->uploadsDir().'/'.$path, 'An image file outlived the account it belonged to.');
        }

        $this->assertSame(
            1,
            (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM gos_location WHERE id = :id', ['id' => $placeId]),
            'A shared location was destroyed with the account.',
        );
    }

    /**
     * @return list<string>
     */
    private function collect(User $user, ?Location $place = null): array
    {
        $goshuincho = GoshuinchoFactory::createOne(['owner' => $user, 'title' => 'Kansai, spring 2025']);
        $place ??= LocationFactory::createOne();

        $this->receive($goshuincho, $place, true);
        $this->receive($goshuincho, LocationFactory::createOne(), false);

        $paths = [];

        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $paths = array_merge($paths, [$goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull()]);

            foreach (static::getContainer()->get(PhotoRepository::class)->findBy(['goshuin' => $goshuin]) as $photo) {
                $paths = array_merge($paths, [$photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull()]);
            }
        }

        return array_values(array_filter($paths));
    }

    private function receive(Goshuincho $goshuincho, Location $place, bool $photographed): void
    {
        $form = $this->client->request(Request::METHOD_GET, '/goshuincho/'.$goshuincho->getSlug().'/goshuin/add')
            ->selectButton('goshuin_submit')
            ->form()
        ;

        $form['goshuin[location]'] = $place->getId();
        $form['goshuin[imageFile]']->upload($this->createImage(900, 1230)->getPathname());

        $sent = $form->getPhpValues();
        $files = $form->getPhpFiles();

        if ($photographed) {
            $sent['photo_add_label'] = ['location' => ['The torii']];
            $files['photo_add'] = ['location' => [$this->createImage(600, 450)]];
        }

        $this->client->request(Request::METHOD_POST, $form->getUri(), $sent, $files);
        $this->assertResponseRedirects();
    }

    private function scrap(): void
    {
        foreach (static::getContainer()->get(GoshuinRepository::class)->findAll() as $goshuin) {
            $this->removeUploads($goshuin->getImage(), $goshuin->getImageMini(), $goshuin->getImageCard(), $goshuin->getImageFull());
        }

        foreach (static::getContainer()->get(PhotoRepository::class)->findAll() as $photo) {
            $this->removeUploads($photo->getImage(), $photo->getImageMini(), $photo->getImageCard(), $photo->getImageFull());
        }
    }

    private function anyone(string $id): User
    {
        return $this->unfiltered()->find(User::class, $id);
    }

    private function locations(): LocationRepository
    {
        $this->manager();

        return static::getContainer()->get(LocationRepository::class);
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return static::getContainer()->get('doctrine')->getConnection();
    }

    private function users(): UserRepository
    {
        $this->manager();

        return static::getContainer()->get(UserRepository::class);
    }
}
