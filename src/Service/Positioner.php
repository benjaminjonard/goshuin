<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AttachedPhoto;
use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Interface\Photographed;
use App\Entity\Photo;
use App\Enum\PhotoType;
use App\Repository\AttachedPhotoRepository;
use App\Repository\GoshuinRepository;
use App\Repository\PhotoRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Positioner
{
    public function __construct(
        private EntityManagerInterface $manager,
        private GoshuinRepository $repository,
        private PhotoRepository $photos,
    ) {
    }

    public function add(Goshuin $goshuin): void
    {
        $goshuincho = $this->goshuinchoOf($goshuin);

        $this->locked($goshuincho, function () use ($goshuin, $goshuincho): void {
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
        $this->locked($goshuincho, fn (): null => $this->renumber($this->repository->inOrder($goshuincho), $ids));
    }

    public function remove(Goshuin $goshuin): void
    {
        $goshuincho = $this->goshuinchoOf($goshuin);

        $this->locked($goshuincho, function () use ($goshuin, $goshuincho): void {
            $this->manager->remove($goshuin);
            $this->manager->flush();
            $this->renumber($this->repository->inOrder($goshuincho), []);
        });
    }

    public function addPhoto(Photo $photo): void
    {
        $goshuin = $this->goshuinOf($photo);
        $type = $this->typeOf($photo);

        $this->locked($goshuin, function () use ($photo, $goshuin, $type): void {
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
        $this->locked($goshuin, fn (): null => $this->renumber($this->photos->ofType($goshuin, $type), $ids));
    }

    public function removePhoto(Photo $photo): void
    {
        $goshuin = $this->goshuinOf($photo);
        $type = $this->typeOf($photo);

        $this->locked($goshuin, function () use ($photo, $goshuin, $type): void {
            $this->manager->remove($photo);
            $this->manager->flush();
            $this->renumber($this->photos->ofType($goshuin, $type), []);
        });
    }

    public function addAttachedPhoto(AttachedPhoto $photo): void
    {
        $subject = $this->subjectOf($photo);
        $album = $this->album($subject);

        $this->locked($subject, function () use ($photo, $subject, $album): void {
            $photo->setPosition($album->lastPosition($subject) + 1);
            $this->manager->persist($photo);
            $this->manager->flush();
        });
    }

    /**
     * @param list<string> $ids
     */
    public function orderAttachedPhotos(Photographed $subject, array $ids): void
    {
        $album = $this->album($subject);

        $this->locked($subject, fn (): null => $this->renumber($album->ofSubject($subject), $ids));
    }

    public function removeAttachedPhoto(AttachedPhoto $photo): void
    {
        $subject = $this->subjectOf($photo);
        $album = $this->album($subject);

        $this->locked($subject, function () use ($photo, $subject, $album): void {
            $this->manager->remove($photo);
            $this->manager->flush();
            $this->renumber($album->ofSubject($subject), []);
        });
    }

    private function locked(object $subject, \Closure $work): void
    {
        $this->manager->wrapInTransaction(function () use ($subject, $work): void {
            $this->manager->lock($subject, LockMode::PESSIMISTIC_WRITE);
            $work();
        });
    }

    /**
     * @return AttachedPhotoRepository<AttachedPhoto>
     */
    private function album(Photographed $subject): AttachedPhotoRepository
    {
        return $this->manager->getRepository($subject::photoClass());
    }

    /**
     * @param list<Goshuin|Photo|AttachedPhoto> $items
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

    private function subjectOf(AttachedPhoto $photo): Photographed
    {
        return $photo->getSubject() ?? throw new \LogicException('An AttachedPhoto is positioned within its subject.');
    }

    private function goshuinOf(Photo $photo): Goshuin
    {
        return $photo->getGoshuin() ?? throw new \LogicException('A Photo is positioned within its Goshuin.');
    }

    private function typeOf(Photo $photo): PhotoType
    {
        return $photo->getType() ?? throw new \LogicException('A Photo is positioned within its type.');
    }

    private function goshuinchoOf(Goshuin $goshuin): Goshuincho
    {
        return $goshuin->getGoshuincho() ?? throw new \LogicException('A Goshuin is positioned within its Goshuincho.');
    }
}
