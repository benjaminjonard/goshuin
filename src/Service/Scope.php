<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\City;
use App\Entity\Goshuincho;
use App\Entity\Prefecture;

final readonly class Scope
{
    public function __construct(
        public string $key,
        public string $value,
        public string $icon,
        public string $label,
        public string $href,
        public Goshuincho|City|Prefecture $subject,
    ) {
    }
}
