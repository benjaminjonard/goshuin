<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Intl\Languages;

final readonly class LocaleHelper
{
    private const int REGIONAL_OFFSET = 0x1F1A5;

    /**
     * @param list<string> $locales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')] private array $locales,
    ) {
    }

    public function knows(string $locale): bool
    {
        return \in_array($locale, $this->locales, true);
    }

    /**
     * @return array<string, string>
     */
    public function choices(): array
    {
        $choices = [];

        foreach ($this->locales as $locale) {
            $choices[$this->label($locale)] = $locale;
        }

        return $choices;
    }

    private function label(string $locale): string
    {
        $name = Languages::getName($locale, $locale);

        return $this->flag($locale).' '.mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    private function flag(string $locale): string
    {
        $region = \Locale::getRegion(\Locale::addLikelySubtags($locale));

        if (mb_strlen($region) !== 2) {
            return '';
        }

        return mb_chr(self::REGIONAL_OFFSET + mb_ord($region[0], 'UTF-8'), 'UTF-8')
            .mb_chr(self::REGIONAL_OFFSET + mb_ord($region[1], 'UTF-8'), 'UTF-8');
    }
}
