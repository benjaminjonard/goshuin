<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\Identified;
use App\Entity\Interface\Named;
use App\Entity\Interface\Photographed;
use App\Entity\Trait\HasNames;
use App\Entity\Trait\HasPhotograph;
use App\Enum\LocationType;
use App\Repository\LocationRepository;
use App\Validator\AtLeastOneName;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'gos_location')]
#[ORM\Index(name: 'idx_location_municipality_code', columns: ['municipality_code'])]
#[AtLeastOneName]
class Location implements Named, Identified, Photographed
{
    use HasNames;
    use HasPhotograph;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::STRING, length: 8, enumType: LocationType::class, nullable: true)]
    private ?LocationType $type = null;

    /**
     * @var DoctrineCollection<int, Deity>
     */
    #[ORM\ManyToMany(targetEntity: Deity::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'gos_location_deity')]
    private DoctrineCollection $deities;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $foundation = null;

    #[ORM\ManyToOne(targetEntity: City::class, cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?City $city = null;

    #[ORM\ManyToOne(targetEntity: Prefecture::class, cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Prefecture $prefecture = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $address = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true, options: ['fixed' => true])]
    private ?string $municipalityCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * @var DoctrineCollection<int, LocationPhoto>
     */
    #[ORM\OneToMany(targetEntity: LocationPhoto::class, mappedBy: 'subject', cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private DoctrineCollection $photos;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->photos = new ArrayCollection();
        $this->deities = new ArrayCollection();
    }

    #[\Override]
    public static function photoClass(): string
    {
        return LocationPhoto::class;
    }

    /**
     * @return DoctrineCollection<int, LocationPhoto>
     */
    #[\Override]
    public function getPhotos(): DoctrineCollection
    {
        return $this->photos;
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): Location
    {
        $this->owner = $owner;

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

    /**
     * @return DoctrineCollection<int, Deity>
     */
    public function getDeities(): DoctrineCollection
    {
        return $this->deities;
    }

    public function addDeity(Deity $deity): Location
    {
        if (!$this->deities->contains($deity)) {
            $this->deities->add($deity);
        }

        return $this;
    }

    public function removeDeity(Deity $deity): Location
    {
        $this->deities->removeElement($deity);

        return $this;
    }

    public function getFoundation(): ?string
    {
        return $this->foundation;
    }

    public function setFoundation(?string $foundation): Location
    {
        $this->foundation = $foundation;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): Location
    {
        $this->city = $city;

        return $this;
    }

    public function getPrefecture(): ?Prefecture
    {
        return $this->prefecture;
    }

    public function setPrefecture(?Prefecture $prefecture): Location
    {
        $this->prefecture = $prefecture;

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

    public function getMunicipalityCode(): ?string
    {
        return $this->municipalityCode;
    }

    public function setMunicipalityCode(?string $municipalityCode): Location
    {
        $this->municipalityCode = $municipalityCode;

        return $this;
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
