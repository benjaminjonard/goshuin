<?php

declare(strict_types=1);

namespace App\Service;

final readonly class Pin
{
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
        public int $position,
        public string $title,
        public string $slug,
        public ?int $hue,
    ) {
    }
}
