<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Tests\AppTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SecurityTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function test_a_user_signs_in_and_reaches_home(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->submitCredentials('user@example.com', 'a-long-enough-password');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_homepage');
    }

    public function test_signing_in_restores_the_stored_language(): void
    {
        UserFactory::createOne(['email' => 'user@example.com', 'locale' => 'fr']);

        $this->submitCredentials('user@example.com', 'a-long-enough-password');
        $crawler = $this->client->followRedirect();

        $this->assertSame('fr', $crawler->filter('html')->attr('lang'), 'Signing in did not restore the stored language.');
    }

    public function test_signing_in_with_an_unknown_language_falls_back_to_english(): void
    {
        UserFactory::createOne(['email' => 'user@example.com', 'locale' => 'jp']);

        $this->submitCredentials('user@example.com', 'a-long-enough-password');
        $crawler = $this->client->followRedirect();

        $this->assertSame('en', $crawler->filter('html')->attr('lang'), 'An unknown language was not refused.');
    }

    public function test_an_anonymous_visitor_gets_the_language_their_browser_asks_for(): void
    {
        UserFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'ja']);

        $this->assertSame('ja', $crawler->filter('html')->attr('lang'), 'The browser language was ignored before signing in.');
        $this->assertStringContainsString('ログイン', $crawler->filter('body')->text(), 'The Japanese catalogue was not served.');
    }

    public function test_an_unknown_browser_language_falls_back_to_english(): void
    {
        UserFactory::createOne();

        $crawler = $this->client->request(Request::METHOD_GET, '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de']);

        $this->assertSame('en', $crawler->filter('html')->attr('lang'), 'A language the instance does not carry was accepted.');
    }

    public function test_a_stored_language_beats_the_one_the_browser_asks_for(): void
    {
        UserFactory::createOne(['email' => 'user@example.com', 'locale' => 'fr']);

        $crawler = $this->client->request(Request::METHOD_GET, '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'ja']);
        $this->client->submit($crawler->filter('form')->form(), [
            '_username' => 'user@example.com',
            '_password' => 'a-long-enough-password',
        ]);
        $crawler = $this->client->followRedirect();

        $this->assertSame('fr', $crawler->filter('html')->attr('lang'), 'The browser overrode a stored choice.');
    }

    public function test_a_disabled_user_cannot_sign_in(): void
    {
        UserFactory::new()->disabled()->create(['email' => 'gone@example.com']);

        $this->submitCredentials('gone@example.com', 'a-long-enough-password');

        $this->client->followRedirect();
        $this->assertRouteSame('app_login', [], 'A disabled user was let in.');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function test_a_wrong_password_does_not_sign_anyone_in(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->submitCredentials('user@example.com', 'not-the-password');

        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function test_the_error_does_not_say_whether_the_email_exists(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->submitCredentials('user@example.com', 'not-the-password');
        $known = $this->errorShown($this->client->followRedirect());

        $this->submitCredentials('nobody@example.com', 'not-the-password');
        $unknown = $this->errorShown($this->client->followRedirect());

        $this->assertNotSame('', $known, 'A failed sign-in showed no error at all.');
        $this->assertSame($known, $unknown, 'The error told the visitor whether the email exists.');
    }

    public function test_repeated_failures_are_refused_with_their_own_message(): void
    {
        UserFactory::createOne(['email' => 'throttled@example.com']);

        $messages = [];
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $this->submitCredentials('throttled@example.com', 'not-the-password');
            $messages[$attempt] = $this->errorShown($this->client->followRedirect());
        }

        $this->assertNotSame($messages[1], $messages[6], 'The throttle never announced itself.');
        $this->assertSame($messages[1], $messages[5], 'The message changed before the threshold.');
    }

    public function test_a_protected_page_is_given_back_after_signing_in(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->client->request(Request::METHOD_GET, '/settings');
        $this->client->followRedirect();
        $this->assertRouteSame('app_login', [], 'A protected page did not lead to sign-in.');

        $this->submitCredentials('user@example.com', 'a-long-enough-password');
        $this->client->followRedirect();

        $this->assertRouteSame('app_settings', [], 'Signing in did not give the requested page back.');
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertResponseIsSuccessful();

        $this->client->request(Request::METHOD_GET, '/logout');
        $this->client->followRedirect();

        $this->assertRouteSame('app_login');
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login', [], 'The session survived signing out.');
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
