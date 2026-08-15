<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\City;
use App\Entity\Prefecture;

final readonly class Summary
{
    /**
     * @param list<City>       $cities
     * @param list<Prefecture> $prefectures
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
