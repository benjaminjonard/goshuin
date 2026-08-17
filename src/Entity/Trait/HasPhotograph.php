<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Attribute\Upload;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

trait HasPhotograph
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $photograph = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $photographMini = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $photographCard = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $photographFull = null;

    #[Upload(
        pathProperty: 'photograph',
        miniProperty: 'photographMini',
        cardProperty: 'photographCard',
        fullProperty: 'photographFull',
        deleteProperty: 'removePhotograph',
    )]
    #[Assert\Image(
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'error.upload_format',
        uploadIniSizeErrorMessage: 'error.upload_too_large',
    )]
    private ?File $photographFile = null;

    private bool $removePhotograph = false;

    public function getPhotograph(): ?string
    {
        return $this->photograph;
    }

    public function setPhotograph(?string $photograph): static
    {
        $this->photograph = $photograph;

        return $this;
    }

    public function getPhotographMini(): ?string
    {
        return $this->photographMini;
    }

    public function setPhotographMini(?string $photographMini): static
    {
        $this->photographMini = $photographMini;

        return $this;
    }

    public function getPhotographCard(): ?string
    {
        return $this->photographCard;
    }

    public function setPhotographCard(?string $photographCard): static
    {
        $this->photographCard = $photographCard;

        return $this;
    }

    public function getPhotographFull(): ?string
    {
        return $this->photographFull;
    }

    public function setPhotographFull(?string $photographFull): static
    {
        $this->photographFull = $photographFull;

        return $this;
    }

    public function getPhotographFile(): ?File
    {
        return $this->photographFile;
    }

    public function setPhotographFile(?File $photographFile): static
    {
        $this->photographFile = $photographFile;

        return $this;
    }

    public function isRemovePhotograph(): bool
    {
        return $this->removePhotograph;
    }

    public function setRemovePhotograph(bool $removePhotograph): static
    {
        $this->removePhotograph = $removePhotograph;

        return $this;
    }
}
