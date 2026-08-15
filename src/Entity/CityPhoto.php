<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CityPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityPhotoRepository::class)]
#[ORM\Table(name: 'gos_city_photo')]
#[ORM\UniqueConstraint(name: 'un_city_photo_position', columns: ['city_id', 'position'])]
class CityPhoto extends AttachedPhoto
{
    #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(name: 'city_id', nullable: false, onDelete: 'CASCADE')]
    private ?City $subject = null;

    #[\Override]
    public function getSubject(): ?City
    {
        return $this->subject;
    }

    public function setSubject(?City $subject): CityPhoto
    {
        $this->subject = $subject;

        return $this;
    }
}
