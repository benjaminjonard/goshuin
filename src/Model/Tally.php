<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Tally
{
    public function __construct(
        public int $goshuincho,
        public int $goshuin,
        public int $cities,
        public int $prefectures,
    ) {
    }
}
