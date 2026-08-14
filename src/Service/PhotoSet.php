<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goshuin;
use App\Entity\Location;
use App\Entity\LocationPhoto;
use App\Entity\Photo;
use App\Enum\PhotoType;
use App\Repository\LocationPhotoRepository;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class PhotoSet
{
    public function __construct(
        private PhotoRepository $photos,
        private LocationPhotoRepository $locationPhotos,
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

    public function applyToLocation(Location $location, PhotoInstructions $instructions): int
    {
        return $this->settle(
            $this->locationPhotos->ofLocation($location),
            $instructions,
            fn (LocationPhoto $photo): null => $this->positioner->removeLocationPhoto($photo),
            fn (array $order): null => $this->positioner->orderLocationPhotos($location, $order),
            fn (UploadedFile $file, ?string $label): LocationPhoto => new LocationPhoto()
                ->setLocation($location)
                ->setLabel($label)
                ->setImageFile($file),
            fn (LocationPhoto $photo): null => $this->positioner->addLocationPhoto($photo),
        );
    }

    /**
     * @param list<Photo|LocationPhoto> $existing
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
