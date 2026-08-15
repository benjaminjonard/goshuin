<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\Upload;
use App\Repository\DeityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeityRepository::class)]
#[ORM\Table(name: 'gos_deity')]
#[ORM\UniqueConstraint(name: 'un_deity_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'un_deity_slug', columns: ['slug'])]
#[UniqueEntity(fields: ['name'], message: 'error.deity.not_unique')]
class Deity implements Sluggable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Gedmo\Slug(fields: ['name'], unique: true)]
    private ?string $slug = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Length(max: 255)])]
    private array $additionalNames = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

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

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): Deity
    {
        $this->slug = $slug;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): Deity
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAdditionalNames(): array
    {
        return $this->additionalNames;
    }

    /**
     * @param list<string> $additionalNames
     */
    public function setAdditionalNames(array $additionalNames): Deity
    {
        $this->additionalNames = $additionalNames;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): Deity
    {
        $this->description = $description;

        return $this;
    }

    public function getPhotograph(): ?string
    {
        return $this->photograph;
    }

    public function setPhotograph(?string $photograph): Deity
    {
        $this->photograph = $photograph;

        return $this;
    }

    public function getPhotographMini(): ?string
    {
        return $this->photographMini;
    }

    public function setPhotographMini(?string $photographMini): Deity
    {
        $this->photographMini = $photographMini;

        return $this;
    }

    public function getPhotographCard(): ?string
    {
        return $this->photographCard;
    }

    public function setPhotographCard(?string $photographCard): Deity
    {
        $this->photographCard = $photographCard;

        return $this;
    }

    public function getPhotographFull(): ?string
    {
        return $this->photographFull;
    }

    public function setPhotographFull(?string $photographFull): Deity
    {
        $this->photographFull = $photographFull;

        return $this;
    }

    public function getPhotographFile(): ?File
    {
        return $this->photographFile;
    }

    public function setPhotographFile(?File $photographFile): Deity
    {
        $this->photographFile = $photographFile;

        return $this;
    }

    public function isRemovePhotograph(): bool
    {
        return $this->removePhotograph;
    }

    public function setRemovePhotograph(bool $removePhotograph): Deity
    {
        $this->removePhotograph = $removePhotograph;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
