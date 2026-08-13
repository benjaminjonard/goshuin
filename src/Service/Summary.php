<?php

declare(strict_types=1);

namespace App\Service;

final readonly class Summary
{
    /**
     * @param list<string> $cities
     * @param list<string> $prefectures
     */
    public function __construct(
        public int $goshuin,
        public int $locations,
        public array $cities,
        public array $prefectures,
        public ?\DateTimeImmutable $first,
        public ?\DateTimeImmutable $last,
    ) {
    }

    public function days(): ?int
    {
        if ($this->first === null || $this->last === null) {
            return null;
        }

        return (int) $this->first->diff($this->last)->days + 1;
    }
}
