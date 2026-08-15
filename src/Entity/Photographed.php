<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Collection as DoctrineCollection;

interface Photographed
{
    public function getId(): string;

    /**
     * @return DoctrineCollection<int, AttachedPhoto>
     */
    public function getPhotos(): DoctrineCollection;

    /**
     * @return class-string<AttachedPhoto>
     */
    public static function photoClass(): string;
}
