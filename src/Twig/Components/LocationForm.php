<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Deity;
use App\Entity\Location;
use App\Form\Type\LocationType;
use App\Repository\DeityRepository;
use App\Repository\LocationRepository;
use App\Service\Geocoder;
use App\Service\GeocoderFailed;
use App\Service\LocationTypeGuesser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class LocationForm
{
    use ComponentToolsTrait;
    use LiveCollectionTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(fieldName: 'edited')]
    public ?Location $location = null;

    #[LiveProp]
    public string $named = '';

    #[LiveProp(writable: true)]
    public string $address = '';

    /** @var list<array{name: string, japaneseName: string, locality: string, prefecture: string, address: string, latitude: float, longitude: float}> */
    #[LiveProp]
    public array $found = [];

    #[LiveProp]
    public string $foundFor = '';

    #[LiveProp]
    public bool $failed = false;

    /** @var array<int, list<string>>|null */
    private ?array $offered = null;

    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly LocationRepository $locations,
        private readonly DeityRepository $deities,
        private readonly LocationTypeGuesser $guesser,
        private readonly Geocoder $geocoder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[LiveAction]
    public function usePlace(
        #[LiveArg] string $placeName,
        #[LiveArg] string $japaneseName,
        #[LiveArg] string $locality,
        #[LiveArg] string $prefecture,
        #[LiveArg] string $address,
        #[LiveArg] string $latitude,
        #[LiveArg] string $longitude,
    ): void {
        $this->formValues['romanizedName'] = $placeName;
        $this->formValues['japaneseName'] = $japaneseName;
        $this->formValues['locality'] = $locality;
        $this->formValues['prefecture'] = $prefecture;
        $this->formValues['address'] = $address;
        $this->formValues['latitude'] = $latitude;
        $this->formValues['longitude'] = $longitude;
        $this->formValues['type'] = $this->guesser->guess($japaneseName !== '' ? $japaneseName : $placeName)?->value ?? '';
        $this->address = '';
    }

    #[LiveAction]
    public function create(): void
    {
        if ($this->location !== null) {
            throw new BadRequestHttpException('An existing location is saved by its own form.');
        }

        $this->submitForm();

        $location = $this->getForm()->getData();

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        $this->emitUp('location:created', ['location' => $location->getId()]);
    }

    #[LiveAction]
    public function useDeity(#[LiveArg] int $row, #[LiveArg] string $name): void
    {
        $this->formValues['deities'][$row] = $name;
        $this->offered = null;
    }

    /**
     * Rows worth a panel, each holding what was found — an empty list saying so.
     *
     * @return array<int, list<string>>
     */
    public function getDeitySuggestions(): array
    {
        if ($this->offered !== null) {
            return $this->offered;
        }

        $this->offered = [];

        foreach ($this->formValues['deities'] ?? [] as $row => $typed) {
            $typed = trim((string) $typed);

            if ($typed === '' || $this->deities->namedExactly($typed) !== null) {
                continue;
            }

            $this->offered[$row] = array_map(
                static fn (Deity $deity): string => (string) $deity->getName(),
                $this->deities->search($typed),
            );
        }

        return $this->offered;
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->emitUp('location:cancelled');
    }

    public function isCreating(): bool
    {
        return $this->location === null;
    }

    public function isGeocoderAvailable(): bool
    {
        return $this->geocoder->isAvailable();
    }

    /**
     * @return list<array{name: string, japaneseName: string, locality: string, prefecture: string, address: string, latitude: float, longitude: float}>
     */
    public function getPlaces(): array
    {
        $query = trim($this->address);

        if ($query === $this->foundFor) {
            return $this->found;
        }

        $this->foundFor = $query;

        try {
            $this->found = $this->geocoder->search($query);
            $this->failed = false;
        } catch (GeocoderFailed) {
            $this->found = [];
            $this->failed = true;
        }

        return $this->found;
    }

    public function hasFailed(): bool
    {
        $this->getPlaces();

        return $this->failed;
    }

    public function isSearchable(): bool
    {
        return mb_strlen(trim($this->address)) >= 3;
    }

    /**
     * @return list<Location>
     */
    public function getDuplicates(): array
    {
        $name = trim((string) ($this->formValues['romanizedName'] ?? ''));

        if ($name === '' || $this->location !== null) {
            return [];
        }

        return $this->locations->namedExactly($name);
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->forms->create(LocationType::class, $this->location ?? $this->named(), [
            'editing' => $this->location !== null,
        ]);
    }

    private function named(): Location
    {
        $location = new Location();

        if ($this->named !== '') {
            $location
                ->setRomanizedName($this->named)
                ->setType($this->guesser->guess($this->named))
            ;
        }

        return $location;
    }
}
