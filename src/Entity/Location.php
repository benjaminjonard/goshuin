<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LocationType;
use App\Repository\LocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'gos_location')]
class Location
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $romanizedName = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $japaneseName = null;

    #[ORM\Column(type: Types::STRING, length: 8, enumType: LocationType::class, nullable: true)]
    private ?LocationType $type = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $locality = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $address = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getRomanizedName(): ?string
    {
        return $this->romanizedName;
    }

    public function setRomanizedName(?string $romanizedName): Location
    {
        $this->romanizedName = $romanizedName;

        return $this;
    }

    public function getJapaneseName(): ?string
    {
        return $this->japaneseName;
    }

    public function setJapaneseName(?string $japaneseName): Location
    {
        $this->japaneseName = $japaneseName;

        return $this;
    }

    public function getType(): ?LocationType
    {
        return $this->type;
    }

    public function setType(?LocationType $type): Location
    {
        $this->type = $type;

        return $this;
    }

    public function getLocality(): ?string
    {
        return $this->locality;
    }

    public function setLocality(?string $locality): Location
    {
        $this->locality = $locality;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): Location
    {
        $this->address = $address;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): Location
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): Location
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): Location
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): Location
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): Location
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
