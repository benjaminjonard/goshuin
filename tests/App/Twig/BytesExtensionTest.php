<?php

declare(strict_types=1);

namespace App\Tests\App\Twig;

use App\Twig\BytesExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BytesExtensionTest extends TestCase
{
    #[DataProvider('sizes')]
    public function test_it_scales_to_the_largest_binary_unit(
        int|float $bytes,
        string $locale,
        string $abbreviation,
        string $expected,
    ): void {
        $extension = new BytesExtension($this->translator($abbreviation));

        $this->assertSame($expected, $this->normalise($extension->bytes($bytes, $locale)));
    }

    public static function sizes(): iterable
    {
        yield 'nothing stored yet' => [0, 'en', 'B', '0 B'];
        yield 'plain bytes stay whole' => [512, 'en', 'B', '512 B'];
        yield 'kibibytes' => [2048, 'en', 'B', '2.00 KiB'];
        yield 'mebibytes' => [5 * 1024 ** 2, 'en', 'B', '5.00 MiB'];
        yield 'gibibytes' => [39546589839, 'en', 'B', '36.83 GiB'];
        yield 'read in French' => [39546589839, 'fr', 'o', '36,83 Gio'];
        yield 'beyond the largest unit' => [1024.0 ** 7, 'en', 'B', '1024.00 EiB'];
    }

    private function translator(string $abbreviation): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn($abbreviation);

        return $translator;
    }

    private function normalise(string $value): string
    {
        return str_replace(["\u{00a0}", "\u{202f}"], ' ', $value);
    }
}
