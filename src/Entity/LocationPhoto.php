<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LocationPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationPhotoRepository::class)]
#[ORM\Table(name: 'gos_location_photo')]
#[ORM\UniqueConstraint(name: 'un_location_photo_position', columns: ['location_id', 'position'])]
class LocationPhoto extends AttachedPhoto
{
    #[ORM\ManyToOne(targetEntity: Location::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(name: 'location_id', nullable: false, onDelete: 'CASCADE')]
    private ?Location $subject = null;

    #[\Override]
    public function getSubject(): ?Location
    {
        return $this->subject;
    }

    public function setSubject(?Location $subject): LocationPhoto
    {
        $this->subject = $subject;

        return $this;
    }
}
