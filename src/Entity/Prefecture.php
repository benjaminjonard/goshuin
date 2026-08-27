<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\Identified;
use App\Entity\Interface\Named;
use App\Entity\Interface\Photographed;
use App\Entity\Trait\HasNames;
use App\Entity\Trait\HasPhotograph;
use App\Repository\PrefectureRepository;
use App\Validator\AtLeastOneName;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PrefectureRepository::class)]
#[ORM\Table(name: 'gos_prefecture')]
#[AtLeastOneName]
class Prefecture implements Named, Identified, Photographed
{
    use HasNames;
    use HasPhotograph;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): Prefecture
    {
        $this->notes = $notes;

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
