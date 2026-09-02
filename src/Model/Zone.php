<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Zone
{
    public function __construct(
        public string $code,
        public int $goshuin,
        public int $goshuincho,
    ) {
    }
}
