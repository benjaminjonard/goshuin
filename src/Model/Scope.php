<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\City;
use App\Entity\Prefecture;
use App\Entity\Tag;

final readonly class Scope
{
    public function __construct(
        public string $key,
        public string $value,
        public string $icon,
        public string $label,
        public string $href,
        public City|Prefecture|Tag $subject,
    ) {
    }
}
