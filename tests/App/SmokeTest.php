<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SmokeTest extends KernelTestCase
{
    public function test_kernel_boots_in_the_test_environment(): void
    {
        $kernel = self::bootKernel();

        $this->assertSame('test', $kernel->getEnvironment());
        $this->assertInstanceOf(Kernel::class, $kernel);
    }

    public function test_container_provides_the_entity_manager(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
    }
}
