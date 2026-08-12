<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Enum\LocationType;
use App\Service\LocationTypeGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocationTypeGuesserTest extends TestCase
{
    #[DataProvider('names')]
    public function test_it_infers_from_the_end_of_the_name(string $name, ?LocationType $expected): void
    {
        $this->assertSame($expected, (new LocationTypeGuesser())->guess($name));
    }

    public static function names(): iterable
    {
        yield '伏見稲荷大社' => ['伏見稲荷大社', LocationType::Shrine];
        yield '明治神宮' => ['明治神宮', LocationType::Shrine];
        yield '春日大社' => ['春日大社', LocationType::Shrine];
        yield '太宰府天満宮' => ['太宰府天満宮', LocationType::Shrine];
        yield '鶴岡八幡宮' => ['鶴岡八幡宮', LocationType::Shrine];
        yield '八坂神社' => ['八坂神社', LocationType::Shrine];

        yield '清水寺' => ['清水寺', LocationType::Temple];
        yield '平等院' => ['平等院', LocationType::Temple];
        yield '三十三間堂' => ['三十三間堂', LocationType::Temple];
        yield '川崎大師' => ['川崎大師', LocationType::Temple];

        yield '広島城' => ['広島城', LocationType::Other];
        yield '安土城跡' => ['安土城跡', LocationType::Other];

        yield 'Fushimi Inari-taisha' => ['Fushimi Inari-taisha', LocationType::Shrine];
        yield 'Kiyomizu-dera' => ['Kiyomizu-dera', LocationType::Temple];
        yield 'Meiji-jingu' => ['Meiji-jingu', LocationType::Shrine];
        yield 'Meiji-jingū' => ['Meiji-jingū', LocationType::Shrine];
        yield 'Byodo-in' => ['Byodo-in', LocationType::Temple];
        yield 'Hiroshima-jo' => ['Hiroshima-jo', LocationType::Other];
        yield 'case is ignored' => ['Kiyomizu-DERA', LocationType::Temple];

        yield 'nothing recognised' => ['Some Place', null];
        yield 'romaji without a hyphen infers nothing' => ['Kiyomizudera', null];
        yield 'a hyphen with an unknown tail' => ['Nijo-castle', null];
        yield 'an empty name' => ['', null];
        yield 'whitespace only' => ['   ', null];
    }

    public function test_a_temple_attached_to_a_shrine_is_a_temple(): void
    {
        $this->assertSame(LocationType::Temple, (new LocationTypeGuesser())->guess('神宮寺'));
    }

    public function test_it_never_matches_inside_the_name(): void
    {
        $guesser = new LocationTypeGuesser();

        $this->assertNull($guesser->guess('神社通り'), 'A suffix inside the name was read as one.');
        $this->assertNull($guesser->guess('寺町商店街'), 'A suffix at the start was read as one.');
        $this->assertNull($guesser->guess('taisha-mae'), 'A romaji suffix before the hyphen was read as one.');
    }

    public function test_the_longer_suffix_wins_over_the_shorter_one(): void
    {
        $guesser = new LocationTypeGuesser();

        $this->assertSame(LocationType::Other, $guesser->guess('安土城跡'));
        $this->assertSame(LocationType::Other, $guesser->guess('安土城'));
    }
}
