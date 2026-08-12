<?php

declare(strict_types=1);

namespace App\Enum;

enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    /**
     * The attribute the document carries. System puts none there, which is what lets
     * prefers-color-scheme decide — the middle state of AD-9.
     */
    public function attribute(): ?string
    {
        return $this === self::System ? null : $this->value;
    }
}
