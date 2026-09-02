<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Coverage
{
    /**
     * @param list<Zone> $prefectures
     * @param list<Zone> $municipalities
     */
    public function __construct(
        public array $prefectures,
        public array $municipalities,
        public int $unlocatedGoshuin,
        public int $unlocatedGoshuincho,
    ) {
    }
}
