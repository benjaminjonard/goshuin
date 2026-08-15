<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AttachedPhoto;
use App\Entity\City;
use App\Entity\CityPhoto;
use App\Entity\Goshuin;
use App\Entity\Location;
use App\Entity\LocationPhoto;
use App\Entity\Photo;
use App\Entity\Photographed;
use App\Entity\Prefecture;
use App\Entity\PrefecturePhoto;
use App\Enum\PhotoType;
use App\Repository\AttachedPhotoRepository;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class PhotoSet
{
    public function __construct(
        private PhotoRepository $photos,
        private Positioner $positioner,
        private EntityManagerInterface $manager,
        private ValidatorInterface $validator,
    ) {
    }

    public function apply(Goshuin $goshuin, PhotoType $type, PhotoInstructions $instructions): int
    {
        return $this->settle(
            $this->photos->ofType($goshuin, $type),
            $instructions,
            fn (Photo $photo): null => $this->positioner->removePhoto($photo),
            fn (array $order): null => $this->positioner->orderPhotos($goshuin, $type, $order),
            fn (UploadedFile $file, ?string $label): Photo => new Photo()
                ->setGoshuin($goshuin)
                ->setType($type)
                ->setLabel($label)
                ->setImageFile($file),
            fn (Photo $photo): null => $this->positioner->addPhoto($photo),
        );
    }

    public function applyTo(Photographed $subject, PhotoInstructions $instructions): int
    {
        $album = $this->album($subject);

        return $this->settle(
            $album->ofSubject($subject),
            $instructions,
            fn (AttachedPhoto $photo): null => $this->positioner->removeAttachedPhoto($photo),
            fn (array $order): null => $this->positioner->orderAttachedPhotos($subject, $order),
            fn (UploadedFile $file, ?string $label): AttachedPhoto => $this->attach($subject)
                ->setLabel($label)
                ->setImageFile($file),
            fn (AttachedPhoto $photo): null => $this->positioner->addAttachedPhoto($photo),
        );
    }

    /**
     * @return AttachedPhotoRepository<AttachedPhoto>
     */
    private function album(Photographed $subject): AttachedPhotoRepository
    {
        return $this->manager->getRepository($subject::photoClass());
    }

    private function attach(Photographed $subject): AttachedPhoto
    {
        return match (true) {
            $subject instanceof Location => new LocationPhoto()->setSubject($subject),
            $subject instanceof City => new CityPhoto()->setSubject($subject),
            $subject instanceof Prefecture => new PrefecturePhoto()->setSubject($subject),
        };
    }

    /**
     * @param list<Photo|AttachedPhoto> $existing
     */
    private function settle(
        array $existing,
        PhotoInstructions $instructions,
        callable $remove,
        callable $order,
        callable $make,
        callable $add,
    ): int {
        $kept = [];

        foreach ($existing as $photo) {
            $kept[$photo->getId()] = $photo;
        }

        foreach ($instructions->removed as $id) {
            if (isset($kept[$id])) {
                $remove($kept[$id]);
                unset($kept[$id]);
            }
        }

        foreach ($instructions->labels as $id => $label) {
            if (isset($kept[$id])) {
                $kept[$id]->setLabel($this->clean($label));
            }
        }

        $this->manager->flush();

        $wanted = array_values(array_filter($instructions->order, static fn (string $id): bool => isset($kept[$id])));

        if ($wanted !== []) {
            $order($wanted);
        }

        $refused = 0;

        foreach ($instructions->added as $spot => $file) {
            $photo = $make($file, $this->clean($instructions->addedLabels[$spot] ?? ''));

            if ($this->validator->validate($photo)->count() > 0) {
                ++$refused;

                continue;
            }

            $add($photo);
        }

        return $refused;
    }

    private function clean(string $label): ?string
    {
        $label = trim($label);

        return $label === '' ? null : $label;
    }
}
