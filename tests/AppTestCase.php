<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

abstract class AppTestCase extends WebTestCase
{
    use FileTrait;

    protected KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->client);
        gc_collect_cycles();
    }

    protected function signIn(User $user): void
    {
        static::getContainer()->get('security.token_storage')
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    protected function uploadsDir(): string
    {
        return static::getContainer()->getParameter('app.uploads_dir');
    }

    protected function removeUploads(?string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path !== null) {
                @unlink($this->uploadsDir().'/'.$path);
            }
        }
    }

    protected function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();

        return $manager;
    }

    protected function unfiltered(): EntityManagerInterface
    {
        $manager = $this->manager();
        $filters = $manager->getFilters();

        if ($filters->isEnabled('ownership')) {
            $filters->disable('ownership');
        }

        return $manager;
    }
}
