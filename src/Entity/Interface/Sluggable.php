<?php

declare(strict_types=1);

namespace App\Entity\Interface;

interface Sluggable
{
    public function getSlug(): ?string;
}
