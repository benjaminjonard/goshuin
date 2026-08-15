<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CityPhoto;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AttachedPhotoRepository<CityPhoto>
 */
class CityPhotoRepository extends AttachedPhotoRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CityPhoto::class);
    }
}
