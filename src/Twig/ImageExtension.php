<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ImageExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('image', $this->image(...)),
        ];
    }

    public function image(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return '/uploads/'.$path;
    }
}
