<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Holdings
{
    public function __construct(
        public int $goshuincho,
        public int $goshuin,
        public int $photographs,
    ) {
    }

    public function any(): bool
    {
        return $this->goshuincho > 0 || $this->goshuin > 0 || $this->photographs > 0;
    }
}
