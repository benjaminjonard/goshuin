<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\Sluggable;
use App\Entity\Trait\HasPhotograph;
use App\Repository\DeityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeityRepository::class)]
#[ORM\Table(name: 'gos_deity')]
#[ORM\UniqueConstraint(name: 'un_deity_name', columns: ['owner_id', 'name'])]
#[ORM\UniqueConstraint(name: 'un_deity_slug', columns: ['owner_id', 'slug'])]
#[UniqueEntity(fields: ['name'], message: 'error.deity.not_unique')]
class Deity implements Sluggable
{
    use HasPhotograph;

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

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Length(max: 255)])]
    private array $additionalNames = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): Deity
    {
        $this->owner = $owner;

        return $this;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
