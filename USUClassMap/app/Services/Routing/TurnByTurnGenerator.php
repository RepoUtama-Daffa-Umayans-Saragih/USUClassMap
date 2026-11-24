<?php

namespace App\Services\Routing;

class TurnByTurnGenerator
{
    public function __construct(
        protected GeometryGuardService $geometryGuard
    ) {}

    /**
     * Generate concise Indonesian turn-by-turn navigation instructions.
     *
     * @param  array<int, array<string, mixed>>  $mainEdges
     * @param  array<int, array{0: float, 1: float}>|null  $originAccessCoords
     * @param  array<int, array{0: float, 1: float}>|null  $destAccessCoords
     * @return array<int, array{instruction: string, distance_meters: int, maneuver: string, coordinate: array{0: float, 1: float}}>
     */
    public function generate(
        array $mainEdges,
        ?array $originAccessCoords,
        ?array $destAccessCoords,
        string $originLabel,
        string $destLabel,
        string $mode,
        array $originCoord,
        array $destCoord
    ): array {
        $steps = [];
        $verb = $mode === 'driving' ? 'berkendara' : 'berjalan kaki';

        $originAccessMeters = $this->polylineMeters($originAccessCoords);
        if ($originAccessMeters >= 3.0) {
            $steps[] = [
                'instruction' => "Keluar dari area {$originLabel} menuju jalur utama sejauh " . $this->formatMeters($originAccessMeters),
                'distance_meters' => (int) round($originAccessMeters),
                'maneuver' => 'depart',
                'coordinate' => $originCoord,
            ];
        } else {
            $steps[] = [
                'instruction' => "Mulai {$verb} dari {$originLabel}",
                'distance_meters' => 0,
                'maneuver' => 'depart',
                'coordinate' => $originCoord,
            ];
        }

        // Merge consecutive edges along the same road label unless a turn (>= 25 deg) or bridge transition occurs
        $groups = [];
        $prevBearing = null;

        foreach ($mainEdges as $edge) {
            $u = $edge['uCoord'];
            $v = $edge['vCoord'];
            $dist = (float) ($edge['distance_m'] ?? $this->geometryGuard->haversineMeters($u[1], $u[0], $v[1], $v[0]));
            $bearing = $this->geometryGuard->bearingDegrees($u, $v);
            $label = $this->humanRoadLabel($edge);
            $isBridge = !empty($edge['is_bridge']);

            $delta = 0.0;
            if ($prevBearing !== null) {
                $delta = fmod(($bearing - $prevBearing + 540.0), 360.0) - 180.0;
            }

            $lastIdx = count($groups) - 1;
            $canMerge = $lastIdx >= 0
                && $groups[$lastIdx]['label'] === $label
                && $groups[$lastIdx]['is_bridge'] === $isBridge
                && abs($delta) < 25.0;

            if ($canMerge) {
                $groups[$lastIdx]['distance_m'] += $dist;
                $groups[$lastIdx]['endCoord'] = $v;
                $groups[$lastIdx]['endBearing'] = $bearing;
            } else {
                $groups[] = [
                    'label' => $label,
                    'raw_name' => (string) ($edge['name'] ?? ''),
                    'road_class' => (string) ($edge['road_class'] ?? ''),
                    'is_bridge' => $isBridge,
                    'distance_m' => $dist,
                    'startCoord' => $u,
                    'endCoord' => $v,
                    'delta' => $lastIdx < 0 ? 0.0 : $delta,
                    'endBearing' => $bearing,
                ];
            }

            $prevBearing = $bearing;
        }

        foreach ($groups as $idx => $g) {
            $distRounded = max(1, (int) round($g['distance_m']));
            $distTxt = $this->formatMeters($g['distance_m']);

            if ($g['is_bridge']) {
                $steps[] = [
                    'instruction' => "Menyeberangi {$g['label']} sejauh {$distTxt}",
                    'distance_meters' => $distRounded,
                    'maneuver' => 'bridge',
                    'coordinate' => $g['startCoord'],
                ];
                continue;
            }

            if ($idx === 0) {
                $steps[] = [
                    'instruction' => "Ikuti {$g['label']} sejauh {$distTxt}",
                    'distance_meters' => $distRounded,
                    'maneuver' => 'straight',
                    'coordinate' => $g['startCoord'],
                ];
                continue;
            }

            [$maneuver, $phrase] = $this->classifyTurn((float) $g['delta']);
            $prep = $maneuver === 'straight' ? 'mengikuti' : 'ke';

            $steps[] = [
                'instruction' => "{$phrase} {$prep} {$g['label']} sejauh {$distTxt}",
                'distance_meters' => $distRounded,
                'maneuver' => $maneuver,
                'coordinate' => $g['startCoord'],
            ];
        }

        $destAccessMeters = $this->polylineMeters($destAccessCoords);
        if ($destAccessMeters >= 3.0) {
            $steps[] = [
                'instruction' => "Masuk area halaman/pintu masuk {$destLabel} sejauh " . $this->formatMeters($destAccessMeters),
                'distance_meters' => (int) round($destAccessMeters),
                'maneuver' => 'access',
                'coordinate' => $destAccessCoords[0] ?? $destCoord,
            ];
        }

        $steps[] = [
            'instruction' => "Tiba di tujuan: {$destLabel}",
            'distance_meters' => 0,
            'maneuver' => 'arrive',
            'coordinate' => $destCoord,
        ];

        return $this->cleanSteps($steps);
    }

    /**
     * Remove noisy sub-3m intermediate steps and collapse consecutive duplicate instructions.
     *
     * @param  array<int, array{instruction: string, distance_meters: int, maneuver: string, coordinate: array{0: float, 1: float}}>  $steps
     * @return array<int, array{instruction: string, distance_meters: int, maneuver: string, coordinate: array{0: float, 1: float}}>
     */
    protected function cleanSteps(array $steps): array
    {
        $filtered = [];

        foreach ($steps as $step) {
            $maneuver = (string) ($step['maneuver'] ?? 'straight');
            $dist = (int) ($step['distance_meters'] ?? 0);

            if (!in_array($maneuver, ['depart', 'arrive', 'start'], true) && $dist <= 2) {
                continue;
            }

            $lastIdx = count($filtered) - 1;
            if ($lastIdx >= 0 && ($filtered[$lastIdx]['instruction'] ?? '') === ($step['instruction'] ?? '')) {
                $filtered[$lastIdx]['distance_meters'] += $dist;
                continue;
            }

            $filtered[] = $step;
        }

        return array_values($filtered);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function classifyTurn(float $delta): array
    {
        if (abs($delta) <= 22.0) {
            return ['straight', 'Lurus'];
        }
        if ($delta > 22.0 && $delta <= 58.0) {
            return ['slight_right', 'Serong kanan'];
        }
        if ($delta > 58.0 && $delta <= 135.0) {
            return ['turn_right', 'Belok kanan'];
        }
        if ($delta < -22.0 && $delta >= -58.0) {
            return ['slight_left', 'Serong kiri'];
        }
        if ($delta < -58.0 && $delta >= -135.0) {
            return ['turn_left', 'Belok kiri'];
        }

        return ['uturn', 'Putar balik'];
    }

    protected function humanRoadLabel(array $edge): string
    {
        $name = trim((string) ($edge['name'] ?? ''));
        $class = strtolower((string) ($edge['road_class'] ?? ''));

        if (!empty($edge['is_bridge'])) {
            return $name !== '' ? "jembatan {$name}" : 'jembatan penyeberangan parit';
        }

        if ($name !== '') {
            return $name;
        }

        return match ($class) {
            'footway', 'pedestrian', 'corridor', 'campus_walkway' => 'jalur pejalan kaki',
            'steps' => 'tangga pejalan kaki',
            'path', 'track' => 'jalan setapak kampus',
            'service' => 'jalur akses gedung/parkiran',
            default => 'jalan lingkungan kampus',
        };
    }

    protected function polylineMeters(?array $coords): float
    {
        if (!$coords || count($coords) < 2) {
            return 0.0;
        }
        $sum = 0.0;
        $cnt = count($coords);
        for ($i = 0; $i < $cnt - 1; $i++) {
            $sum += $this->geometryGuard->haversineMeters(
                (float) $coords[$i][1],
                (float) $coords[$i][0],
                (float) $coords[$i + 1][1],
                (float) $coords[$i + 1][0]
            );
        }

        return $sum;
    }

    protected function formatMeters(float $meters): string
    {
        if ($meters >= 1000.0) {
            return number_format($meters / 1000.0, 2, ',', '.') . ' km';
        }

        return max(1, (int) round($meters)) . ' m';
    }
}
