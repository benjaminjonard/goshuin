<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BytesExtension extends AbstractExtension
{
    private const array UNITS = ['', 'Ki', 'Mi', 'Gi', 'Ti', 'Pi', 'Ei'];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('bytes', $this->bytes(...)),
        ];
    }

    public function bytes(int|float $bytes, ?string $locale = null): string
    {
        $step = $bytes > 0 ? min((int) floor(log((float) $bytes, 1024)), \count(self::UNITS) - 1) : 0;

        $formatter = new \NumberFormatter($locale ?? \Locale::getDefault(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $step === 0 ? 0 : 2);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);

        return $formatter->format($bytes / 1024 ** $step)
            .' '.self::UNITS[$step]
            .$this->translator->trans('label.byte_abbreviation', locale: $locale);
    }
}
