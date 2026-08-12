<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Entity\User;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class OwnershipTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_a_user_cannot_reach_another_user_by_id(): void
    {
        $mine = UserFactory::createOne();
        $theirs = UserFactory::createOne();
        $mineId = $mine->getId();
        $theirsId = $theirs->getId();

        $this->client->loginUser($mine);
        $this->client->request(Request::METHOD_GET, '/');

        $this->assertNull($this->find($theirsId), 'Another user was reachable by id.');
        $this->assertNotNull($this->find($mineId), 'My own record became unreachable.');
    }

    public function test_a_user_sees_nobody_but_themselves(): void
    {
        $mine = UserFactory::createOne();
        UserFactory::createMany(3);
        $mineId = $mine->getId();

        $this->client->loginUser($mine);
        $this->client->request(Request::METHOD_GET, '/');

        $this->assertSame([$mineId], $this->visibleUserIds());
    }

    public function test_two_requests_in_one_process_do_not_share_a_user(): void
    {
        $first = UserFactory::createOne();
        $second = UserFactory::createOne();
        $firstId = $first->getId();
        $secondId = $second->getId();

        $this->client->loginUser($first);
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertSame([$firstId], $this->visibleUserIds());

        $this->client->loginUser($this->userIgnoringOwnership($secondId));
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertSame([$secondId], $this->visibleUserIds(), 'The second request kept the first user.');
    }

    public function test_the_filter_is_off_when_nobody_is_authenticated(): void
    {
        UserFactory::createMany(2);

        $this->client->request(Request::METHOD_GET, '/login');

        $this->assertCount(2, $this->visibleUserIds(), 'The filter stayed on for an anonymous visitor.');
    }

    public function test_signing_out_releases_the_filter(): void
    {
        $mine = UserFactory::createOne();
        UserFactory::createOne();

        $this->client->loginUser($mine);
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertCount(1, $this->visibleUserIds());

        $this->client->request(Request::METHOD_GET, '/logout');
        $this->client->followRedirect();

        $this->assertCount(2, $this->visibleUserIds(), 'The filter survived signing out.');
    }

    public function test_nothing_else_re_checks_ownership(): void
    {
        $sources = array_merge(
            glob(\dirname(__DIR__, 2).'/src/Controller/*.php') ?: [],
            glob(\dirname(__DIR__, 2).'/src/Repository/*.php') ?: [],
        );

        foreach ($sources as $source) {
            $code = file_get_contents($source);
            $this->assertStringNotContainsString('getOwner()', $code, basename($source).' re-checks ownership.');
            $this->assertStringNotContainsString('AccessDenied', $code, basename($source).' raises an access denial.');
        }

        $this->assertSame([], glob(\dirname(__DIR__, 2).'/src/Security/Voter/*.php') ?: [], 'A Voter appeared.');
    }

    private function find(string $id): ?User
    {
        return $this->users()->find($id);
    }

    private function userIgnoringOwnership(string $id): User
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $filters = $manager->getFilters();

        if ($filters->isEnabled('ownership')) {
            $filters->disable('ownership');
        }

        return $manager->getRepository(User::class)->find($id);
    }

    /**
     * @return list<string>
     */
    private function visibleUserIds(): array
    {
        return array_map(
            static fn (User $user): string => $user->getId(),
            $this->users()->findAll(),
        );
    }

    private function users(): \Doctrine\ORM\EntityRepository
    {
        $doctrine = static::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();

        return $doctrine->getRepository(User::class);
    }
}
