<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Location;
use App\Entity\LocationPhoto;
use App\Entity\Photo;
use App\Enum\PhotoType;
use App\Repository\GoshuinRepository;
use App\Repository\LocationPhotoRepository;
use App\Repository\PhotoRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Positioner
{
    public function __construct(
        private EntityManagerInterface $manager,
        private GoshuinRepository $repository,
        private PhotoRepository $photos,
        private LocationPhotoRepository $locationPhotos,
    ) {
    }

    public function add(Goshuin $goshuin): void
    {
        $goshuincho = $this->goshuinchoOf($goshuin);

        $this->manager->wrapInTransaction(function () use ($goshuin, $goshuincho): void {
            $this->manager->lock($goshuincho, LockMode::PESSIMISTIC_WRITE);
            $goshuin->setPosition($this->repository->lastPosition($goshuincho) + 1);
            $this->manager->persist($goshuin);
            $this->manager->flush();
        });
    }

    /**
     * @param list<string> $ids
     */
    public function order(Goshuincho $goshuincho, array $ids): void
    {
        $this->manager->wrapInTransaction(function () use ($goshuincho, $ids): void {
            $this->manager->lock($goshuincho, LockMode::PESSIMISTIC_WRITE);
            $this->renumber($this->repository->inOrder($goshuincho), $ids);
        });
    }

    public function remove(Goshuin $goshuin): void
    {
        $goshuincho = $this->goshuinchoOf($goshuin);

        $this->manager->wrapInTransaction(function () use ($goshuin, $goshuincho): void {
            $this->manager->lock($goshuincho, LockMode::PESSIMISTIC_WRITE);
            $this->manager->remove($goshuin);
            $this->manager->flush();
            $this->renumber($this->repository->inOrder($goshuincho), []);
        });
    }

    public function addPhoto(Photo $photo): void
    {
        $goshuin = $this->goshuinOf($photo);
        $type = $this->typeOf($photo);

        $this->manager->wrapInTransaction(function () use ($photo, $goshuin, $type): void {
            $this->manager->lock($goshuin, LockMode::PESSIMISTIC_WRITE);
            $photo->setPosition($this->photos->lastPosition($goshuin, $type) + 1);
            $this->manager->persist($photo);
            $this->manager->flush();
        });
    }

    /**
     * @param list<string> $ids
     */
    public function orderPhotos(Goshuin $goshuin, PhotoType $type, array $ids): void
    {
        $this->manager->wrapInTransaction(function () use ($goshuin, $type, $ids): void {
            $this->manager->lock($goshuin, LockMode::PESSIMISTIC_WRITE);
            $this->renumber($this->photos->ofType($goshuin, $type), $ids);
        });
    }

    public function removePhoto(Photo $photo): void
    {
        $goshuin = $this->goshuinOf($photo);
        $type = $this->typeOf($photo);

        $this->manager->wrapInTransaction(function () use ($photo, $goshuin, $type): void {
            $this->manager->lock($goshuin, LockMode::PESSIMISTIC_WRITE);
            $this->manager->remove($photo);
            $this->manager->flush();
            $this->renumber($this->photos->ofType($goshuin, $type), []);
        });
    }

    public function addLocationPhoto(LocationPhoto $photo): void
    {
        $location = $this->locationOf($photo);

        $this->manager->wrapInTransaction(function () use ($photo, $location): void {
            $this->manager->lock($location, LockMode::PESSIMISTIC_WRITE);
            $photo->setPosition($this->locationPhotos->lastPosition($location) + 1);
            $this->manager->persist($photo);
            $this->manager->flush();
        });
    }

    /**
     * @param list<string> $ids
     */
    public function orderLocationPhotos(Location $location, array $ids): void
    {
        $this->manager->wrapInTransaction(function () use ($location, $ids): void {
            $this->manager->lock($location, LockMode::PESSIMISTIC_WRITE);
            $this->renumber($this->locationPhotos->ofLocation($location), $ids);
        });
    }

    public function removeLocationPhoto(LocationPhoto $photo): void
    {
        $location = $this->locationOf($photo);

        $this->manager->wrapInTransaction(function () use ($photo, $location): void {
            $this->manager->lock($location, LockMode::PESSIMISTIC_WRITE);
            $this->manager->remove($photo);
            $this->manager->flush();
            $this->renumber($this->locationPhotos->ofLocation($location), []);
        });
    }

    /**
     * @param list<Goshuin|Photo|LocationPhoto> $items
     * @param list<string>                      $ids
     */
    private function renumber(array $items, array $ids): void
    {
        $waiting = [];

        foreach ($items as $item) {
            $waiting[$item->getId()] = $item;
            $item->setPosition(-(int) $item->getPosition());
        }

        $this->manager->flush();

        $spot = 0;

        foreach ($ids as $id) {
            if (isset($waiting[$id])) {
                $waiting[$id]->setPosition(++$spot);
                unset($waiting[$id]);
            }
        }

        foreach ($waiting as $item) {
            $item->setPosition(++$spot);
        }

        $this->manager->flush();
    }

    private function locationOf(LocationPhoto $photo): Location
    {
        $location = $photo->getLocation();

        if (!$location instanceof Location) {
            throw new \LogicException('A LocationPhoto is positioned within its Location.');
        }

        return $location;
    }

    private function goshuinOf(Photo $photo): Goshuin
    {
        $goshuin = $photo->getGoshuin();

        if (!$goshuin instanceof Goshuin) {
            throw new \LogicException('A Photo is positioned within its Goshuin.');
        }

        return $goshuin;
    }

    private function typeOf(Photo $photo): PhotoType
    {
        $type = $photo->getType();

        if (!$type instanceof PhotoType) {
            throw new \LogicException('A Photo is positioned within its type.');
        }

        return $type;
    }

    private function goshuinchoOf(Goshuin $goshuin): Goshuincho
    {
        $goshuincho = $goshuin->getGoshuincho();

        if (!$goshuincho instanceof Goshuincho) {
            throw new \LogicException('A Goshuin is positioned within its Goshuincho.');
        }

        return $goshuincho;
    }
}
