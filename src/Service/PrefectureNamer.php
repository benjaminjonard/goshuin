<?php

declare(strict_types=1);

namespace App\Service;

final readonly class PrefectureNamer
{
    private const string SUFFIX = ' Prefecture';

    private const array KANJI = [
        '北海道' => 'Hokkaido',
        '青森県' => 'Aomori',
        '岩手県' => 'Iwate',
        '宮城県' => 'Miyagi',
        '秋田県' => 'Akita',
        '山形県' => 'Yamagata',
        '福島県' => 'Fukushima',
        '茨城県' => 'Ibaraki',
        '栃木県' => 'Tochigi',
        '群馬県' => 'Gunma',
        '埼玉県' => 'Saitama',
        '千葉県' => 'Chiba',
        '東京都' => 'Tokyo',
        '神奈川県' => 'Kanagawa',
        '新潟県' => 'Niigata',
        '富山県' => 'Toyama',
        '石川県' => 'Ishikawa',
        '福井県' => 'Fukui',
        '山梨県' => 'Yamanashi',
        '長野県' => 'Nagano',
        '岐阜県' => 'Gifu',
        '静岡県' => 'Shizuoka',
        '愛知県' => 'Aichi',
        '三重県' => 'Mie',
        '滋賀県' => 'Shiga',
        '京都府' => 'Kyoto',
        '大阪府' => 'Osaka',
        '兵庫県' => 'Hyogo',
        '奈良県' => 'Nara',
        '和歌山県' => 'Wakayama',
        '鳥取県' => 'Tottori',
        '島根県' => 'Shimane',
        '岡山県' => 'Okayama',
        '広島県' => 'Hiroshima',
        '山口県' => 'Yamaguchi',
        '徳島県' => 'Tokushima',
        '香川県' => 'Kagawa',
        '愛媛県' => 'Ehime',
        '高知県' => 'Kochi',
        '福岡県' => 'Fukuoka',
        '佐賀県' => 'Saga',
        '長崎県' => 'Nagasaki',
        '熊本県' => 'Kumamoto',
        '大分県' => 'Oita',
        '宮崎県' => 'Miyazaki',
        '鹿児島県' => 'Kagoshima',
        '沖縄県' => 'Okinawa',
    ];

    private const array VARIANTS = [
        'Hokkaidō' => 'Hokkaido',
        'Tōkyō' => 'Tokyo',
        'Kyōto' => 'Kyoto',
        'Ōsaka' => 'Osaka',
        'Hyōgo' => 'Hyogo',
        'Kōchi' => 'Kochi',
        'Ōita' => 'Oita',
        'Gumma' => 'Gunma',
    ];

    public function name(?string $state): string
    {
        $state = trim((string) $state);

        return $this->canonical($state) ?? $state;
    }

    public function recognise(?string $place): string
    {
        return $this->canonical(trim((string) $place)) ?? '';
    }

    private function canonical(string $state): ?string
    {
        if ($state === '') {
            return null;
        }

        if (isset(self::KANJI[$state])) {
            return self::KANJI[$state];
        }

        if (isset(self::VARIANTS[$state])) {
            return self::VARIANTS[$state];
        }

        if (\in_array($state, self::KANJI, true)) {
            return $state;
        }

        return str_ends_with($state, self::SUFFIX) ? $this->canonical(substr($state, 0, -\strlen(self::SUFFIX))) : null;
    }
}
