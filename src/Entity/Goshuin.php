<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\Upload;
use App\Repository\GoshuinRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GoshuinRepository::class)]
#[ORM\Table(name: 'gos_goshuin')]
#[ORM\UniqueConstraint(name: 'un_goshuin_position', columns: ['goshuincho_id', 'position'])]
class Goshuin
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Goshuincho::class, inversedBy: 'goshuins')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Goshuincho $goshuincho = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'error.location_required')]
    private ?Location $location = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'error.received_on_required')]
    private ?\DateTimeImmutable $receivedOn = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $position = null;

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
    #[Assert\NotNull(message: 'error.image_required', groups: ['goshuin:create'])]
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

    public function getGoshuincho(): ?Goshuincho
    {
        return $this->goshuincho;
    }

    public function setGoshuincho(?Goshuincho $goshuincho): Goshuin
    {
        $this->goshuincho = $goshuincho;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): Goshuin
    {
        $this->owner = $owner;

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): Goshuin
    {
        $this->location = $location;

        return $this;
    }

    public function getReceivedOn(): ?\DateTimeImmutable
    {
        return $this->receivedOn;
    }

    public function setReceivedOn(?\DateTimeImmutable $receivedOn): Goshuin
    {
        $this->receivedOn = $receivedOn;

        return $this;
    }

    public function isReceivedInTheFuture(): bool
    {
        return $this->receivedOn !== null && $this->receivedOn > new \DateTimeImmutable('today');
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): Goshuin
    {
        $this->position = $position;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): Goshuin
    {
        $this->image = $image;

        return $this;
    }

    public function getImageMini(): ?string
    {
        return $this->imageMini;
    }

    public function setImageMini(?string $imageMini): Goshuin
    {
        $this->imageMini = $imageMini;

        return $this;
    }

    public function getImageCard(): ?string
    {
        return $this->imageCard;
    }

    public function setImageCard(?string $imageCard): Goshuin
    {
        $this->imageCard = $imageCard;

        return $this;
    }

    public function getImageFull(): ?string
    {
        return $this->imageFull;
    }

    public function setImageFull(?string $imageFull): Goshuin
    {
        $this->imageFull = $imageFull;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): Goshuin
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
