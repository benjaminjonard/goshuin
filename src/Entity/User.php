<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\Upload;
use App\Enum\Theme;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'gos_user')]
#[UniqueEntity(fields: ['email'], message: 'error.email.not_unique')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $password = null;

    #[Assert\NotBlank(groups: ['user:password'])]
    #[Assert\Length(min: 12, max: 4096, groups: ['user:password'])]
    private ?string $plainPassword = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $avatarMini = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $avatarCard = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $avatarFull = null;

    #[Upload(
        pathProperty: 'avatar',
        miniProperty: 'avatarMini',
        cardProperty: 'avatarCard',
        fullProperty: 'avatarFull',
        deleteProperty: 'removeAvatar',
    )]
    #[Assert\Image(
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'error.upload_format',
        uploadIniSizeErrorMessage: 'error.upload_too_large',
    )]
    private ?File $avatarFile = null;

    private bool $removeAvatar = false;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::STRING, length: 5, options: ['default' => 'en'])]
    private string $locale = 'en';

    #[ORM\Column(type: Types::STRING, length: 8, enumType: Theme::class, options: ['default' => 'system'])]
    private Theme $theme = Theme::System;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): User
    {
        $this->name = $name;

        return $this;
    }

    public function getInitials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(
            static fn (string $word): string => mb_substr($word, 0, 1),
            \count($words) > 1 ? [$words[0], end($words)] : $words,
        )));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): User
    {
        $this->email = $email;

        return $this;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): User
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): User
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): User
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getAvatarMini(): ?string
    {
        return $this->avatarMini;
    }

    public function setAvatarMini(?string $avatarMini): User
    {
        $this->avatarMini = $avatarMini;

        return $this;
    }

    public function getAvatarCard(): ?string
    {
        return $this->avatarCard;
    }

    public function setAvatarCard(?string $avatarCard): User
    {
        $this->avatarCard = $avatarCard;

        return $this;
    }

    public function getAvatarFull(): ?string
    {
        return $this->avatarFull;
    }

    public function setAvatarFull(?string $avatarFull): User
    {
        $this->avatarFull = $avatarFull;

        return $this;
    }

    public function getAvatarFile(): ?File
    {
        return $this->avatarFile;
    }

    public function setAvatarFile(?File $avatarFile): User
    {
        $this->avatarFile = $avatarFile;

        return $this;
    }

    public function isRemoveAvatar(): bool
    {
        return $this->removeAvatar;
    }

    public function setRemoveAvatar(bool $removeAvatar): User
    {
        $this->removeAvatar = $removeAvatar;

        return $this;
    }

    #[\Override]
    public function getRoles(): array
    {
        return array_unique([...$this->roles, 'ROLE_USER']);
    }

    public function setRoles(array $roles): User
    {
        $this->roles = $roles;

        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function setAdmin(bool $admin): User
    {
        $this->roles = $admin ? ['ROLE_ADMIN'] : ['ROLE_USER'];

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): User
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): User
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): User
    {
        $this->theme = $theme;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): User
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): User
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        unset($data["\0".self::class."\0avatarFile"]);

        return $data;
    }
}
