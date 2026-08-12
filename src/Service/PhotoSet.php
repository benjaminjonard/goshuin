<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goshuin;
use App\Entity\Photo;
use App\Enum\PhotoType;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        $kept = [];

        foreach ($this->photos->ofType($goshuin, $type) as $photo) {
            $kept[$photo->getId()] = $photo;
        }

        foreach ($instructions->removed as $id) {
            if (isset($kept[$id])) {
                $this->positioner->removePhoto($kept[$id]);
                unset($kept[$id]);
            }
        }

        foreach ($instructions->labels as $id => $label) {
            if (isset($kept[$id])) {
                $kept[$id]->setLabel($this->clean($label));
            }
        }

        $this->manager->flush();

        $order = array_values(array_filter($instructions->order, static fn (string $id): bool => isset($kept[$id])));

        if ($order !== []) {
            $this->positioner->orderPhotos($goshuin, $type, $order);
        }

        return $this->add($goshuin, $type, $instructions);
    }

    private function add(Goshuin $goshuin, PhotoType $type, PhotoInstructions $instructions): int
    {
        $refused = 0;

        foreach ($instructions->added as $spot => $file) {
            $photo = new Photo()
                ->setGoshuin($goshuin)
                ->setType($type)
                ->setLabel($this->clean($instructions->addedLabels[$spot] ?? ''))
                ->setImageFile($file)
            ;

            if ($this->validator->validate($photo)->count() > 0) {
                ++$refused;

                continue;
            }

            $this->positioner->addPhoto($photo);
        }

        return $refused;
    }

    private function clean(string $label): ?string
    {
        $label = trim($label);

        return $label === '' ? null : $label;
    }
}
