<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Goshuin;

final readonly class Leg
{
    public function __construct(
        public Goshuin $goshuin,
        public ?int $days = null,
        public bool $sameDay = false,
    ) {
    }
}
