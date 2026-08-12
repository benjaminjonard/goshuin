<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
        UserFactory::createOne(['email' => 'benjamin@example.com']);

        // Act
        $this->submitCredentials('benjamin@example.com', 'a-long-enough-password');

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
        UserFactory::createOne(['email' => 'benjamin@example.com']);

        // Act
        $this->submitCredentials('benjamin@example.com', 'not-the-password');

        // Assert
        $this->client->followRedirect();
        $this->assertRouteSame('app_login');
        $this->assertNull(static::getContainer()->get('security.token_storage')->getToken());
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
