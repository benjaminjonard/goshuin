<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LocationPhoto;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AttachedPhotoRepository<LocationPhoto>
 */
class LocationPhotoRepository extends AttachedPhotoRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocationPhoto::class);
    }
}
