<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Goshuin>
 */
final class GoshuinFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Goshuin::class;
    }

    public function in(Goshuincho $goshuincho, int $position = 1): static
    {
        return $this->with(['goshuincho' => $goshuincho, 'position' => $position]);
    }

    public function on(string $day): static
    {
        return $this->with(['receivedOn' => new \DateTimeImmutable($day)]);
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Goshuin $goshuin): void {
            if (!$goshuin->getOwner() instanceof User) {
                $goshuin->setOwner($goshuin->getGoshuincho()?->getOwner());
            }
        });
    }

    #[\Override]
    protected function defaults(): array
    {
        $stem = bin2hex(random_bytes(4));

        return [
            'goshuincho' => GoshuinchoFactory::new(),
            'location' => LocationFactory::new(),
            'position' => 1,
            'receivedOn' => new \DateTimeImmutable('2025-03-15'),
            'image' => 'ab/cd/'.$stem.'.jpg',
            'imageMini' => 'ab/cd/'.$stem.'-96.jpg',
            'imageCard' => 'ab/cd/'.$stem.'-384.jpg',
            'imageFull' => 'ab/cd/'.$stem.'-1200.jpg',
        ];
    }
}
