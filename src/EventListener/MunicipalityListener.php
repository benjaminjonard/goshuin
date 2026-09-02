<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Location;
use App\Service\Municipality;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class MunicipalityListener
{
    public function __construct(
        private Municipality $municipality,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $location = $args->getObject();

        if (!$location instanceof Location) {
            return;
        }

        $this->locate($location);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $location = $args->getObject();

        if (!$location instanceof Location) {
            return;
        }

        if (!$args->hasChangedField('latitude') && !$args->hasChangedField('longitude')) {
            return;
        }

        $this->locate($location);

        $manager = $args->getObjectManager();
        $manager->getUnitOfWork()->recomputeSingleEntityChangeSet($manager->getClassMetadata(Location::class), $location);
    }

    private function locate(Location $location): void
    {
        $location->setMunicipalityCode(
            $location->hasCoordinates()
                ? $this->municipality->at((float) $location->getLatitude(), (float) $location->getLongitude())
                : null,
        );
    }
}
