<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Municipality
{
    private const string INDEX = '/data/geo/municipalities.json.gz';

    /**
     * @var list<array{code: string, bbox: array{float, float, float, float}, rings: list<list<array{float, float}>>}>|null
     */
    private ?array $units = null;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')] private readonly string $projectDir,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function at(float $latitude, float $longitude): ?string
    {
        foreach ($this->units() as $unit) {
            [$west, $south, $east, $north] = $unit['bbox'];

            if ($longitude < $west || $longitude > $east || $latitude < $south || $latitude > $north) {
                continue;
            }

            if ($this->encloses($unit['rings'], $latitude, $longitude)) {
                return $unit['code'];
            }
        }

        return null;
    }

    /**
     * @return list<array{code: string, bbox: array{float, float, float, float}, rings: list<list<array{float, float}>>}>
     */
    private function units(): array
    {
        if ($this->units !== null) {
            return $this->units;
        }

        $path = $this->projectDir.self::INDEX;
        $packed = @file_get_contents($path);
        $plain = $packed === false ? false : @gzdecode($packed);
        $units = $plain === false ? null : json_decode($plain, true);

        if (!\is_array($units)) {
            $this->logger?->error('The municipality index could not be read; every location will be left without a code.', ['path' => $path]);

            return $this->units = [];
        }

        return $this->units = $units;
    }

    /**
     * @param list<list<array{float, float}>> $rings
     */
    private function encloses(array $rings, float $latitude, float $longitude): bool
    {
        $inside = false;

        foreach ($rings as $ring) {
            $vertices = \count($ring);

            for ($i = 0, $j = $vertices - 1; $i < $vertices; $j = $i++) {
                [$x, $y] = $ring[$i];
                [$previousX, $previousY] = $ring[$j];

                if ($y > $latitude === $previousY > $latitude) {
                    continue;
                }

                if ($longitude < ($previousX - $x) * ($latitude - $y) / ($previousY - $y) + $x) {
                    $inside = !$inside;
                }
            }
        }

        return $inside;
    }
}
