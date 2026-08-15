<?php

declare(strict_types=1);

namespace App\Model;

final readonly class StoredImage
{
    public function __construct(
        public string $path,
        public string $mini,
        public string $card,
        public string $full,
        public int $width,
        public int $height,
    ) {
    }
}
