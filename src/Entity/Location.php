<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\Upload;
use App\Enum\LocationType;
use App\Repository\LocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
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

    /**
     * @var DoctrineCollection<int, Deity>
     */
    #[ORM\ManyToMany(targetEntity: Deity::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'gos_location_deity')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private DoctrineCollection $deities;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $foundation = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $locality = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $prefecture = null;

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

    /**
     * @var DoctrineCollection<int, LocationPhoto>
     */
    #[ORM\OneToMany(targetEntity: LocationPhoto::class, mappedBy: 'location', cascade: ['remove'], fetch: 'EXTRA_LAZY')]
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

    /**
     * @return DoctrineCollection<int, LocationPhoto>
     */
    public function getPhotos(): DoctrineCollection
    {
        return $this->photos;
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

    public function getLocality(): ?string
    {
        return $this->locality;
    }

    public function setLocality(?string $locality): Location
    {
        $this->locality = $locality;

        return $this;
    }

    public function getPrefecture(): ?string
    {
        return $this->prefecture;
    }

    public function setPrefecture(?string $prefecture): Location
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

    public function getPhotograph(): ?string
    {
        return $this->photograph;
    }

    public function setPhotograph(?string $photograph): Location
    {
        $this->photograph = $photograph;

        return $this;
    }

    public function getPhotographMini(): ?string
    {
        return $this->photographMini;
    }

    public function setPhotographMini(?string $photographMini): Location
    {
        $this->photographMini = $photographMini;

        return $this;
    }

    public function getPhotographCard(): ?string
    {
        return $this->photographCard;
    }

    public function setPhotographCard(?string $photographCard): Location
    {
        $this->photographCard = $photographCard;

        return $this;
    }

    public function getPhotographFull(): ?string
    {
        return $this->photographFull;
    }

    public function setPhotographFull(?string $photographFull): Location
    {
        $this->photographFull = $photographFull;

        return $this;
    }

    public function getPhotographFile(): ?File
    {
        return $this->photographFile;
    }

    public function setPhotographFile(?File $photographFile): Location
    {
        $this->photographFile = $photographFile;

        return $this;
    }

    public function isRemovePhotograph(): bool
    {
        return $this->removePhotograph;
    }

    public function setRemovePhotograph(bool $removePhotograph): Location
    {
        $this->removePhotograph = $removePhotograph;

        return $this;
    }
}
