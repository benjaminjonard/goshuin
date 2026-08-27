<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Pin
{
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
        public int $position,
        public string $title,
        public string $id,
        public ?int $hue,
    ) {
    }
}
