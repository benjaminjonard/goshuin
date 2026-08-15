<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Entity\LocationPhoto;
use App\Repository\GoshuinRepository;
use App\Repository\LocationPhotoRepository;
use App\Repository\PhotoRepository;
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

        new Positioner($manager, $repository, $this->createStub(PhotoRepository::class))->add($goshuin);
    }

    public function test_an_order_submitted_whole_is_applied_and_stays_contiguous(): void
    {
        $goshuins = $this->goshuins(4);
        $ids = array_map(static fn (Goshuin $goshuin): string => $goshuin->getId(), $goshuins);
        $goshuincho = $goshuins[0]->getGoshuincho();

        $this->ordering($goshuins)->order($goshuincho, [$ids[3], $ids[0], $ids[2], $ids[1]]);

        $this->assertSame([2, 4, 3, 1], $this->positionsOf($goshuins), 'The submitted order was not the one applied.');
    }

    public function test_a_goshuin_left_out_of_the_submitted_order_keeps_a_place_at_the_end(): void
    {
        $goshuins = $this->goshuins(3);
        $ids = array_map(static fn (Goshuin $goshuin): string => $goshuin->getId(), $goshuins);

        $this->ordering($goshuins)->order($goshuins[0]->getGoshuincho(), [$ids[2]]);

        $this->assertSame([1, 2, 3], $this->sorted($goshuins), 'Leaving one out of the order left a hole.');
        $this->assertSame(1, $goshuins[2]->getPosition(), 'The one that was named did not take the place it was given.');
    }

    public function test_removing_a_goshuin_closes_the_hole_behind_it(): void
    {
        $goshuins = $this->goshuins(5);
        $removed = $goshuins[2];

        $this->ordering($goshuins)->remove($removed);

        unset($goshuins[2]);
        $this->assertSame([1, 2, 3, 4], array_values($this->positionsOf($goshuins)), 'Removing from the middle left a hole.');
    }

    public function test_removing_the_last_goshuin_renumbers_nothing(): void
    {
        $goshuins = $this->goshuins(3);

        $this->ordering($goshuins)->remove($goshuins[2]);

        unset($goshuins[2]);
        $this->assertSame([1, 2], array_values($this->positionsOf($goshuins)));
    }

    public function test_the_first_photograph_of_a_location_takes_position_one(): void
    {
        $photo = new LocationPhoto()->setSubject(new Location());

        $this->placing([])->addAttachedPhoto($photo);

        $this->assertSame(1, $photo->getPosition());
    }

    public function test_a_photograph_takes_the_place_after_the_last_one(): void
    {
        $photo = new LocationPhoto()->setSubject(new Location());

        $this->placing([$this->shot(1), $this->shot(2)])->addAttachedPhoto($photo);

        $this->assertSame(3, $photo->getPosition());
    }

    public function test_a_photograph_outside_a_location_is_refused(): void
    {
        $this->expectException(\LogicException::class);

        $this->placing([])->addAttachedPhoto(new LocationPhoto());
    }

    public function test_reordering_photographs_keeps_them_contiguous(): void
    {
        $shots = [$this->shot(1), $this->shot(2), $this->shot(3)];
        $ids = array_map(static fn (LocationPhoto $p): string => $p->getId(), $shots);

        $this->placing($shots)->orderAttachedPhotos(new Location(), [$ids[2], $ids[0], $ids[1]]);

        $this->assertSame([2, 3, 1], array_map(static fn (LocationPhoto $p): ?int => $p->getPosition(), $shots), 'The submitted order was not applied.');
    }

    public function test_a_photograph_left_out_of_the_order_keeps_a_place_at_the_end(): void
    {
        $shots = [$this->shot(1), $this->shot(2), $this->shot(3)];
        $ids = array_map(static fn (LocationPhoto $p): string => $p->getId(), $shots);

        $this->placing($shots)->orderAttachedPhotos(new Location(), [$ids[2]]);

        $this->assertSame(1, $shots[2]->getPosition(), 'The named photograph did not come first.');
        $this->assertSame([2, 3], [$shots[0]->getPosition(), $shots[1]->getPosition()], 'The unnamed photographs lost their contiguity.');
    }

    public function test_removing_a_photograph_closes_the_hole_behind_it(): void
    {
        $shots = [$this->shot(1), $this->shot(2), $this->shot(3)];
        $left = [$shots[0], $shots[2]];

        $this->placing($left)->removeAttachedPhoto($shots[1]);

        $this->assertSame([1, 2], array_map(static fn (LocationPhoto $p): ?int => $p->getPosition(), $left), 'The removal left a gap.');
    }

    /**
     * @return list<Goshuin>
     */
    private function goshuins(int $count): array
    {
        $goshuincho = new Goshuincho();
        $goshuins = [];

        for ($page = 1; $page <= $count; ++$page) {
            $goshuins[] = new Goshuin()->setGoshuincho($goshuincho)->setPosition($page);
        }

        return $goshuins;
    }

    /**
     * @param list<Goshuin> $goshuins
     */
    private function ordering(array $goshuins): Positioner
    {
        $gone = new \ArrayObject();

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $manager->method('remove')->willReturnCallback(static function (object $entity) use ($gone): void {
            $gone->append($entity);
        });

        $repository = $this->createStub(GoshuinRepository::class);
        $repository->method('lastPosition')->willReturnCallback(static function () use ($goshuins): int {
            return max(array_map(static fn (Goshuin $goshuin): int => (int) $goshuin->getPosition(), $goshuins));
        });
        $repository->method('inOrder')->willReturnCallback(static function () use ($goshuins, $gone): array {
            $found = array_filter(
                $goshuins,
                static fn (Goshuin $goshuin): bool => !in_array($goshuin, $gone->getArrayCopy(), true),
            );

            usort($found, static fn (Goshuin $a, Goshuin $b): int => $a->getPosition() <=> $b->getPosition());

            return array_values($found);
        });

        return new Positioner($manager, $repository, $this->createStub(PhotoRepository::class));
    }

    /**
     * @param array<int, Goshuin> $goshuins
     *
     * @return array<int, int>
     */
    private function positionsOf(array $goshuins): array
    {
        return array_map(static fn (Goshuin $goshuin): int => (int) $goshuin->getPosition(), $goshuins);
    }

    /**
     * @param array<int, Goshuin> $goshuins
     *
     * @return list<int>
     */
    private function sorted(array $goshuins): array
    {
        $positions = array_values($this->positionsOf($goshuins));
        sort($positions);

        return $positions;
    }

    private function positioner(int $lastPosition): Positioner
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());

        $repository = $this->createStub(GoshuinRepository::class);
        $repository->method('lastPosition')->willReturn($lastPosition);

        return new Positioner($manager, $repository, $this->createStub(PhotoRepository::class));
    }

    private function goshuin(): Goshuin
    {
        return new Goshuin()->setGoshuincho(new Goshuincho());
    }

    private function shot(int $position): LocationPhoto
    {
        return new LocationPhoto()->setSubject(new Location())->setPosition($position);
    }

    /**
     * @param list<LocationPhoto> $existing
     */
    private function placing(array $existing): Positioner
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());

        $photos = $this->createStub(LocationPhotoRepository::class);
        $photos->method('lastPosition')->willReturn($existing === [] ? 0 : max(array_map(static fn (LocationPhoto $p): int => (int) $p->getPosition(), $existing)));
        $photos->method('ofSubject')->willReturn($existing);
        $manager->method('getRepository')->willReturn($photos);

        return new Positioner($manager, $this->createStub(GoshuinRepository::class), $this->createStub(PhotoRepository::class));
    }
}
