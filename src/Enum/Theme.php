<?php

declare(strict_types=1);

namespace App\Enum;

enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function attribute(): ?string
    {
        return $this === self::System ? null : $this->value;
    }
}
