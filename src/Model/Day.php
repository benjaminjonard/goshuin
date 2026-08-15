<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Goshuin;

final readonly class Day
{
    /**
     * @param list<Goshuin> $goshuins
     */
    public function __construct(
        public ?\DateTimeImmutable $date,
        public array $goshuins,
        public ?int $after = null,
    ) {
    }
}
