<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Deity;
use App\Repository\DeityRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DeityLinker
{
    private const string LATIN = '[\p{Latin}\p{N}]';

    private const string ANCHOR = '<a href="%s" class="font-semibold text-accent-text no-underline hover:underline">%s</a>';

    public function __construct(
        private DeityRepository $deities,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function link(?string $text, ?Deity $except = null): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        $named = $this->named($except);

        if ($named === []) {
            return nl2br($this->escaped($text));
        }

        $html = '';

        foreach (preg_split($this->pattern($named), $text, -1, \PREG_SPLIT_DELIM_CAPTURE) as $at => $part) {
            $deity = $at % 2 === 0 ? null : $named[mb_strtolower($part)] ?? null;

            $html .= $deity === null
                ? $this->escaped($part)
                : sprintf(self::ANCHOR, $this->escaped($this->urls->generate('app_deity_show', ['slug' => $deity->getSlug()])), $this->escaped($part));
        }

        return nl2br($html);
    }

    /**
     * Every name a deity answers to, the longest first so the fullest one wins where two overlap.
     *
     * @return array<string, Deity>
     */
    private function named(?Deity $except): array
    {
        $named = [];

        foreach ($this->deities->findBy([], ['name' => 'ASC']) as $deity) {
            if ($except !== null && $deity->getId() === $except->getId()) {
                continue;
            }

            foreach ([$deity->getName(), ...$deity->getAdditionalNames()] as $name) {
                $name = trim((string) $name);

                if ($name !== '') {
                    $named[mb_strtolower($name)] ??= $deity;
                }
            }
        }

        uksort($named, static fn (string|int $a, string|int $b): int => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        return $named;
    }

    /**
     * @param array<string, Deity> $named
     */
    private function pattern(array $named): string
    {
        $alternatives = [];

        foreach (array_keys($named) as $name) {
            $name = (string) $name;

            // A Japanese name runs into the words around it, a Latin one must stand on its own.
            $alternatives[] = (preg_match('/^'.self::LATIN.'/u', $name) === 1 ? '(?<!'.self::LATIN.')' : '')
                .preg_quote($name, '/')
                .(preg_match('/'.self::LATIN.'$/u', $name) === 1 ? '(?!'.self::LATIN.')' : '');
        }

        return '/('.implode('|', $alternatives).')/iu';
    }

    private function escaped(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
