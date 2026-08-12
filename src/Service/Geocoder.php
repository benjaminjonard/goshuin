<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class Geocoder
{
    private const int MINIMUM_QUERY = 3;

    private const float TIMEOUT = 20.0;

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire('%app.photon_host_url%')] private string $host,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->host !== '';
    }

    /**
     * @return list<array{name: string, japaneseName: string, locality: string, address: string, latitude: float, longitude: float}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if (!$this->isAvailable() || mb_strlen($query) < self::MINIMUM_QUERY) {
            return [];
        }

        $pending = [
            'romanised' => $this->ask($query, 'en', $limit),
            'local' => $this->ask($query, 'default', $limit),
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

        $local = $this->readIfReady($pending['local']);

        $places = [];

        foreach ($romanised as $id => $feature) {
            $places[] = [
                'name' => $feature['name'],
                'japaneseName' => $local[$id]['name'] ?? '',
                'locality' => $feature['locality'],
                'address' => $feature['address'],
                'latitude' => $feature['latitude'],
                'longitude' => $feature['longitude'],
            ];
        }

        return $places;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function address(array $properties): string
    {
        $house = trim(($properties['housenumber'] ?? '').' '.($properties['street'] ?? ''));

        $parts = array_filter([
            $house,
            $properties['district'] ?? null,
            $properties['city'] ?? null,
            $properties['state'] ?? null,
            $properties['postcode'] ?? null,
            $properties['country'] ?? null,
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode(', ', array_map('trim', $parts));
    }

    private function ask(string $query, string $language, int $limit): ?ResponseInterface
    {
        try {
            return $this->client->request('GET', rtrim($this->host, '/').'/api/', [
                'query' => ['q' => $query, 'limit' => $limit, 'lang' => $language],
                'headers' => ['User-Agent' => 'Goshuin (self-hosted goshuin collection)'],
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
                'max_connect_duration' => self::TIMEOUT,
            ]);
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @return array<string, array{name: string, locality: string, address: string, latitude: float, longitude: float}>
     */
    /**
     * @return array<string, array{name: string, locality: string, address: string, latitude: float, longitude: float}>
     */
    private function readIfReady(?ResponseInterface $response): array
    {
        if ($response === null) {
            return [];
        }

        try {
            foreach ($this->client->stream([$response], 0) as $chunk) {
                if ($chunk->isLast()) {
                    return $this->read($response, false);
                }

                if ($chunk->isTimeout()) {
                    break;
                }
            }
        } catch (ExceptionInterface) {
            return [];
        }

        $response->cancel();

        return [];
    }

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

            $found[$properties['osm_type'].$properties['osm_id']] = [
                'name' => (string) $properties['name'],
                'locality' => (string) ($properties['city'] ?? $properties['state'] ?? ''),
                'address' => $this->address($properties),
                'latitude' => (float) $coordinates[1],
                'longitude' => (float) $coordinates[0],
            ];
        }

        return $found;
    }
}
