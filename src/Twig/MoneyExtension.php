<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Intl\Currencies;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
        ];
    }

    public function money(?int $amount, string $currency = 'JPY', ?string $locale = null): string
    {
        if ($amount === null) {
            return '';
        }

        $digits = Currencies::getFractionDigits($currency);

        $formatter = new \NumberFormatter($locale ?? \Locale::getDefault(), \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $digits);
        $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency);
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, Currencies::getSymbol($currency, 'en'));

        return $formatter->format($amount / 10 ** $digits);
    }
}
