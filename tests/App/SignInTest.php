<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SignInTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_a_user_signs_in_and_reaches_home(): void
    {
        // Arrange
        UserFactory::createOne(['email' => 'user@example.com']);

        // Act
        $this->submitCredentials('user@example.com', 'a-long-enough-password');

        // Assert
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_homepage');
    }

    public function test_a_disabled_user_cannot_sign_in(): void
    {
        // Arrange
        UserFactory::new()->disabled()->create(['email' => 'gone@example.com']);

        // Act
        $this->submitCredentials('gone@example.com', 'a-long-enough-password');

        // Assert
        $this->client->followRedirect();
        $this->assertRouteSame('app_login', [], 'A disabled user was let in.');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function test_a_wrong_password_does_not_sign_anyone_in(): void
    {
        // Arrange
        UserFactory::createOne(['email' => 'user@example.com']);

        // Act
        $this->submitCredentials('user@example.com', 'not-the-password');

        // Assert
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function test_the_error_does_not_say_whether_the_email_exists(): void
    {
        // Arrange
        UserFactory::createOne(['email' => 'user@example.com']);

        // Act
        $this->submitCredentials('user@example.com', 'not-the-password');
        $known = $this->errorShown($this->client->followRedirect());

        $this->submitCredentials('nobody@example.com', 'not-the-password');
        $unknown = $this->errorShown($this->client->followRedirect());

        // Assert
        $this->assertNotSame('', $known, 'A failed sign-in showed no error at all.');
        $this->assertSame($known, $unknown, 'The error told the visitor whether the email exists.');
    }

    public function test_repeated_failures_are_refused_with_their_own_message(): void
    {
        // Arrange
        UserFactory::createOne(['email' => 'throttled@example.com']);

        // Act
        $messages = [];
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $this->submitCredentials('throttled@example.com', 'not-the-password');
            $messages[$attempt] = $this->errorShown($this->client->followRedirect());
        }

        // Assert
        $this->assertNotSame($messages[1], $messages[6], 'The throttle never announced itself.');
        $this->assertSame($messages[1], $messages[5], 'The message changed before the threshold.');
    }

    public function test_a_protected_page_is_given_back_after_signing_in(): void
    {
        // Arrange
        UserFactory::createOne(['email' => 'user@example.com']);

        // Act
        $this->client->request(Request::METHOD_GET, '/_design');
        $this->client->followRedirect();
        $this->assertRouteSame('app_login', [], 'A protected page did not lead to sign-in.');

        $this->submitCredentials('user@example.com', 'a-long-enough-password');
        $this->client->followRedirect();

        // Assert
        $this->assertRouteSame('app_design', [], 'Signing in did not give the requested page back.');
    }

    public function test_signing_out_ends_the_session(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertResponseIsSuccessful();

        // Act
        $this->client->request(Request::METHOD_GET, '/logout');
        $this->client->followRedirect();

        // Assert
        $this->assertRouteSame('app_login');
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login', [], 'The session survived signing out.');
    }

    public function test_an_authenticated_page_is_never_cached(): void
    {
        // Arrange
        $this->client->loginUser(UserFactory::createOne());

        // Act
        $this->client->request(Request::METHOD_GET, '/');

        // Assert
        $cacheControl = $this->client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    private function errorShown(Crawler $crawler): string
    {
        $alert = $crawler->filter('[role="alert"]');

        return $alert->count() === 0 ? '' : trim($alert->text());
    }

    private function submitCredentials(string $email, string $password): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/login');

        $this->client->submit($crawler->filter('form')->form(), [
            '_username' => $email,
            '_password' => $password,
        ]);
    }
}
