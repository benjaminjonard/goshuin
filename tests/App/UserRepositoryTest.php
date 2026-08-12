<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Repository\UserRepository;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_it_reports_an_empty_instance(): void
    {
        $repository = $this->repository();

        $this->assertTrue($repository->hasNone());

        UserFactory::createOne();

        $this->assertFalse($repository->hasNone());
    }

    public function test_it_counts_administrators_and_nobody_else(): void
    {
        UserFactory::new()->admin()->many(2)->create();
        UserFactory::createOne();
        UserFactory::new()->disabled()->create();

        $count = $this->repository()->countAdministrators();

        $this->assertSame(2, $count);
    }

    private function repository(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }
}
