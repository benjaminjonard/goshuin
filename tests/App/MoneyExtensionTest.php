<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyExtensionTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_it_formats_from_minor_units_and_the_currency(
        ?int $amount,
        string $currency,
        string $locale,
        string $expected,
    ): void {
        $extension = new MoneyExtension();

        $formatted = $extension->money($amount, $currency, $locale);

        $this->assertSame($expected, $this->normalise($formatted));
    }

    public static function amounts(): iterable
    {
        yield 'yen, no minor unit' => [300, 'JPY', 'en', '¥300'];
        yield 'yen, read in French' => [300, 'JPY', 'fr', '300 ¥'];

        yield 'euro, two minor units' => [500, 'EUR', 'fr', '5,00 €'];
        yield 'euro, read in English' => [500, 'EUR', 'en', '€5.00'];

        yield 'zero is a value, not an absence' => [0, 'JPY', 'en', '¥0'];

        yield 'nothing at all' => [null, 'JPY', 'en', ''];
    }

    private function normalise(string $value): string
    {
        return str_replace(["\u{00a0}", "\u{202f}"], ' ', $value);
    }
}
