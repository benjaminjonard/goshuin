<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Enum\Theme;
use App\Repository\UserRepository;
use App\Tests\AppTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SettingsTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_settings_are_private(): void
    {
        $this->client->request(Request::METHOD_GET, '/settings');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function test_a_user_changes_their_name_email_language_and_theme(): void
    {
        $user = UserFactory::createOne(['email' => 'user@example.com', 'locale' => 'en', 'theme' => Theme::System]);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/settings');

        $this->client->submitForm('account_submit', [
            'account[name]' => 'Renamed',
            'account[email]' => 'moved@example.com',
            'account[locale]' => 'fr',
            'account[theme]' => 'dark',
        ]);

        $this->assertResponseRedirects();
        $stored = $this->users()->findOneBy(['email' => 'moved@example.com']);
        $this->assertNotNull($stored, 'The email was not changed.');
        $this->assertSame('Renamed', $stored->getName());
        $this->assertSame('fr', $stored->getLocale());
        $this->assertSame(Theme::Dark, $stored->getTheme());
    }

    public function test_the_chosen_language_renders_the_next_page(): void
    {
        $user = UserFactory::createOne(['locale' => 'en']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/settings');
        $english = $this->client->getCrawler()->filter('h1')->text();

        $this->client->submitForm('account_submit', [
            'account[name]' => $user->getName(),
            'account[email]' => $user->getEmail(),
            'account[locale]' => 'fr',
            'account[theme]' => 'system',
        ]);
        $crawler = $this->client->followRedirect();

        $this->assertNotSame($english, $crawler->filter('h1')->text(), 'The page kept the previous language.');
    }

    public function test_the_stored_theme_reaches_the_document(): void
    {
        $this->client->loginUser(UserFactory::createOne(['theme' => Theme::Light]));

        $crawler = $this->client->request(Request::METHOD_GET, '/settings');

        $this->assertSame('light', $crawler->filter('html')->attr('data-theme'));
    }

    public function test_following_the_system_leaves_the_document_undecided(): void
    {
        $this->client->loginUser(UserFactory::createOne(['theme' => Theme::System]));

        $crawler = $this->client->request(Request::METHOD_GET, '/settings');

        $this->assertNull($crawler->filter('html')->attr('data-theme'), 'System put an attribute on the document.');
    }

    public function test_a_user_gives_themselves_an_avatar(): void
    {
        $user = UserFactory::createOne(['name' => 'Kenji Mori']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/settings');

        $this->client->submitForm('account_submit', [
            'account[name]' => 'Kenji Mori',
            'account[email]' => $user->getEmail(),
            'account[locale]' => 'en',
            'account[theme]' => 'system',
            'account[avatarFile]' => $this->createImage(800, 800),
        ]);

        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $stored = $this->users()->find($user->getId());
        $this->assertNotNull($stored->getAvatarMini(), 'The avatar was not stored.');
        $this->assertSame(
            '/uploads/'.$stored->getAvatarMini(),
            $crawler->filter('header img')->attr('src'),
            'The bar does not serve the avatar at size.',
        );
        $this->assertStringNotContainsString('KM', $crawler->filter('header')->text(), 'The initials outlived the avatar.');

        $this->removeUploads($stored->getAvatar(), $stored->getAvatarMini(), $stored->getAvatarCard(), $stored->getAvatarFull());
    }

    public function test_a_user_takes_their_avatar_back_off(): void
    {
        $user = UserFactory::createOne(['name' => 'Kenji Mori']);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/settings');

        $this->client->submitForm('account_submit', [
            'account[name]' => 'Kenji Mori',
            'account[email]' => $user->getEmail(),
            'account[locale]' => 'en',
            'account[theme]' => 'system',
            'account[avatarFile]' => $this->createImage(800, 800),
        ]);
        $this->client->followRedirect();

        $stored = $this->users()->find($user->getId());
        $paths = [$stored->getAvatar(), $stored->getAvatarMini(), $stored->getAvatarCard(), $stored->getAvatarFull()];

        $this->client->request(Request::METHOD_GET, '/settings');
        $this->client->submitForm('account_submit', [
            'account[name]' => 'Kenji Mori',
            'account[email]' => $user->getEmail(),
            'account[locale]' => 'en',
            'account[theme]' => 'system',
            'account[removeAvatar]' => '1',
        ]);

        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $stored = $this->users()->find($user->getId());
        $this->assertNull($stored->getAvatarMini(), 'The avatar survived its removal.');
        $this->assertFileDoesNotExist($this->uploadsDir().'/'.$paths[0], 'The stored image outlived the avatar.');
        $this->assertCount(0, $crawler->filter('header img'), 'The bar still shows an avatar.');
        $this->assertStringContainsString('KM', $crawler->filter('header')->text(), 'The initials did not come back.');
        $this->assertCount(0, $crawler->filter('input[name="account[removeAvatar]"]'), 'An account without an avatar is still offered to remove one.');

        $this->removeUploads(...$paths);
    }

    public function test_an_avatar_refuses_anything_but_an_image(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/settings');

        $this->client->submitForm('account_submit', [
            'account[name]' => $user->getName(),
            'account[email]' => $user->getEmail(),
            'account[locale]' => 'en',
            'account[theme]' => 'system',
            'account[avatarFile]' => $this->createTextFile(),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertNull($this->users()->find($user->getId())->getAvatar(), 'A text file was stored as an avatar.');
    }

    public function test_a_password_change_needs_the_current_one(): void
    {
        $user = UserFactory::createOne();
        $hashed = $user->getPassword();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/settings');

        $this->client->submitForm('password_submit', [
            'password_change[currentPassword]' => 'not-the-current-one',
            'password_change[plainPassword][first]' => 'another-long-password',
            'password_change[plainPassword][second]' => 'another-long-password',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $stored = $this->users()->find($user->getId());
        $this->assertSame($hashed, $stored->getPassword(), 'The password changed without the current one.');
    }

    public function test_a_password_change_ends_the_other_sessions(): void
    {
        $user = UserFactory::createOne(['email' => 'user@example.com']);

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertResponseIsSuccessful();
        $elsewhere = $this->client->getCookieJar()->all();

        $this->client->restart();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/settings');

        $this->client->submitForm('password_submit', [
            'password_change[currentPassword]' => 'a-long-enough-password',
            'password_change[plainPassword][first]' => 'another-long-password',
            'password_change[plainPassword][second]' => 'another-long-password',
        ]);
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_settings', [], 'Changing my own password signed me out.');

        $this->client->restart();
        foreach ($elsewhere as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login', [], 'The other session survived the password change.');
    }

    private function users(): UserRepository
    {
        $this->manager();

        return static::getContainer()->get(UserRepository::class);
    }
}
