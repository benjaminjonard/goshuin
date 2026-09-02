<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Span
{
    public function __construct(
        public string $key,
        public int $goshuin,
        public int $goshuincho,
    ) {
    }
}
