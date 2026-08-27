<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\City;
use App\Repository\CityRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements DataTransformerInterface<City, string>
 */
final readonly class CityName implements DataTransformerInterface
{
    public function __construct(
        private CityRepository $cities,
        private RequestStack $requests,
    ) {
    }

    /**
     * @param City|null $value
     */
    #[\Override]
    public function transform(mixed $value): string
    {
        return $value instanceof City ? (string) $value->getDisplayName($this->locale()) : '';
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

        return $this->cities->namedExactly($name) ?? new City()->setDisplayName($this->locale(), $name);
    }
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
