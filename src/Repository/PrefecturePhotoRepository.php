<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PrefecturePhoto;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AttachedPhotoRepository<PrefecturePhoto>
 */
class PrefecturePhotoRepository extends AttachedPhotoRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrefecturePhoto::class);
    }
}
