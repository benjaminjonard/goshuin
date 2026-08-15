<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Prefecture;
use App\Repository\PrefectureRepository;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<Prefecture, string>
 */
final readonly class PrefectureName implements DataTransformerInterface
{
    public function __construct(
        private PrefectureRepository $prefectures,
    ) {
    }

    /**
     * @param Prefecture|null $value
     */
    #[\Override]
    public function transform(mixed $value): string
    {
        return $value instanceof Prefecture ? (string) $value->getName() : '';
    }

    /**
     * @param string|null $value
     */
    #[\Override]
    public function reverseTransform(mixed $value): ?Prefecture
    {
        $name = trim((string) $value);

        if ($name === '') {
            return null;
        }

        return $this->prefectures->namedExactly($name) ?? new Prefecture()->setName($name);
    }
}
