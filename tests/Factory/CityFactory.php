<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\City;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<City>
 */
final class CityFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return City::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
        ];
    }
}
