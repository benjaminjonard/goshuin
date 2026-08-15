<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrefecturePhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrefecturePhotoRepository::class)]
#[ORM\Table(name: 'gos_prefecture_photo')]
#[ORM\UniqueConstraint(name: 'un_prefecture_photo_position', columns: ['prefecture_id', 'position'])]
class PrefecturePhoto extends AttachedPhoto
{
    #[ORM\ManyToOne(targetEntity: Prefecture::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(name: 'prefecture_id', nullable: false, onDelete: 'CASCADE')]
    private ?Prefecture $subject = null;

    #[\Override]
    public function getSubject(): ?Prefecture
    {
        return $this->subject;
    }

    public function setSubject(?Prefecture $subject): PrefecturePhoto
    {
        $this->subject = $subject;

        return $this;
    }
}
