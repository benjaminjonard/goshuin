<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Deity;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Deity>
 */
final class DeityFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Deity::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'romanizedName' => self::faker()->unique()->words(2, true),
        ];
    }
}
