<?php

declare(strict_types=1);

namespace App\Tests\App\Entity;

use App\Entity\Location;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HasNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ?string, 1: ?string, 2: ?string, 3: string, 4: ?string, 5: ?string}>
     */
    public static function chains(): iterable
    {
        yield 'all three, other locale' => ['Kiyomizu-dera', '清水寺', 'きよみずでら', 'en', 'Kiyomizu-dera', '清水寺'];
        yield 'all three, japanese' => ['Kiyomizu-dera', '清水寺', 'きよみずでら', 'ja', '清水寺', 'きよみずでら'];
        yield 'romanized alone, japanese' => ['Kiyomizu-dera', null, null, 'ja', 'Kiyomizu-dera', null];
        yield 'kanji alone, other locale' => [null, '清水寺', null, 'en', '清水寺', null];
        yield 'kanji and kana, other locale' => [null, '清水寺', 'きよみずでら', 'en', '清水寺', 'きよみずでら'];
        yield 'kana alone, japanese' => [null, null, 'きよみずでら', 'ja', 'きよみずでら', null];
        yield 'nothing at all' => [null, null, null, 'en', null, null];
        yield 'blanks count for nothing' => ['  ', '', null, 'en', null, null];
    }

    #[DataProvider('chains')]
    public function test_the_chains_answer_what_the_locale_asks_for(
        ?string $romanized,
        ?string $kanji,
        ?string $kana,
        string $locale,
        ?string $display,
        ?string $secondary,
    ): void {
        $location = new Location()
            ->setRomanizedName($romanized)
            ->setKanjiName($kanji)
            ->setKanaName($kana);

        $this->assertSame($display, $location->getDisplayName($locale), 'The displayed name is not the one the chain names.');
        $this->assertSame($secondary, $location->getSecondaryName($locale), 'The secondary name is not the one the chain names.');
    }

    public function test_the_secondary_name_never_repeats_the_displayed_one(): void
    {
        $location = new Location()->setRomanizedName('Kiyomizu-dera')->setKanjiName('Kiyomizu-dera');

        $this->assertSame('Kiyomizu-dera', $location->getDisplayName('en'));
        $this->assertNull($location->getSecondaryName('en'), 'The same name was stated twice.');
    }

    public function test_a_name_is_required_but_any_of_the_three_will_do(): void
    {
        $this->assertFalse(new Location()->hasAName());
        $this->assertFalse(new Location()->setKanaName('   ')->hasAName());
        $this->assertTrue(new Location()->setRomanizedName('Kiyomizu-dera')->hasAName());
        $this->assertTrue(new Location()->setKanjiName('清水寺')->hasAName());
        $this->assertTrue(new Location()->setKanaName('きよみずでら')->hasAName());
    }

    public function test_japanese_is_ordered_by_its_reading_and_the_rest_by_the_latin_name(): void
    {
        $this->assertSame(['kanaName', 'kanjiName', 'romanizedName'], Location::orderFields('ja'));
        $this->assertSame(['romanizedName', 'kanjiName', 'kanaName'], Location::orderFields('en'));
        $this->assertSame(['kanjiName', 'kanaName', 'romanizedName'], Location::displayFields('ja'));
        $this->assertSame(['romanizedName', 'kanjiName', 'kanaName'], Location::displayFields('en'));
    }

    public function test_a_new_name_lands_on_the_field_the_locale_leads_with(): void
    {
        $this->assertSame('清水寺', new Location()->setDisplayName('ja', '清水寺')->getKanjiName());
        $this->assertNull(new Location()->setDisplayName('ja', '清水寺')->getRomanizedName());
        $this->assertSame('Kiyomizu-dera', new Location()->setDisplayName('en', 'Kiyomizu-dera')->getRomanizedName());
    }
}
