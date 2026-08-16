<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\Upload;
use App\Repository\PrefectureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PrefectureRepository::class)]
#[ORM\Table(name: 'gos_prefecture')]
#[ORM\UniqueConstraint(name: 'un_prefecture_name', columns: ['owner_id', 'name'])]
#[ORM\UniqueConstraint(name: 'un_prefecture_slug', columns: ['owner_id', 'slug'])]
#[UniqueEntity(fields: ['name'], message: 'error.prefecture.not_unique')]
class Prefecture implements Photographed, Sluggable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Gedmo\Slug(fields: ['name'], unique: true, unique_base: 'owner')]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

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
     * @var DoctrineCollection<int, PrefecturePhoto>
     */
    #[ORM\OneToMany(targetEntity: PrefecturePhoto::class, mappedBy: 'subject', cascade: ['remove'], fetch: 'EXTRA_LAZY')]
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
    }

    #[\Override]
    public static function photoClass(): string
    {
        return PrefecturePhoto::class;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): Prefecture
    {
        $this->slug = $slug;

        return $this;
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return DoctrineCollection<int, PrefecturePhoto>
     */
    #[\Override]
    public function getPhotos(): DoctrineCollection
    {
        return $this->photos;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): Prefecture
    {
        $this->owner = $owner;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): Prefecture
    {
        $this->name = $name;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): Prefecture
    {
        $this->notes = $notes;

        return $this;
    }

    public function getPhotograph(): ?string
    {
        return $this->photograph;
    }

    public function setPhotograph(?string $photograph): Prefecture
    {
        $this->photograph = $photograph;

        return $this;
    }

    public function getPhotographMini(): ?string
    {
        return $this->photographMini;
    }

    public function setPhotographMini(?string $photographMini): Prefecture
    {
        $this->photographMini = $photographMini;

        return $this;
    }

    public function getPhotographCard(): ?string
    {
        return $this->photographCard;
    }

    public function setPhotographCard(?string $photographCard): Prefecture
    {
        $this->photographCard = $photographCard;

        return $this;
    }

    public function getPhotographFull(): ?string
    {
        return $this->photographFull;
    }

    public function setPhotographFull(?string $photographFull): Prefecture
    {
        $this->photographFull = $photographFull;

        return $this;
    }

    public function getPhotographFile(): ?File
    {
        return $this->photographFile;
    }

    public function setPhotographFile(?File $photographFile): Prefecture
    {
        $this->photographFile = $photographFile;

        return $this;
    }

    public function isRemovePhotograph(): bool
    {
        return $this->removePhotograph;
    }

    public function setRemovePhotograph(bool $removePhotograph): Prefecture
    {
        $this->removePhotograph = $removePhotograph;

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
