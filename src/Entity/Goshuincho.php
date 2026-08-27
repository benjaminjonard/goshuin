<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\Identified;
use App\Attribute\Upload;
use App\Repository\GoshuinchoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GoshuinchoRepository::class)]
#[ORM\Table(name: 'gos_goshuincho')]
class Goshuincho implements Identified
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 0, max: 360)]
    private ?int $hue = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Location $boughtAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $price = null;

    #[ORM\Column(type: Types::STRING, length: 3, options: ['default' => 'JPY'])]
    private string $currency = 'JPY';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverFront = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverFrontMini = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverFrontCard = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverFrontFull = null;

    #[Upload(
        pathProperty: 'coverFront',
        miniProperty: 'coverFrontMini',
        cardProperty: 'coverFrontCard',
        fullProperty: 'coverFrontFull',
        deleteProperty: 'removeCoverFront',
    )]
    #[Assert\Image(
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'error.upload_format',
        uploadIniSizeErrorMessage: 'error.upload_too_large',
    )]
    private ?File $coverFrontFile = null;

    private bool $removeCoverFront = false;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverBack = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverBackMini = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverBackCard = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $coverBackFull = null;

    #[Upload(
        pathProperty: 'coverBack',
        miniProperty: 'coverBackMini',
        cardProperty: 'coverBackCard',
        fullProperty: 'coverBackFull',
        deleteProperty: 'removeCoverBack',
    )]
    #[Assert\Image(
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'error.upload_format',
        uploadIniSizeErrorMessage: 'error.upload_too_large',
    )]
    private ?File $coverBackFile = null;

    private bool $removeCoverBack = false;

    /**
     * @var DoctrineCollection<int, Goshuin>
     */
    #[ORM\OneToMany(targetEntity: Goshuin::class, mappedBy: 'goshuincho', cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private DoctrineCollection $goshuins;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->goshuins = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): Goshuincho
    {
        $this->title = $title;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): Goshuincho
    {
        $this->owner = $owner;

        return $this;
    }

    public function getHue(): ?int
    {
        return $this->hue;
    }

    public function setHue(?int $hue): Goshuincho
    {
        $this->hue = $hue;

        return $this;
    }

    public function getBoughtAt(): ?Location
    {
        return $this->boughtAt;
    }

    public function setBoughtAt(?Location $boughtAt): Goshuincho
    {
        $this->boughtAt = $boughtAt;

        return $this;
    }

    public function getPurchasedAt(): ?\DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(?\DateTimeImmutable $purchasedAt): Goshuincho
    {
        $this->purchasedAt = $purchasedAt;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): Goshuincho
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): Goshuincho
    {
        $this->currency = $currency;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): Goshuincho
    {
        $this->description = $description;

        return $this;
    }

    public function getCoverFront(): ?string
    {
        return $this->coverFront;
    }

    public function setCoverFront(?string $coverFront): Goshuincho
    {
        $this->coverFront = $coverFront;

        return $this;
    }

    public function getCoverFrontMini(): ?string
    {
        return $this->coverFrontMini;
    }

    public function setCoverFrontMini(?string $coverFrontMini): Goshuincho
    {
        $this->coverFrontMini = $coverFrontMini;

        return $this;
    }

    public function getCoverFrontCard(): ?string
    {
        return $this->coverFrontCard;
    }

    public function setCoverFrontCard(?string $coverFrontCard): Goshuincho
    {
        $this->coverFrontCard = $coverFrontCard;

        return $this;
    }

    public function getCoverFrontFull(): ?string
    {
        return $this->coverFrontFull;
    }

    public function setCoverFrontFull(?string $coverFrontFull): Goshuincho
    {
        $this->coverFrontFull = $coverFrontFull;

        return $this;
    }

    public function getCoverFrontFile(): ?File
    {
        return $this->coverFrontFile;
    }

    public function setCoverFrontFile(?File $coverFrontFile): Goshuincho
    {
        $this->coverFrontFile = $coverFrontFile;

        return $this;
    }

    public function isRemoveCoverFront(): bool
    {
        return $this->removeCoverFront;
    }

    public function setRemoveCoverFront(bool $removeCoverFront): Goshuincho
    {
        $this->removeCoverFront = $removeCoverFront;

        return $this;
    }

    public function getCoverBack(): ?string
    {
        return $this->coverBack;
    }

    public function setCoverBack(?string $coverBack): Goshuincho
    {
        $this->coverBack = $coverBack;

        return $this;
    }

    public function getCoverBackMini(): ?string
    {
        return $this->coverBackMini;
    }

    public function setCoverBackMini(?string $coverBackMini): Goshuincho
    {
        $this->coverBackMini = $coverBackMini;

        return $this;
    }

    public function getCoverBackCard(): ?string
    {
        return $this->coverBackCard;
    }

    public function setCoverBackCard(?string $coverBackCard): Goshuincho
    {
        $this->coverBackCard = $coverBackCard;

        return $this;
    }

    public function getCoverBackFull(): ?string
    {
        return $this->coverBackFull;
    }

    public function setCoverBackFull(?string $coverBackFull): Goshuincho
    {
        $this->coverBackFull = $coverBackFull;

        return $this;
    }

    public function getCoverBackFile(): ?File
    {
        return $this->coverBackFile;
    }

    public function setCoverBackFile(?File $coverBackFile): Goshuincho
    {
        $this->coverBackFile = $coverBackFile;

        return $this;
    }

    public function isRemoveCoverBack(): bool
    {
        return $this->removeCoverBack;
    }

    public function setRemoveCoverBack(bool $removeCoverBack): Goshuincho
    {
        $this->removeCoverBack = $removeCoverBack;

        return $this;
    }

    /**
     * @return DoctrineCollection<int, Goshuin>
     */
    public function getGoshuins(): DoctrineCollection
    {
        return $this->goshuins;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): Goshuincho
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): Goshuincho
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
