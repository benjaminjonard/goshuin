<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\ImageStore;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly ImageStore $store,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('image', $this->image(...)),
        ];
    }

    public function image(?string $path, ?int $width = null): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return '/uploads/'.($width === null ? $path : $this->store->derivative($path, $width));
    }
}
