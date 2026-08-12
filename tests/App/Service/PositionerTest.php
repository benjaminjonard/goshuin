<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Repository\GoshuinRepository;
use App\Service\Positioner;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PositionerTest extends TestCase
{
    public function test_the_first_goshuin_of_a_goshuincho_takes_position_one(): void
    {
        $goshuin = $this->goshuin();

        $this->positioner(0)->add($goshuin);

        $this->assertSame(1, $goshuin->getPosition());
    }

    public function test_a_goshuin_takes_the_place_after_the_last_one(): void
    {
        $goshuin = $this->goshuin();

        $this->positioner(4)->add($goshuin);

        $this->assertSame(5, $goshuin->getPosition());
    }

    public function test_a_goshuin_outside_a_goshuincho_is_refused(): void
    {
        $this->expectException(\LogicException::class);

        $this->positioner(0)->add(new Goshuin());
    }

    public function test_the_goshuincho_is_locked_before_the_last_position_is_read(): void
    {
        $goshuin = $this->goshuin();
        $goshuincho = $goshuin->getGoshuincho();
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $manager->expects($this->once())->method('lock')->with($goshuincho, LockMode::PESSIMISTIC_WRITE);
        $manager->expects($this->once())->method('persist')->with($goshuin);
        $manager->expects($this->once())->method('flush');

        $repository = $this->createStub(GoshuinRepository::class);
        $repository->method('lastPosition')->willReturn(0);

        new Positioner($manager, $repository)->add($goshuin);
    }

    private function positioner(int $lastPosition): Positioner
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());

        $repository = $this->createStub(GoshuinRepository::class);
        $repository->method('lastPosition')->willReturn($lastPosition);

        return new Positioner($manager, $repository);
    }

    private function goshuin(): Goshuin
    {
        return new Goshuin()->setGoshuincho(new Goshuincho());
    }
}
