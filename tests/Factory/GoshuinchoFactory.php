<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Goshuincho;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Goshuincho>
 */
final class GoshuinchoFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Goshuincho::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'title' => self::faker()->words(3, true),
            'owner' => UserFactory::new(),
        ];
    }
}
