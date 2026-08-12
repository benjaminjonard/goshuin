<?php

declare(strict_types=1);

namespace App\Enum;

enum LocationType: string
{
    case Shrine = 'shrine';
    case Temple = 'temple';
    case Other = 'other';
}
