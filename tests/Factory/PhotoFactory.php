<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Goshuin;
use App\Entity\Photo;
use App\Entity\User;
use App\Enum\PhotoType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Photo>
 */
final class PhotoFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Photo::class;
    }

    public function of(Goshuin $goshuin, PhotoType $type = PhotoType::Location, int $position = 1): static
    {
        return $this->with(['goshuin' => $goshuin, 'type' => $type, 'position' => $position]);
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Photo $photo): void {
            if (!$photo->getOwner() instanceof User) {
                $photo->setOwner($photo->getGoshuin()?->getOwner());
            }
        });
    }

    #[\Override]
    protected function defaults(): array
    {
        $stem = bin2hex(random_bytes(4));

        return [
            'goshuin' => GoshuinFactory::new(),
            'type' => PhotoType::Location,
            'position' => 1,
            'image' => 'ef/01/'.$stem.'.jpg',
            'imageMini' => 'ef/01/'.$stem.'-96.jpg',
            'imageCard' => 'ef/01/'.$stem.'-384.jpg',
            'imageFull' => 'ef/01/'.$stem.'-1200.jpg',
        ];
    }
}
