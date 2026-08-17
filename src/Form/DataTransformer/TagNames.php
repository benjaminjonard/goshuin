<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<Collection<int, Tag>, string>
 */
final readonly class TagNames implements DataTransformerInterface
{
    public function __construct(
        private TagRepository $tags,
    ) {
    }

    /**
     * @param Collection<int, Tag>|null $value
     */
    #[\Override]
    public function transform(mixed $value): string
    {
        if (!$value instanceof Collection) {
            return '';
        }

        return implode(', ', $value->map(static fn (Tag $tag): string => (string) $tag->getName())->toArray());
    }

    /**
     * @return Collection<int, Tag>
     */
    #[\Override]
    public function reverseTransform(mixed $value): Collection
    {
        $named = [];

        foreach (explode(',', (string) $value) as $name) {
            $name = trim($name);

            if ($name === '' || isset($named[mb_strtolower($name)])) {
                continue;
            }

            $named[mb_strtolower($name)] = $this->tags->namedExactly($name) ?? new Tag()->setName($name);
        }

        return new ArrayCollection(array_values($named));
    }
}
