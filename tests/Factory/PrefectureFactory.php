<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Prefecture;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Prefecture>
 */
final class PrefectureFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Prefecture::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
        ];
    }
}
