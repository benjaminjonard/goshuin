<?php

declare(strict_types=1);

namespace App\Tests\App\Service;

use App\Service\PrefectureNamer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrefectureNamerTest extends TestCase
{
    #[DataProvider('states')]
    public function test_it_names_what_photon_returns(?string $state, string $expected): void
    {
        $this->assertSame($expected, (new PrefectureNamer())->name($state));
    }

    public static function states(): iterable
    {
        yield 'kanji' => ['東京都', 'Tokyo'];
        yield 'kanji ending in 府' => ['京都府', 'Kyoto'];
        yield 'kanji ending in 道' => ['北海道', 'Hokkaido'];
        yield 'kanji ending in 県' => ['広島県', 'Hiroshima'];

        yield 'the long form Photon returns' => ['Kyoto Prefecture', 'Kyoto'];
        yield 'the long form of a metropolis' => ['Osaka Prefecture', 'Osaka'];
        yield 'already canonical' => ['Hokkaido', 'Hokkaido'];

        yield 'a macron' => ['Ōsaka', 'Osaka'];
        yield 'a macron mid-name' => ['Hyōgo', 'Hyogo'];
        yield 'a macron in the long form' => ['Ōita Prefecture', 'Oita'];

        yield 'a state that is not Japanese' => ['Santa Catarina', 'Santa Catarina'];
        yield 'an unrecognised long form keeps its suffix' => ['Atlantis Prefecture', 'Atlantis Prefecture'];

        yield 'nothing at all' => [null, ''];
        yield 'an empty state' => ['', ''];
        yield 'whitespace only' => ['   ', ''];
        yield 'surrounding whitespace' => ['  東京都  ', 'Tokyo'];
    }

    #[DataProvider('prefectures')]
    public function test_every_prefecture_is_reachable_from_its_kanji(string $kanji, string $expected): void
    {
        $this->assertSame($expected, (new PrefectureNamer())->name($kanji));
    }

    #[DataProvider('prefectures')]
    public function test_every_canonical_name_names_itself(string $kanji, string $expected): void
    {
        $this->assertSame($expected, (new PrefectureNamer())->name($expected), 'A canonical name was not recognised as one.');
    }

    #[DataProvider('prefectures')]
    public function test_every_prefecture_is_reachable_from_the_long_form(string $kanji, string $expected): void
    {
        $this->assertSame($expected, (new PrefectureNamer())->name($expected.' Prefecture'));
    }

    #[DataProvider('prefectures')]
    public function test_every_prefecture_is_recognised_from_its_kanji(string $kanji, string $expected): void
    {
        $this->assertSame($expected, (new PrefectureNamer())->recognise($kanji));
    }

    #[DataProvider('places')]
    public function test_it_recognises_only_a_prefecture(?string $place, string $expected): void
    {
        $this->assertSame($expected, (new PrefectureNamer())->recognise($place));
    }

    public static function places(): iterable
    {
        yield 'a canonical name' => ['Tokyo', 'Tokyo'];
        yield 'a long form' => ['Kyoto Prefecture', 'Kyoto'];
        yield 'a macron' => ['Ōsaka', 'Osaka'];

        yield 'a city that is not a prefecture' => ['Kamakura', ''];
        yield 'a ward' => ['Taito', ''];
        yield 'a place outside Japan' => ['Santa Catarina', ''];

        yield 'nothing at all' => [null, ''];
        yield 'an empty place' => ['', ''];
        yield 'whitespace only' => ['   ', ''];
        yield 'surrounding whitespace' => ['  Tokyo  ', 'Tokyo'];
    }

    public static function prefectures(): iterable
    {
        yield ['北海道', 'Hokkaido'];
        yield ['青森県', 'Aomori'];
        yield ['岩手県', 'Iwate'];
        yield ['宮城県', 'Miyagi'];
        yield ['秋田県', 'Akita'];
        yield ['山形県', 'Yamagata'];
        yield ['福島県', 'Fukushima'];
        yield ['茨城県', 'Ibaraki'];
        yield ['栃木県', 'Tochigi'];
        yield ['群馬県', 'Gunma'];
        yield ['埼玉県', 'Saitama'];
        yield ['千葉県', 'Chiba'];
        yield ['東京都', 'Tokyo'];
        yield ['神奈川県', 'Kanagawa'];
        yield ['新潟県', 'Niigata'];
        yield ['富山県', 'Toyama'];
        yield ['石川県', 'Ishikawa'];
        yield ['福井県', 'Fukui'];
        yield ['山梨県', 'Yamanashi'];
        yield ['長野県', 'Nagano'];
        yield ['岐阜県', 'Gifu'];
        yield ['静岡県', 'Shizuoka'];
        yield ['愛知県', 'Aichi'];
        yield ['三重県', 'Mie'];
        yield ['滋賀県', 'Shiga'];
        yield ['京都府', 'Kyoto'];
        yield ['大阪府', 'Osaka'];
        yield ['兵庫県', 'Hyogo'];
        yield ['奈良県', 'Nara'];
        yield ['和歌山県', 'Wakayama'];
        yield ['鳥取県', 'Tottori'];
        yield ['島根県', 'Shimane'];
        yield ['岡山県', 'Okayama'];
        yield ['広島県', 'Hiroshima'];
        yield ['山口県', 'Yamaguchi'];
        yield ['徳島県', 'Tokushima'];
        yield ['香川県', 'Kagawa'];
        yield ['愛媛県', 'Ehime'];
        yield ['高知県', 'Kochi'];
        yield ['福岡県', 'Fukuoka'];
        yield ['佐賀県', 'Saga'];
        yield ['長崎県', 'Nagasaki'];
        yield ['熊本県', 'Kumamoto'];
        yield ['大分県', 'Oita'];
        yield ['宮崎県', 'Miyazaki'];
        yield ['鹿児島県', 'Kagoshima'];
        yield ['沖縄県', 'Okinawa'];
    }

    public function test_all_forty_seven_are_covered(): void
    {
        $this->assertCount(47, iterator_to_array(self::prefectures(), false), 'A prefecture is missing from the mapping.');
    }
}
