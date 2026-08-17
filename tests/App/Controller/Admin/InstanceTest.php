<?php

declare(strict_types=1);

namespace App\Tests\App\Controller\Admin;

use App\Tests\AppTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class InstanceTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_the_instance_page_refuses_a_plain_user(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_the_instance_page_is_private(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function test_the_instance_page_reports_the_release_the_runtime_and_the_disk_usage(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
        $crawler = $this->client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseIsSuccessful();

        $read = [];
        foreach ($crawler->filter('dl > div') as $node) {
            $row = new Crawler($node);
            $read[$row->filter('dt')->text()] = $row->filter('dd')->text();
        }

        $this->assertSame(static::getContainer()->getParameter('app.release'), $read['Release']);
        $this->assertSame(\PHP_VERSION, $read['PHP version']);
        $this->assertSame(Kernel::VERSION, $read['Symfony version']);
        $this->assertSame(\extension_loaded('frankenphp'), \array_key_exists('FrankenPHP version', $read));
        $this->assertMatchesRegularExpression('/^[\d,.]+ (B|KiB|MiB|GiB)$/', $read['Disk usage']);
    }
}
