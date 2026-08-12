<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Repository\UserRepository;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FirstRunTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->followRedirects();
    }

    public function test_every_route_leads_to_setup_while_there_is_no_user(): void
    {
        foreach (['/', '/login'] as $path) {
            $this->client->request(Request::METHOD_GET, $path);
            $this->assertRouteSame('app_setup', [], sprintf('%s did not lead to setup.', $path));
        }
    }

    public function test_setup_creates_the_administrator_and_opens_a_session(): void
    {
        $this->client->request(Request::METHOD_GET, '/setup');

        $this->client->submitForm('setup_submit', [
            'setup[name]' => 'Test User',
            'setup[email]' => 'user@example.com',
            'setup[plainPassword][first]' => 'a-long-enough-password',
            'setup[plainPassword][second]' => 'a-long-enough-password',
        ]);

        $this->assertRouteSame('app_homepage');
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user@example.com']);
        $this->assertNotNull($user);
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertNotSame('a-long-enough-password', $user->getPassword(), 'The password was stored in clear.');
        $this->assertNull($user->getPlainPassword(), 'The plain password survived the request.');
    }

    public function test_an_invalid_submission_creates_nothing(): void
    {
        $this->client->request(Request::METHOD_GET, '/setup');

        $this->client->submitForm('setup_submit', [
            'setup[name]' => '',
            'setup[email]' => 'not-an-email',
            'setup[plainPassword][first]' => 'short',
            'setup[plainPassword][second]' => 'different',
        ]);

        $this->assertRouteSame('app_setup');
        $this->assertSame(0, static::getContainer()->get(UserRepository::class)->count([]));
    }

    public function test_setup_is_gone_once_a_user_exists(): void
    {
        UserFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/setup');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_home_states_the_collection_is_empty_and_draws_nothing_else(): void
    {
        $user = UserFactory::new()->admin()->create();
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('app_homepage');
        $this->assertCount(1, $crawler->filter('h1'), 'More than one h1 on the page.');
        $this->assertCount(1, $crawler->filter('main h2'), 'Home did not state that the collection is empty.');
        $this->assertGreaterThan(0, $crawler->filter('main a')->count(), 'Home offered no way out.');
        $this->assertCount(0, $crawler->filter('[data-controller="map"]'), 'A map was drawn on an empty collection.');
        $this->assertCount(0, $crawler->filter('main ol, main dl'), 'Home grew a list again.');
    }
}
