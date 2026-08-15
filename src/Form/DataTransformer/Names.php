<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<list<string>, list<string>>
 */
final readonly class Names implements DataTransformerInterface
{
    /**
     * @param list<string>|null $value
     *
     * @return list<string>
     */
    #[\Override]
    public function transform(mixed $value): array
    {
        $named = $this->kept($value ?? []);

        return $named === [] ? [''] : $named;
    }

    /**
     * @param list<string|null>|null $value
     *
     * @return list<string>
     */
    #[\Override]
    public function reverseTransform(mixed $value): array
    {
        return $this->kept($value ?? []);
    }

    /**
     * @param list<string|null> $value
     *
     * @return list<string>
     */
    private function kept(array $value): array
    {
        $named = [];

        foreach ($value as $name) {
            $name = trim((string) $name);

            if ($name === '' || isset($named[mb_strtolower($name)])) {
                continue;
            }

            $named[mb_strtolower($name)] = $name;
        }

        return array_values($named);
    }
}
