<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\Identified;
use App\Entity\Interface\Named;
use App\Entity\Trait\HasNames;
use App\Entity\Trait\HasPhotograph;
use App\Repository\DeityRepository;
use App\Validator\AtLeastOneName;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeityRepository::class)]
#[ORM\Table(name: 'gos_deity')]
#[AtLeastOneName]
class Deity implements Named, Identified
{
    use HasNames;
    use HasPhotograph;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

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
