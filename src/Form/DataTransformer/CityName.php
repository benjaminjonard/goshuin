<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\City;
use App\Repository\CityRepository;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<City, string>
 */
final readonly class CityName implements DataTransformerInterface
{
    public function __construct(
        private CityRepository $cities,
    ) {
    }

    /**
     * @param City|null $value
     */
    #[\Override]
    public function transform(mixed $value): string
    {
        return $value instanceof City ? (string) $value->getName() : '';
    }

    /**
     * @param string|null $value
     */
    #[\Override]
    public function reverseTransform(mixed $value): ?City
    {
        $name = trim((string) $value);

        if ($name === '') {
            return null;
        }

        return $this->cities->namedExactly($name) ?? new City()->setName($name);
    }
}
