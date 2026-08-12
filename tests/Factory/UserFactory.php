<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    public function admin(): static
    {
        return $this->with(['roles' => ['ROLE_ADMIN']]);
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->firstName(),
            'email' => self::faker()->unique()->safeEmail(),
            'plainPassword' => 'a-long-enough-password',
            'enabled' => true,
        ];
    }
}
