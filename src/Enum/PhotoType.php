<?php

declare(strict_types=1);

namespace App\Enum;

enum PhotoType: string
{
    case Location = 'location';
    case Other = 'other';
}
