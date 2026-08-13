<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Location;
use App\Enum\LocationType;
use App\Repository\LocationRepository;
use App\Service\Geocoder;
use App\Service\GeocoderFailed;
use App\Service\LocationTypeGuesser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

#[AsLiveComponent]
class LocationCombobox
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp]
    public string $name = '';

    #[LiveProp]
    public string $id = '';

    #[LiveProp(writable: true)]
    public ?string $selected = null;

    #[LiveProp(writable: true)]
    public string $term = '';

    #[LiveProp(writable: true)]
    public bool $creating = false;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $newRomanizedName = '';

    #[LiveProp(writable: true)]
    #[Assert\Length(max: 255)]
    public string $newJapaneseName = '';

    #[LiveProp(writable: true)]
    public ?string $newType = null;

    #[LiveProp(writable: true)]
    #[Assert\Length(max: 255)]
    public string $newLocality = '';

    #[LiveProp(writable: true)]
    #[Assert\Length(max: 255)]
    public string $newPrefecture = '';

    #[LiveProp(writable: true)]
    #[Assert\Length(max: 255)]
    public string $newAddress = '';

    #[LiveProp(writable: true)]
    public string $newLatitude = '';

    #[LiveProp(writable: true)]
    public string $newLongitude = '';

    #[LiveProp(writable: true)]
    public string $newNotes = '';

    #[LiveProp(writable: true)]
    public string $address = '';

    /** @var list<array{name: string, japaneseName: string, locality: string, prefecture: string, address: string, latitude: float, longitude: float}> */
    #[LiveProp]
    public array $found = [];

    #[LiveProp]
    public string $foundFor = '';

    #[LiveProp]
    public bool $failed = false;

    public function __construct(
        private readonly LocationRepository $locations,
        private readonly LocationTypeGuesser $guesser,
        private readonly Geocoder $geocoder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[LiveAction]
    public function choose(#[LiveArg] string $location): void
    {
        $this->selected = $location;
        $this->term = '';
        $this->stopCreating();
    }

    #[LiveAction]
    public function clear(): void
    {
        $this->selected = null;
        $this->term = '';
        $this->stopCreating();
    }

    #[LiveAction]
    public function startCreating(): void
    {
        $this->newRomanizedName = trim($this->term);
        $this->newType = $this->guesser->guess($this->newRomanizedName)?->value;
        $this->creating = true;
    }

    #[LiveAction]
    public function cancelCreating(): void
    {
        $this->stopCreating();
    }

    #[LiveAction]
    public function create(): void
    {
        $this->validate();

        $location = new Location();
        $location
            ->setRomanizedName(trim($this->newRomanizedName))
            ->setJapaneseName($this->emptyToNull($this->newJapaneseName))
            ->setType($this->newType === null ? null : LocationType::from($this->newType))
            ->setLocality($this->emptyToNull($this->newLocality))
            ->setPrefecture($this->emptyToNull($this->newPrefecture))
            ->setAddress($this->emptyToNull($this->newAddress))
            ->setLatitude($this->toCoordinate($this->newLatitude))
            ->setLongitude($this->toCoordinate($this->newLongitude))
            ->setNotes($this->emptyToNull($this->newNotes))
        ;

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        $this->selected = $location->getId();
        $this->term = '';
        $this->stopCreating();
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
        $this->newRomanizedName = $placeName;
        $this->newJapaneseName = $japaneseName;
        $this->newLocality = $locality;
        $this->newPrefecture = $prefecture;
        $this->newAddress = $address;
        $this->newLatitude = $latitude;
        $this->newLongitude = $longitude;
        $this->newType = $this->guesser->guess($this->inferrableName())?->value;
        $this->address = '';
    }

    #[LiveAction]
    public function inferType(): void
    {
        $this->newType = $this->guesser->guess($this->inferrableName())?->value;
    }

    public function isGeocoderAvailable(): bool
    {
        return $this->geocoder->isAvailable();
    }

    /**
     * @return list<array{name: string, japaneseName: string, locality: string, prefecture: string, latitude: float, longitude: float}>
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
    public function getResults(): array
    {
        if (trim($this->term) === '') {
            return [];
        }

        return $this->locations->search(trim($this->term));
    }

    public function getChosen(): ?Location
    {
        return $this->selected === null || $this->selected === '' ? null : $this->locations->find($this->selected);
    }

    public function getCreatable(): ?string
    {
        $term = trim($this->term);

        if ($term === '') {
            return null;
        }

        foreach ($this->getResults() as $location) {
            if (mb_strtolower((string) $location->getRomanizedName()) === mb_strtolower($term)) {
                return null;
            }
        }

        return $term;
    }

    /**
     * @return list<Location>
     */
    public function getDuplicates(): array
    {
        $name = trim($this->newRomanizedName);

        return $name === '' ? [] : $this->locations->namedExactly($name);
    }

    /**
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        $types = [];

        foreach (LocationType::cases() as $type) {
            $types[$type->value] = 'label.'.$type->value;
        }

        return $types;
    }

    private function stopCreating(): void
    {
        $this->creating = false;
        $this->newRomanizedName = '';
        $this->newJapaneseName = '';
        $this->newType = null;
        $this->newLocality = '';
        $this->newPrefecture = '';
        $this->newAddress = '';
        $this->newLatitude = '';
        $this->newLongitude = '';
        $this->newNotes = '';
        $this->address = '';
        $this->found = [];
        $this->foundFor = '';
        $this->failed = false;
        $this->resetValidation();
    }

    private function inferrableName(): string
    {
        return trim($this->newJapaneseName) !== '' ? trim($this->newJapaneseName) : trim($this->newRomanizedName);
    }

    private function emptyToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }

    private function toCoordinate(string $value): ?float
    {
        return is_numeric(trim($value)) ? (float) trim($value) : null;
    }
}
