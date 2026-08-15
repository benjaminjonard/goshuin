<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\DeityLinker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DeityExtension extends AbstractExtension
{
    public function __construct(
        private readonly DeityLinker $linker,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('deities', $this->linker->link(...), ['is_safe' => ['html']]),
        ];
    }
}
