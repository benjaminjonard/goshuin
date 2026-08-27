<?php

declare(strict_types=1);

namespace App\Entity\Interface;

interface Named
{
    public function getRomanizedName(): ?string;

    public function getKanjiName(): ?string;

    public function getKanaName(): ?string;

    public function getDisplayName(string $locale): ?string;

    public function setDisplayName(string $locale, ?string $name): static;

    public function getSecondaryName(string $locale): ?string;

    public function hasAName(): bool;

    /**
     * @return list<string>
     */
    public static function displayFields(string $locale): array;

    /**
     * @return list<string>
     */
    public static function orderFields(string $locale): array;
}
