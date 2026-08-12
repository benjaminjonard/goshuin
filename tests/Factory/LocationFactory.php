<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Location;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Location>
 */
final class LocationFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Location::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'romanizedName' => self::faker()->unique()->words(2, true),
        ];
    }
}
