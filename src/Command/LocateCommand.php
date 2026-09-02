<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\LocationRepository;
use App\Service\Municipality;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:locate', description: 'Fill in the JIS administrative code of every location that has coordinates and none.')]
final readonly class LocateCommand
{
    public function __construct(
        private LocationRepository $locations,
        private Municipality $municipality,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $pending = $this->locations->withoutMunicipalityCode();

        if ($pending === []) {
            $io->success('Every located location already carries a code.');

            return Command::SUCCESS;
        }

        $located = 0;
        $unmatched = [];

        foreach ($pending as $location) {
            $code = $this->municipality->at((float) $location->getLatitude(), (float) $location->getLongitude());

            if ($code === null) {
                $unmatched[] = sprintf('%s (%F, %F)', $location->getDisplayName('en') ?? $location->getId(), $location->getLatitude(), $location->getLongitude());

                continue;
            }

            $location->setMunicipalityCode($code);
            ++$located;
        }

        $this->entityManager->flush();

        if ($located > 0) {
            $io->success(sprintf('%d located.', $located));
        }

        if ($unmatched !== []) {
            $io->warning(sprintf('%d matched no administrative unit.', \count($unmatched)));
            $io->listing($unmatched);
        }

        if ($located === 0) {
            $io->error(sprintf('None of the %d located locations could be coded.', \count($pending)));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
