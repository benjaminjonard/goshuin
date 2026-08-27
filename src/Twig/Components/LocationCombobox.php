<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Location;
use App\Repository\LocationRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class LocationCombobox
{
    use DefaultActionTrait;

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

    #[LiveProp]
    public string $named = '';

    public function __construct(
        private readonly LocationRepository $locations,
        private readonly RequestStack $requests,
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
        $this->named = trim($this->term);
        $this->creating = true;
    }

    #[LiveListener('location:created')]
    public function useCreated(#[LiveArg] string $location): void
    {
        $this->selected = $location;
        $this->term = '';
        $this->stopCreating();
    }

    #[LiveListener('location:cancelled')]
    public function abandonCreating(): void
    {
        $this->stopCreating();
    }

    /**
     * @return list<Location>
     */
    public function getResults(): array
    {
        if (trim($this->term) === '') {
            return [];
        }

        return $this->locations->search(trim($this->term), $this->locale());
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

        $sought = mb_strtolower($term);

        foreach ($this->getResults() as $location) {
            foreach ([$location->getRomanizedName(), $location->getKanjiName(), $location->getKanaName()] as $name) {
                if (mb_strtolower(trim((string) $name)) === $sought) {
                    return null;
                }
            }
        }

        return $term;
    }

    private function stopCreating(): void
    {
        $this->creating = false;
        $this->named = '';
    }
    private function locale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
