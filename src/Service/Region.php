<?php

declare(strict_types=1);

namespace App\Service;

final readonly class Region
{
    public function __construct(
        public string $japanese,
        public string $romanized,
    ) {
    }
}
