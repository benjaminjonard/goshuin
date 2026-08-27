<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Deity;
use App\Repository\DeityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<Collection<int, Deity>, list<string>>
 */
final readonly class DeityNames implements DataTransformerInterface
{
    public function __construct(
        private DeityRepository $deities,
        private RequestStack $requests,
    ) {
    }

    /**
     * @param Collection<int, Deity>|null $value
     *
     * @return list<string>
     */
    #[\Override]
    public function transform(mixed $value): array
    {
        if (!$value instanceof Collection) {
            return [''];
        }

        $named = array_values($value->map(fn (Deity $deity): string => (string) $deity->getDisplayName($this->locale()))->toArray());
        sort($named);

        return $named === [] ? [''] : $named;
    }

    /**
     * @param list<string|null>|null $value
     *
     * @return Collection<int, Deity>
     */
    #[\Override]
    public function reverseTransform(mixed $value): Collection
    {
        $named = [];

        foreach ($value ?? [] as $name) {
            $name = trim((string) $name);

            if ($name === '' || isset($named[mb_strtolower($name)])) {
                continue;
            }

            $named[mb_strtolower($name)] = $this->deities->namedExactly($name) ?? new Deity()->setDisplayName($this->locale(), $name);
        }

        return new ArrayCollection(array_values($named));
    }
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
