<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\Upload;
use App\Enum\PhotoType;
use App\Repository\PhotoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\Table(name: 'gos_photo')]
#[ORM\UniqueConstraint(name: 'un_photo_position', columns: ['goshuin_id', 'type', 'position'])]
class Photo
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Goshuin::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Goshuin $goshuin = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::STRING, length: 8, enumType: PhotoType::class)]
    private ?PhotoType $type = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $position = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $image = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $imageMini = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $imageCard = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $imageFull = null;

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
    private ?File $imageFile = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGoshuin(): ?Goshuin
    {
        return $this->goshuin;
    }

    public function setGoshuin(?Goshuin $goshuin): Photo
    {
        $this->goshuin = $goshuin;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): Photo
    {
        $this->owner = $owner;

        return $this;
    }

    public function getType(): ?PhotoType
    {
        return $this->type;
    }

    public function setType(?PhotoType $type): Photo
    {
        $this->type = $type;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Photo
    {
        $this->position = $position;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): Photo
    {
        $this->label = $label;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): Photo
    {
        $this->image = $image;

        return $this;
    }

    public function getImageMini(): ?string
    {
        return $this->imageMini;
    }

    public function setImageMini(?string $imageMini): Photo
    {
        $this->imageMini = $imageMini;

        return $this;
    }

    public function getImageCard(): ?string
    {
        return $this->imageCard;
    }

    public function setImageCard(?string $imageCard): Photo
    {
        $this->imageCard = $imageCard;

        return $this;
    }

    public function getImageFull(): ?string
    {
        return $this->imageFull;
    }

    public function setImageFull(?string $imageFull): Photo
    {
        $this->imageFull = $imageFull;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): Photo
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
