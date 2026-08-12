<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;

trait IgnoresOwnership
{
    private function unfiltered(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        $manager->clear();
        $filters = $manager->getFilters();

        if ($filters->isEnabled('ownership')) {
            $filters->disable('ownership');
        }

        return $manager;
    }
}
