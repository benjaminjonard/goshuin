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

    /**
     * Amounts are stored as an integer number of minor units plus an ISO 4217 code, so
     * the divisor and the number of decimals come from the currency: 300 JPY is ¥300
     * because yen has no minor unit, while 500 EUR is €5.00.
     *
     * The symbol is looked up in English on purpose. ICU renders JPY as "300 JPY" for a
     * French reader, which is correct French practice for a foreign currency but reads
     * clumsily beside a yen icon in a collection of Japanese seals. Grouping, decimals and
     * the symbol's position still follow the reader's locale — only the symbol itself
     * is pinned.
     *
     * An absent amount returns an empty string, which a template emits as nothing at
     * all rather than as a zero or a dash (AD-8).
     */
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
