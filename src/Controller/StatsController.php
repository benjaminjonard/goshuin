<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\Shown;
use App\Model\Coverage;
use App\Model\Span;
use App\Model\Zone;
use App\Repository\GoshuinRepository;
use App\Repository\GoshuinchoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatsController extends AbstractController
{
    private const int EARLIEST_YEAR = 1868;
    private const array NO_ZONES = ['zones' => [], 'unlocated' => 0];
    private const array NO_SPANS = ['years' => [], 'months' => [], 'weekdays' => [], 'undated' => 0];

    public function __construct(
        private readonly GoshuinchoRepository $goshuinchos,
        private readonly GoshuinRepository $goshuins,
        private readonly TranslatorInterface $translator,
        private readonly Packages $assets,
        #[Autowire(param: 'kernel.project_dir')] private readonly string $projectDir,
        #[Autowire(param: 'app.map_attribution')] private readonly string $tileAttribution,
        #[Autowire(param: 'app.map_boundaries_attribution')] private readonly string $boundaryAttribution,
    ) {
    }

    #[Route(path: '/stats', name: 'app_stats', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $asked = $request->query->all()['show'] ?? null;
        $shown = Shown::asked(\is_string($asked) ? $asked : null);
        $tally = $this->goshuinchos->tally();

        $goshuin = $shown->showsGoshuin() ? $this->goshuins->coverage() : self::NO_ZONES;
        $goshuincho = $shown->showsGoshuincho() ? $this->goshuinchos->coverage() : self::NO_ZONES;
        $coverage = $this->coverage($goshuin, $goshuincho);

        return $this->render('App/Stats/index.html.twig', [
            'shown' => $shown,
            'tally' => $tally,
            'counted' => ($shown->showsGoshuin() ? $tally->goshuin : 0) + ($shown->showsGoshuincho() ? $tally->goshuincho : 0),
            'coverage' => $coverage,
            'zones' => [
                'prefectures' => $this->lit($coverage->prefectures, $shown),
                'municipalities' => $this->lit($coverage->municipalities, $shown),
            ],
            'spans' => $this->spans(
                $shown->showsGoshuin() ? $this->goshuins->spans() : self::NO_SPANS,
                $shown->showsGoshuincho() ? $this->goshuinchos->spans() : self::NO_SPANS,
            ),
            'geo' => [
                'prefectures' => $this->boundaries('geo/prefectures.topo.json'),
                'municipalities' => $this->boundaries('geo/municipalities.topo.json'),
            ],
            'attribution' => $this->tileAttribution.' · '.$this->boundaryAttribution,
        ]);
    }

    private function boundaries(string $file): string
    {
        $stamp = @filemtime($this->projectDir.'/public/'.$file);

        return $this->assets->getUrl($file).($stamp === false ? '' : '?v='.dechex($stamp));
    }

    /**
     * @param array{zones: array<string, int>, unlocated: int} $goshuin
     * @param array{zones: array<string, int>, unlocated: int} $goshuincho
     */
    private function coverage(array $goshuin, array $goshuincho): Coverage
    {
        return new Coverage(
            prefectures: $this->zones($this->grouped($goshuin['zones']), $this->grouped($goshuincho['zones'])),
            municipalities: $this->zones($goshuin['zones'], $goshuincho['zones']),
            unlocatedGoshuin: $goshuin['unlocated'],
            unlocatedGoshuincho: $goshuincho['unlocated'],
        );
    }

    /**
     * @param array<string, int> $municipalities
     *
     * @return array<string, int>
     */
    private function grouped(array $municipalities): array
    {
        $prefectures = [];

        foreach ($municipalities as $code => $held) {
            $key = substr((string) $code, 0, 2);
            $prefectures[$key] = ($prefectures[$key] ?? 0) + $held;
        }

        return $prefectures;
    }

    /**
     * @param array<string, int> $goshuin
     * @param array<string, int> $goshuincho
     *
     * @return list<Zone>
     */
    private function zones(array $goshuin, array $goshuincho): array
    {
        $codes = array_unique(array_map(
            static fn (int|string $code): string => (string) $code,
            [...array_keys($goshuin), ...array_keys($goshuincho)],
        ));

        sort($codes, SORT_STRING);

        return array_map(
            static fn (string $code): Zone => new Zone($code, $goshuin[$code] ?? 0, $goshuincho[$code] ?? 0),
            array_values($codes),
        );
    }

    /**
     * @param list<Zone> $zones
     *
     * @return list<array{code: string, held: string}>
     */
    private function lit(array $zones, Shown $shown): array
    {
        $lit = [];

        foreach ($zones as $zone) {
            $held = [];

            if ($shown->showsGoshuin() && $zone->goshuin > 0) {
                $held[] = $this->translator->trans('label.pill_goshuin', ['count' => $zone->goshuin]);
            }

            if ($shown->showsGoshuincho() && $zone->goshuincho > 0) {
                $held[] = $this->translator->trans('label.pill_goshuincho', ['count' => $zone->goshuincho]);
            }

            if ($held !== []) {
                $lit[] = ['code' => $zone->code, 'held' => implode(' · ', $held)];
            }
        }

        return $lit;
    }

    /**
     * @param array{years: array<int, int>, months: array<int, int>, weekdays: array<int, int>, undated: int} $goshuin
     * @param array{years: array<int, int>, months: array<int, int>, weekdays: array<int, int>, undated: int} $goshuincho
     *
     * @return array{years: list<Span>, months: list<Span>, weekdays: list<Span>, undatedGoshuin: int, undatedGoshuincho: int}
     */
    private function spans(array $goshuin, array $goshuincho): array
    {
        return [
            'years' => $this->series($this->chronological($goshuin['years'], $goshuincho['years']), $goshuin['years'], $goshuincho['years']),
            'months' => $this->series(range(1, 12), $goshuin['months'], $goshuincho['months']),
            'weekdays' => $this->series(range(1, 7), $goshuin['weekdays'], $goshuincho['weekdays']),
            'undatedGoshuin' => $goshuin['undated'],
            'undatedGoshuincho' => $goshuincho['undated'],
        ];
    }

    /**
     * @param array<int, int> $goshuin
     * @param array<int, int> $goshuincho
     *
     * @return list<int>
     */
    private function chronological(array $goshuin, array $goshuincho): array
    {
        $years = array_filter(
            [...array_keys($goshuin), ...array_keys($goshuincho)],
            static fn (int $year): bool => $year >= self::EARLIEST_YEAR && $year <= (int) date('Y') + 1,
        );

        return $years === [] ? [] : range(min($years), max($years));
    }

    /**
     * @param list<int>       $keys
     * @param array<int, int> $goshuin
     * @param array<int, int> $goshuincho
     *
     * @return list<Span>
     */
    private function series(array $keys, array $goshuin, array $goshuincho): array
    {
        return array_map(
            static fn (int $key): Span => new Span((string) $key, $goshuin[$key] ?? 0, $goshuincho[$key] ?? 0),
            $keys,
        );
    }
}
