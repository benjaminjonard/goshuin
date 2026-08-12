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
        // Arrange
        $extension = new MoneyExtension();

        // Act
        $formatted = $extension->money($amount, $currency, $locale);

        // Assert
        $this->assertSame($expected, $this->normalise($formatted));
    }

    public static function amounts(): iterable
    {
        // Yen has no minor unit, so the stored integer is already the amount and no
        // decimals may appear. This is the case the whole filter exists for.
        yield 'yen, no minor unit' => [300, 'JPY', 'en', '¥300'];
        yield 'yen, read in French' => [300, 'JPY', 'fr', '300 ¥'];

        // Euro has two, so 500 minor units is five euros — not five hundred.
        yield 'euro, two minor units' => [500, 'EUR', 'fr', '5,00 €'];
        yield 'euro, read in English' => [500, 'EUR', 'en', '€5.00'];

        yield 'zero is a value, not an absence' => [0, 'JPY', 'en', '¥0'];

        // An absent amount produces nothing, which a template then emits as nothing
        // at all rather than as a zero or a dash (AD-8).
        yield 'nothing at all' => [null, 'JPY', 'en', ''];
    }

    /** Intl uses non-breaking and narrow no-break spaces, which no assertion should hinge on. */
    private function normalise(string $value): string
    {
        return str_replace(["\u{00a0}", "\u{202f}"], ' ', $value);
    }
}
