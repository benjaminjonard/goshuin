<?php

declare(strict_types=1);

namespace App\Enum;

enum Shown: string
{
    case Both = 'both';
    case Goshuin = 'goshuin';
    case Goshuincho = 'goshuincho';

    public static function asked(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Both;
    }

    public function showsGoshuin(): bool
    {
        return $this !== self::Goshuincho;
    }

    public function showsGoshuincho(): bool
    {
        return $this !== self::Goshuin;
    }
}
