<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\GeocoderFailed;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class Geocoder
{
    private const int MINIMUM_QUERY = 3;

    private const float TIMEOUT = 20.0;

    private const float PACE = 1.0;

    private const int SPREAD = 4;

    private const string CITYLESS = 'Tokyo';

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire('%app.photon_host_url%')] private string $host,
        private PrefectureNamer $namer,
        private float $pace = self::PACE,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->host !== '';
    }

    /**
     * @return list<array{name: string, japaneseName: string, locality: string, prefecture: string, address: string, latitude: float, longitude: float}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if (!$this->isAvailable() || mb_strlen($query) < self::MINIMUM_QUERY) {
            return [];
        }

        $started = microtime(true);

        try {
            $pending = [
                'romanised' => $this->ask($query, 'en', $limit),
                'local' => $this->ask($query, 'default', $limit * self::SPREAD),
            ];

            try {
                if ($pending['romanised'] === null) {
                    throw new GeocoderFailed('The geocoder could not be reached.');
                }

                $romanised = $this->read($pending['romanised'], true);
            } catch (GeocoderFailed $failure) {
                $pending['local']?->cancel();

                throw $failure;
            }

            $local = $this->read($pending['local'], false);

            $places = [];

            foreach ($romanised as $id => $feature) {
                $places[] = [
                    'name' => $feature['name'],
                    'japaneseName' => $local[$id]['name'] ?? '',
                    'locality' => $feature['locality'],
                    'prefecture' => $feature['prefecture'],
                    'address' => $feature['address'],
                    'latitude' => $feature['latitude'],
                    'longitude' => $feature['longitude'],
                ];
            }

            return $places;
        } finally {
            $this->pace($started);
        }
    }

    private function pace(float $started): void
    {
        $remaining = $this->pace - (microtime(true) - $started);

        if ($remaining > 0) {
            usleep((int) round($remaining * 1_000_000));
        }
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function locality(array $properties): string
    {
        $city = trim((string) ($properties['city'] ?? ''));

        return $this->namer->recognise($city) === self::CITYLESS ? '' : $city;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function address(array $properties, string $prefecture): string
    {
        $house = trim(($properties['housenumber'] ?? '').' '.($properties['street'] ?? ''));

        $parts = array_filter([
            $house,
            $properties['district'] ?? null,
            $properties['city'] ?? null,
            $prefecture,
            $properties['postcode'] ?? null,
            $properties['country'] ?? null,
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        $kept = [];

        foreach (array_map('trim', $parts) as $part) {
            if ($kept === [] || $part !== $kept[array_key_last($kept)]) {
                $kept[] = $part;
            }
        }

        return implode(', ', $kept);
    }

    private function ask(string $query, string $language, int $limit): ?ResponseInterface
    {
        try {
            return $this->client->request('GET', rtrim($this->host, '/').'/api/', [
                'query' => ['q' => $query, 'limit' => $limit, 'lang' => $language],
                'headers' => ['User-Agent' => 'Goshuin (self-hosted)'],
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
                'max_connect_duration' => self::TIMEOUT,
            ]);
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @return array<string, array{name: string, locality: string, prefecture: string, address: string, latitude: float, longitude: float}>
     */
    private function read(?ResponseInterface $response, bool $required): array
    {
        if ($response === null) {
            return [];
        }

        try {
            $features = $response->toArray()['features'] ?? [];
        } catch (ExceptionInterface $exception) {
            if ($required) {
                throw new GeocoderFailed('The geocoder answered with an error.', previous: $exception);
            }

            return [];
        }

        $found = [];

        foreach ($features as $feature) {
            $properties = $feature['properties'] ?? [];
            $coordinates = $feature['geometry']['coordinates'] ?? null;

            if (!isset($properties['name'], $properties['osm_id'], $properties['osm_type']) || !\is_array($coordinates)) {
                continue;
            }

            $prefecture = $this->namer->name((string) ($properties['state'] ?? ''));

            if ($prefecture === '' && ($properties['countrycode'] ?? '') === 'JP') {
                $prefecture = $this->namer->recognise((string) ($properties['city'] ?? ''));
            }

            $found[$properties['osm_type'].$properties['osm_id']] = [
                'name' => (string) $properties['name'],
                'locality' => $this->locality($properties),
                'prefecture' => $prefecture,
                'address' => $this->address($properties, $prefecture),
                'latitude' => (float) $coordinates[1],
                'longitude' => (float) $coordinates[0],
            ];
        }

        return $found;
    }
}
