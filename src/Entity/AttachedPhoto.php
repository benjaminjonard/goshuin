<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\Upload;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
abstract class AttachedPhoto
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    protected string $id;

    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $position = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    protected ?string $label = null;

    #[ORM\Column(type: Types::STRING)]
    protected ?string $image = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $imageMini = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $imageCard = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $imageFull = null;

    #[Upload(
        pathProperty: 'image',
        miniProperty: 'imageMini',
        cardProperty: 'imageCard',
        fullProperty: 'imageFull',
    )]
    #[Assert\NotNull(message: 'error.image_required')]
    #[Assert\Image(
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'error.upload_format',
        uploadIniSizeErrorMessage: 'error.upload_too_large',
    )]
    protected ?File $imageFile = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    protected ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    abstract public function getSubject(): ?Photographed;

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageMini(): ?string
    {
        return $this->imageMini;
    }

    public function setImageMini(?string $imageMini): static
    {
        $this->imageMini = $imageMini;

        return $this;
    }

    public function getImageCard(): ?string
    {
        return $this->imageCard;
    }

    public function setImageCard(?string $imageCard): static
    {
        $this->imageCard = $imageCard;

        return $this;
    }

    public function getImageFull(): ?string
    {
        return $this->imageFull;
    }

    public function setImageFull(?string $imageFull): static
    {
        $this->imageFull = $imageFull;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): static
    {
        $this->imageFile = $imageFile;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
