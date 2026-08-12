<?php

declare(strict_types=1);

namespace App\Tests\App\Controller;

use App\Tests\AppTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HomeTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_home_is_private(): void
    {
        UserFactory::createOne();

        $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function test_home_states_the_collection_is_empty_and_draws_nothing_else(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('h1'), 'More than one h1 on the page.');
        $this->assertCount(1, $crawler->filter('main h2'), 'Home did not state that the collection is empty.');
        $this->assertGreaterThan(0, $crawler->filter('main a')->count(), 'Home offered no way out.');
        $this->assertCount(0, $crawler->filter('[data-controller="map"]'), 'A map was drawn on an empty collection.');
        $this->assertCount(0, $crawler->filter('main ol, main dl'), 'Home grew a list again.');
    }

    public function test_an_authenticated_page_is_never_cached(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request(Request::METHOD_GET, '/');

        $cacheControl = $this->client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }
}
