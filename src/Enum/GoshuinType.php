<?php

declare(strict_types=1);

namespace App\Enum;

enum GoshuinType: string
{
    case Standard = 'standard';
    case Kirie = 'kirie';
    case Limited = 'limited';
    case Seasonal = 'seasonal';
}
