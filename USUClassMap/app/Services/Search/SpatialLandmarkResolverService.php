<?php

namespace App\Services\Search;

use App\Models\Building;
use App\Models\Schedule;
use Illuminate\Support\Collection;

/**
 * Engine 3: SpatialLandmarkResolverService
 *
 * Solves ambiguous "I only remember where my class is relative to other buildings"
 * queries using real building coordinates, entrance/road-graph facing bearings,
 * multi-constraint directional geometry (front, side, back, between, near),
 * and optional course/day schedule fusion across all 77 USU buildings.
 */
class SpatialLandmarkResolverService
{
    /**
     * Static cache of campus road graph nodes for fast facing-bearing calculation.
     *
     * @var array<int, array{lat: float, lng: float}>|null
     */
    protected static ?array $graphNodes = null;

    public function __construct(
        protected QueryIntentParserService $intentParser
    ) {
    }

    /**
     * Resolve Top 3-5 candidate buildings matching spatial clues & optional academic context.
     *
     * @param string $query
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function resolve(string $query, array $options = []): array
    {
        $parsed = $options['parsed'] ?? $this->intentParser->parse($query, $options);
        $limit = max(3, min(10, (int) ($options['limit'] ?? 5)));
        $day = $options['day'] ?? $parsed['day'] ?? null;
        $courseTerms = $parsed['course_terms'] ?? [];

        $constraints = $parsed['spatial_constraints'] ?? [];
        $mentionedBuildings = $parsed['mentioned_buildings'] ?? [];

        // If user named building(s) without directional prepositions (e.g. "lupa nama gedung dekat FIB"),
        // synthesize a 'near' constraint automatically.
        if (empty($constraints) && !empty($mentionedBuildings)) {
            $constraints[] = [
                'relation'     => 'near',
                'relation_raw' => 'dekat',
                'raw_landmark' => $mentionedBuildings[0]['code'],
                'label'        => $mentionedBuildings[0]['name'],
                'is_ambiguous' => false,
                'buildings'    => [$mentionedBuildings[0]],
            ];
        }

        // Load all 77 buildings with rooms & room media for rich card info
        $buildings = Building::query()
            ->with(['rooms.media'])
            ->orderBy('id')
            ->get();

        if ($buildings->isEmpty()) {
            return [
                'status'           => 'success',
                'query'            => $query,
                'constraints'      => [],
                'ambiguity_notes'  => [],
                'advisory_note'    => 'Data gedung belum tersedia di database.',
                'count'            => 0,
                'candidates'       => [],
            ];
        }

        // Compute facing bearing for each building
        $roadNodes = $this->loadRoadGraphNodes();
        $buildingBearings = [];
        foreach ($buildings as $b) {
            $buildingBearings[$b->id] = $this->estimateFacingBearing($b, $roadNodes);
        }

        // Collect exact landmark IDs to exclude from candidate predictions
        $excludedIds = $this->collectExcludedBuildingIds($constraints);

        // Optional academic schedules matching course terms or day
        $schedulesByBuilding = $this->loadMatchingSchedulesByBuilding($courseTerms, $day);

        // If there are still no constraints (pure unknown query), return gracefully
        if (empty($constraints)) {
            return [
                'status'          => 'success',
                'query'           => $query,
                'constraints'     => [],
                'ambiguity_notes' => [],
                'advisory_note'   => 'Belum ada patokan gedung kampus yang terdeteksi dari deskripsi Anda. Coba sebutkan minimal satu gedung patokan seperti FIB, FMIPA, GKB, FASILKOM, atau Perpustakaan USU.',
                'count'           => 0,
                'candidates'      => [],
            ];
        }

        // Precompute anchor summary info & inter-landmark distances for corridor bonus
        $landmarkCentroids = [];
        $ambiguityNotes = [];
        foreach ($constraints as $idx => $c) {
            if (!empty($c['is_ambiguous'])) {
                $ambiguityNotes[] = "Patokan \"{$c['raw_landmark']}\" ditafsirkan sebagai {$c['label']}.";
            }
            $cBuildings = $c['buildings'] ?? [];
            if (!empty($cBuildings)) {
                $first = $cBuildings[0];
                $landmarkCentroids[] = [
                    'idx'   => $idx,
                    'label' => $c['label'] ?? ($first['name'] ?? $first['code']),
                    'lat'   => (float) $first['latitude'],
                    'lng'   => (float) $first['longitude'],
                ];
            }
        }

        // Score every candidate building not in $excludedIds
        $scored = [];
        foreach ($buildings as $cand) {
            if (in_array((int) $cand->id, $excludedIds, true)) {
                continue;
            }
            if (!$cand->latitude || !$cand->longitude) {
                continue;
            }

            $candLat = (float) $cand->latitude;
            $candLng = (float) $cand->longitude;
            $candBearings = [];
            $constraintScores = [];
            $distanceEntries = [];
            $reasonClauses = [];

            foreach ($constraints as $c) {
                $eval = $this->scoreCandidateAgainstConstraint(
                    $candLat,
                    $candLng,
                    $c,
                    $buildingBearings
                );
                $constraintScores[] = $eval['score'];
                $distanceEntries[] = $eval['distance_entry'];
                $reasonClauses[] = $eval['reason_clause'];
                $candBearings[] = $eval['bearing_deg'];
            }

            $avgConstraint = array_sum($constraintScores) / max(1, count($constraintScores));
            $minConstraint = min($constraintScores);

            // Balanced intersection score
            $spatialScore = (0.58 * $avgConstraint) + (0.42 * $minConstraint);

            // Multi-landmark corridor bonus (when 2+ landmarks exist, reward buildings sitting along corridor between them)
            if (count($landmarkCentroids) >= 2) {
                $p1 = $landmarkCentroids[0];
                $p2 = $landmarkCentroids[1];
                $dInter = $this->haversine($p1['lat'], $p1['lng'], $p2['lat'], $p2['lng']);
                $d1 = $this->haversine($candLat, $candLng, $p1['lat'], $p1['lng']);
                $d2 = $this->haversine($candLat, $candLng, $p2['lat'], $p2['lng']);

                if ($dInter > 40.0) {
                    $detourRatio = ($d1 + $d2) / $dInter;
                    if ($detourRatio <= 1.38) {
                        $spatialScore += 0.14 * (1.0 - max(0.0, ($detourRatio - 1.0) / 0.38));
                    }
                }
            }

            // Academic schedule boost
            $matchedSchedules = $schedulesByBuilding[$cand->id] ?? [];
            $scheduleBoost = !empty($matchedSchedules) ? 0.18 : 0.0;

            // Bonus for academic faculty / kuliah buildings when user explicitly mentioned "kelas"
            $academicBuildingBonus = 0.0;
            if (preg_match('/\b(kelas|kuliah|ruang|mkwu|mkwk)\b/i', $query)) {
                $cat = mb_strtolower((string) ($cand->category ?? ''), 'UTF-8');
                $fac = mb_strtolower((string) ($cand->faculty ?? ''), 'UTF-8');
                if (str_contains($cat, 'fakultas') || $cand->rooms->count() > 0 || $fac === 'fib' || $fac === 'fmipa' || $fac === 'ft') {
                    $academicBuildingBonus = 0.05;
                }
            }

            $totalRawScore = min(1.15, $spatialScore + $scheduleBoost + $academicBuildingBonus);

            $has360 = $cand->rooms->contains(function ($room): bool {
                if (!empty($room->photo_360_path)) {
                    return true;
                }
                if ($room->relationLoaded('media')) {
                    return $room->media->contains(function ($m): bool {
                        $type = strtolower((string) ($m->type ?? ''));
                        $path = strtolower((string) ($m->file_path ?? ''));
                        return in_array($type, ['360', 'panorama', 'virtual_tour', 'image_360'], true)
                            || str_contains($path, '360');
                    });
                }
                return false;
            });

            $scored[] = [
                'building'           => $cand,
                'facing_bearing'     => $buildingBearings[$cand->id] ?? 180.0,
                'raw_score'          => $totalRawScore,
                'distance_entries'   => $distanceEntries,
                'reason_clauses'     => $reasonClauses,
                'matched_schedules'  => array_slice($matchedSchedules, 0, 3),
                'rooms_count'        => $cand->rooms->count(),
                'has_360_tour'       => $has360,
            ];
        }

        usort($scored, function (array $a, array $b): int {
            $cmp = $b['raw_score'] <=> $a['raw_score'];
            if ($cmp !== 0) {
                return $cmp;
            }
            $da = $a['distance_entries'][0]['distance_meters'] ?? 99999;
            $db = $b['distance_entries'][0]['distance_meters'] ?? 99999;
            return $da <=> $db;
        });

        $topSlice = array_slice($scored, 0, $limit);
        $topRaw = !empty($topSlice) ? max(0.01, $topSlice[0]['raw_score']) : 1.0;

        $candidates = [];
        foreach ($topSlice as $rankIdx => $entry) {
            /** @var Building $b */
            $b = $entry['building'];

            // Calibrate realistic 45..95% confidence relative to geometric alignment & rank
            $absPct = 42.0 + ($entry['raw_score'] * 48.0);
            $relFactor = $entry['raw_score'] / $topRaw;
            $confidence = (int) round(max(40, min(95, ($absPct * 0.65) + ($relFactor * 92.0 * 0.35) - ($rankIdx * 2.5))));

            $facingCompass = $this->bearingToCompassId($entry['facing_bearing']);
            $reasonSentence = implode(' dan ', array_filter($entry['reason_clauses'])) . '.';
            if (!empty($entry['matched_schedules'])) {
                $firstSched = $entry['matched_schedules'][0];
                $reasonSentence .= " Terdapat kelas {$firstSched['course_name']} ({$firstSched['day']} {$firstSched['time']}) di ruang {$firstSched['room_name']}.";
            } elseif ($entry['rooms_count'] > 0) {
                $reasonSentence .= " Memiliki {$entry['rooms_count']} ruang kuliah terdaftar (hadap {$facingCompass}).";
            } else {
                $reasonSentence .= " Arah hadap gedung menghadap {$facingCompass}.";
            }

            $candidates[] = [
                'building_id'        => (int) $b->id,
                'code'               => (string) $b->code,
                'name'               => (string) $b->name,
                'faculty'            => (string) ($b->faculty ?: 'Universitas Sumatera Utara'),
                'category'           => (string) ($b->category ?: 'Gedung Kampus'),
                'latitude'           => (float) $b->latitude,
                'longitude'          => (float) $b->longitude,
                'confidence_percent' => $confidence,
                'spatial_reason'     => trim($reasonSentence),
                'facing_compass'     => $facingCompass,
                'distances'          => $entry['distance_entries'],
                'rooms_count'        => $entry['rooms_count'],
                'has_360_tour'       => $entry['has_360_tour'],
                'matched_schedules'  => $entry['matched_schedules'],
                'actions'            => [
                    'focus_url' => url('/map?focus_building=' . $b->id),
                    'route_url' => url('/map?route_to=building:' . $b->id),
                    'route_key' => 'building:' . $b->id,
                ],
            ];
        }

        $advisory = null;
        if (!empty($ambiguityNotes)) {
            $advisory = implode(' ', $ambiguityNotes);
        }

        return [
            'status'          => 'success',
            'query'           => $query,
            'constraints'     => array_map(fn (array $c): array => [
                'relation'     => $c['relation'],
                'raw_landmark' => $c['raw_landmark'] ?? ($c['raw_phrase'] ?? ''),
                'label'        => $c['label'] ?? ($c['raw_phrase'] ?? ''),
                'building_ids' => array_map(fn ($b) => (int) $b['id'], $c['buildings'] ?? []),
            ], $constraints),
            'ambiguity_notes' => $ambiguityNotes,
            'advisory_note'   => $advisory,
            'count'           => count($candidates),
            'candidates'      => $candidates,
        ];
    }

    /**
     * Collect all exact landmark building IDs queried so we don't guess the landmark itself.
     *
     * @param array<int, array<string, mixed>> $constraints
     * @return array<int, int>
     */
    protected function collectExcludedBuildingIds(array $constraints): array
    {
        $ids = [];
        foreach ($constraints as $c) {
            foreach ($c['buildings'] ?? [] as $b) {
                $ids[] = (int) $b['id'];
            }
            foreach ($c['buildings_a'] ?? [] as $b) {
                $ids[] = (int) $b['id'];
            }
            foreach ($c['buildings_b'] ?? [] as $b) {
                $ids[] = (int) $b['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Score candidate (candLat, candLng) against one parsed spatial constraint.
     *
     * @param array<string, mixed> $constraint
     * @param array<int, float> $buildingBearings
     * @return array{score: float, distance_entry: array<string, mixed>, reason_clause: string, bearing_deg: float}
     */
    protected function scoreCandidateAgainstConstraint(
        float $candLat,
        float $candLng,
        array $constraint,
        array $buildingBearings
    ): array {
        $rel = $constraint['relation'];

        if ($rel === 'between' && !empty($constraint['buildings_a']) && !empty($constraint['buildings_b'])) {
            $best = null;
            foreach ($constraint['buildings_a'] as $a) {
                foreach ($constraint['buildings_b'] as $b) {
                    $midLat = ((float) $a['latitude'] + (float) $b['latitude']) / 2.0;
                    $midLng = ((float) $a['longitude'] + (float) $b['longitude']) / 2.0;
                    $dInter = max(40.0, $this->haversine((float) $a['latitude'], (float) $a['longitude'], (float) $b['latitude'], (float) $b['longitude']));
                    $dA = $this->haversine($candLat, $candLng, (float) $a['latitude'], (float) $a['longitude']);
                    $dB = $this->haversine($candLat, $candLng, (float) $b['latitude'], (float) $b['longitude']);
                    $dMid = $this->haversine($candLat, $candLng, $midLat, $midLng);
                    $ratio = ($dA + $dB) / $dInter;

                    $score = (0.60 * exp(-max(0.0, $dMid - 25.0) / max(150.0, $dInter * 0.65)))
                        + (0.40 * exp(-max(0.0, $ratio - 1.05) * 1.6));

                    if ($best === null || $score > $best['score']) {
                        $best = [
                            'score'          => $score,
                            'distance_entry' => [
                                'landmark'        => ($a['code'] . ' ↔ ' . $b['code']),
                                'distance_meters' => (int) round(min($dA, $dB)),
                                'direction'       => 'di koridor antara kedua gedung',
                            ],
                            'reason_clause'  => sprintf(
                                'Berada di antara %s (~%d m) dan %s (~%d m)',
                                $a['code'],
                                (int) round($dA),
                                $b['code'],
                                (int) round($dB)
                            ),
                            'bearing_deg'    => $this->bearing($midLat, $midLng, $candLat, $candLng),
                        ];
                    }
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        $best = null;
        foreach ($constraint['buildings'] ?? [] as $lm) {
            $lmId = (int) $lm['id'];
            $lmLat = (float) $lm['latitude'];
            $lmLng = (float) $lm['longitude'];
            $facing = $buildingBearings[$lmId] ?? 180.0;

            $dist = $this->haversine($lmLat, $lmLng, $candLat, $candLng);
            $bearingFromLm = $this->bearing($lmLat, $lmLng, $candLat, $candLng);
            $angDiff = $this->angularDiff($facing, $bearingFromLm);

            if ($rel === 'front') {
                $dirScore = $angDiff <= 45.0
                    ? 1.0
                    : ($angDiff <= 95.0 ? 1.0 - (($angDiff - 45.0) / 50.0) * 0.45 : max(0.20, 0.55 - (($angDiff - 95.0) / 85.0) * 0.35));
                $distScore = exp(-max(0.0, $dist - 55.0) / 260.0);
                $score = (0.35 * $dirScore) + (0.65 * $distScore);
            } elseif ($rel === 'side') {
                $sideDev = abs($angDiff - 90.0);
                $dirScore = $sideDev <= 35.0
                    ? 1.0
                    : max(0.32, 1.0 - (($sideDev - 35.0) / 55.0) * 0.68);
                $distScore = exp(-max(0.0, $dist - 50.0) / 250.0);
                $score = (0.32 * $dirScore) + (0.68 * $distScore);
            } elseif ($rel === 'back') {
                $backDev = abs(180.0 - $angDiff);
                $dirScore = $backDev <= 50.0
                    ? 1.0
                    : max(0.22, 1.0 - (($backDev - 50.0) / 130.0) * 0.78);
                $distScore = exp(-max(0.0, $dist - 55.0) / 260.0);
                $score = (0.35 * $dirScore) + (0.65 * $distScore);
            } else {
                // 'near'
                $score = exp(-max(0.0, $dist - 35.0) / 250.0);
            }

            if ($best === null || $score > $best['score']) {
                $compassId = $this->bearingToCompassId($bearingFromLm);
                $distRounded = (int) round($dist);
                $relHuman = match ($rel) {
                    'front' => 'area sekitar/depan',
                    'side'  => 'arah samping/sekitar',
                    'back'  => 'area belakang/sekitar',
                    default => 'dekat',
                };

                $best = [
                    'score'          => $score,
                    'distance_entry' => [
                        'landmark'        => $lm['name'] . ' (' . $lm['code'] . ')',
                        'distance_meters' => $distRounded,
                        'direction'       => "arah {$compassId} ({$relHuman})",
                    ],
                    'reason_clause'  => "Berjarak ~{$distRounded} m di sebelah {$compassId} dari {$lm['code']} ({$lm['name']})",
                    'bearing_deg'    => $bearingFromLm,
                ];
            }
        }

        return $best ?? [
            'score'          => 0.0,
            'distance_entry' => ['landmark' => '-', 'distance_meters' => 9999, 'direction' => '-'],
            'reason_clause'  => '',
            'bearing_deg'    => 0.0,
        ];
    }

    /**
     * Load graph nodes from storage/app/routing/usu_campus_graph.json (cached).
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    protected function loadRoadGraphNodes(): array
    {
        if (self::$graphNodes !== null) {
            return self::$graphNodes;
        }

        $path = storage_path('app/routing/usu_campus_graph.json');
        if (!file_exists($path)) {
            self::$graphNodes = [];
            return [];
        }

        $json = @file_get_contents($path);
        $decoded = $json ? json_decode($json, true) : null;
        $rawNodes = is_array($decoded) ? ($decoded['nodes'] ?? []) : [];

        $nodes = [];
        foreach ($rawNodes as $n) {
            if (isset($n['lat'], $n['lng'])) {
                $nodes[] = ['lat' => (float) $n['lat'], 'lng' => (float) $n['lng']];
            }
        }

        self::$graphNodes = $nodes;

        return $nodes;
    }

    /**
     * Estimate facing azimuth (degrees clockwise from North) using entrance coordinates
     * or nearest campus road node.
     *
     * @param array<int, array{lat: float, lng: float}> $roadNodes
     */
    protected function estimateFacingBearing(Building $b, array $roadNodes): float
    {
        $cLat = (float) $b->latitude;
        $cLng = (float) $b->longitude;

        if ($b->entrance_lat !== null && $b->entrance_lng !== null) {
            $eLat = (float) $b->entrance_lat;
            $eLng = (float) $b->entrance_lng;
            if ($this->haversine($cLat, $cLng, $eLat, $eLng) >= 1.0) {
                return $this->bearing($cLat, $cLng, $eLat, $eLng);
            }
        }

        if (!empty($roadNodes)) {
            $nearest = null;
            $bestD = INF;
            foreach ($roadNodes as $node) {
                // Quick Manhattan prefilter before Haversine
                if (abs($node['lat'] - $cLat) > 0.004 || abs($node['lng'] - $cLng) > 0.004) {
                    continue;
                }
                $d = $this->haversine($cLat, $cLng, $node['lat'], $node['lng']);
                if ($d < $bestD) {
                    $bestD = $d;
                    $nearest = $node;
                }
            }
            if ($nearest !== null && $bestD >= 1.0) {
                return $this->bearing($cLat, $cLng, $nearest['lat'], $nearest['lng']);
            }
        }

        return 180.0;
    }

    /**
     * Optional academic schedule fusion keyed by building_id.
     *
     * @param array<int, string> $courseTerms
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function loadMatchingSchedulesByBuilding(array $courseTerms, ?string $day): array
    {
        if (empty($courseTerms) && $day === null) {
            return [];
        }

        $q = Schedule::query()->with(['course', 'room.building']);
        if ($day !== null) {
            $q->where('day', $day);
        }

        if (!empty($courseTerms)) {
            $q->where(function ($sub) use ($courseTerms): void {
                foreach ($courseTerms as $term) {
                    $like = "%{$term}%";
                    $sub->orWhereHas('course', fn ($cq) => $cq->where('name', 'LIKE', $like)->orWhere('code', 'LIKE', $like))
                        ->orWhereHas('lecturer', fn ($lq) => $lq->where('name', 'LIKE', $like));
                }
            });
        }

        $schedules = $q->limit(80)->get();
        $grouped = [];

        foreach ($schedules as $s) {
            $bId = $s->room->building->id ?? null;
            if (!$bId) {
                continue;
            }
            $grouped[$bId][] = [
                'schedule_id' => (int) $s->id,
                'course_name' => (string) ($s->course->name ?? '-'),
                'course_code' => (string) ($s->course->code ?? '-'),
                'room_name'   => (string) ($s->room->name ?? '-'),
                'day'         => (string) $s->day,
                'time'        => substr((string) $s->time_start, 0, 5) . ' - ' . substr((string) $s->time_end, 0, 5),
            ];
        }

        return $grouped;
    }

    protected function bearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dLambda = deg2rad($lng2 - $lng1);

        $y = sin($dLambda) * cos($phi2);
        $x = (cos($phi1) * sin($phi2)) - (sin($phi1) * cos($phi2) * cos($dLambda));

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    protected function angularDiff(float $degA, float $degB): float
    {
        $diff = abs(fmod($degA - $degB + 360.0, 360.0));
        return $diff > 180.0 ? (360.0 - $diff) : $diff;
    }

    protected function bearingToCompassId(float $bearing): string
    {
        $dirs = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
        $idx = (int) round($bearing / 45.0) % 8;
        return $dirs[$idx];
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
