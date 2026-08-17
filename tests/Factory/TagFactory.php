<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Tag;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Tag>
 */
final class TagFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Tag::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
        ];
    }
}
