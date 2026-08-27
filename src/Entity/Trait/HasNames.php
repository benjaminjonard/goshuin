<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait HasNames
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $romanizedName = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $kanjiName = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $kanaName = null;

    public function getRomanizedName(): ?string
    {
        return $this->romanizedName;
    }

    public function setRomanizedName(?string $romanizedName): static
    {
        $this->romanizedName = $romanizedName;

        return $this;
    }

    public function getKanjiName(): ?string
    {
        return $this->kanjiName;
    }

    public function setKanjiName(?string $kanjiName): static
    {
        $this->kanjiName = $kanjiName;

        return $this;
    }

    public function getKanaName(): ?string
    {
        return $this->kanaName;
    }

    public function setKanaName(?string $kanaName): static
    {
        $this->kanaName = $kanaName;

        return $this;
    }

    public function getDisplayName(string $locale): ?string
    {
        return $this->firstFilled(array_map(
            fn (string $field): ?string => $this->{$field},
            self::displayFields($locale),
        ));
    }

    public function setDisplayName(string $locale, ?string $name): static
    {
        return match (self::displayFields($locale)[0]) {
            'kanjiName' => $this->setKanjiName($name),
            'kanaName' => $this->setKanaName($name),
            default => $this->setRomanizedName($name),
        };
    }

    public function getSecondaryName(string $locale): ?string
    {
        return $this->firstFilled(
            $locale === 'ja' ? [$this->kanaName, $this->romanizedName] : [$this->kanjiName, $this->kanaName],
            $this->getDisplayName($locale),
        );
    }

    public function hasAName(): bool
    {
        return $this->firstFilled([$this->romanizedName, $this->kanjiName, $this->kanaName]) !== null;
    }

    /**
     * @return list<string>
     */
    public static function displayFields(string $locale): array
    {
        return $locale === 'ja'
            ? ['kanjiName', 'kanaName', 'romanizedName']
            : ['romanizedName', 'kanjiName', 'kanaName'];
    }

    /**
     * @return list<string>
     */
    public static function orderFields(string $locale): array
    {
        return $locale === 'ja'
            ? ['kanaName', 'kanjiName', 'romanizedName']
            : ['romanizedName', 'kanjiName', 'kanaName'];
    }

    /**
     * @param list<?string> $chain
     */
    private function firstFilled(array $chain, ?string $except = null): ?string
    {
        foreach ($chain as $name) {
            $name = $name === null ? null : trim($name);

            if ($name !== null && $name !== '' && $name !== $except) {
                return $name;
            }
        }

        return null;
    }
}
