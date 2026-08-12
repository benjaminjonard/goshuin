<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\LocationType;

final readonly class LocationTypeGuesser
{
    private const array KANJI = [
        '城跡' => LocationType::Other,
        '天満宮' => LocationType::Shrine,
        '八幡宮' => LocationType::Shrine,
        '神社' => LocationType::Shrine,
        '大社' => LocationType::Shrine,
        '神宮' => LocationType::Shrine,
        '大師' => LocationType::Temple,
        '宮' => LocationType::Shrine,
        '寺' => LocationType::Temple,
        '院' => LocationType::Temple,
        '堂' => LocationType::Temple,
        '庵' => LocationType::Temple,
        '城' => LocationType::Other,
    ];

    private const array ROMAJI = [
        'jinja' => LocationType::Shrine,
        'taisha' => LocationType::Shrine,
        'jingu' => LocationType::Shrine,
        'jingū' => LocationType::Shrine,
        'tenmangu' => LocationType::Shrine,
        'tenmangū' => LocationType::Shrine,
        'hachimangu' => LocationType::Shrine,
        'hachimangū' => LocationType::Shrine,
        'gu' => LocationType::Shrine,
        'gū' => LocationType::Shrine,
        'ji' => LocationType::Temple,
        'dera' => LocationType::Temple,
        'tera' => LocationType::Temple,
        'in' => LocationType::Temple,
        'do' => LocationType::Temple,
        'dō' => LocationType::Temple,
        'an' => LocationType::Temple,
        'daishi' => LocationType::Temple,
        'jo' => LocationType::Other,
        'jō' => LocationType::Other,
    ];

    public function guess(?string $name): ?LocationType
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        foreach (self::KANJI as $suffix => $type) {
            if (str_ends_with($name, $suffix)) {
                return $type;
            }
        }

        $hyphen = mb_strrpos($name, '-');

        if ($hyphen === false) {
            return null;
        }

        return self::ROMAJI[mb_strtolower(mb_substr($name, $hyphen + 1))] ?? null;
    }
}
