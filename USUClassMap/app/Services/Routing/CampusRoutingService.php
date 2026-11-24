<?php

namespace App\Services\Routing;

use App\Models\Building;
use App\Models\CampusRoutingOverride;
use App\Models\PhotocopyPlace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CampusRoutingService
{
    private const CACHE_KEY_COMPILED = 'usu_campus_compiled_graph_v1';
    private const WALKING_SPEED_M_PER_MIN = 75.0;  // 4.5 km/h
    private const DRIVING_SPEED_M_PER_MIN = 333.33; // 20.0 km/h

    public const ENGINE_WALKING = 'Graf Pejalan Kaki USU';
    public const ENGINE_DRIVING = 'Graf Jalan Kendaraan USU';
    public const ENGINE_HYBRID = 'Rute Jalan Medan + Graf Kampus USU';

    protected const NON_DRIVABLE_ROAD_CLASSES = [
        'footway',
        'steps',
        'corridor',
        'path',
        'pedestrian',
        'campus_walkway',
        'bridge_foot',
    ];

    protected const PEDESTRIAN_PRIORITY_CLASSES = [
        'footway',
        'steps',
        'corridor',
        'path',
        'pedestrian',
        'campus_walkway',
        'bridge_foot',
    ];

    /** @var array<string, mixed>|null */
    protected static ?array $memoryCompiledGraph = null;

    public function __construct(
        protected GeometryGuardService $geometryGuard,
        protected TurnByTurnGenerator $turnByTurn,
        protected CampusGraphBuilderService $graphBuilder
    ) {}

    public function invalidateCache(): void
    {
        self::$memoryCompiledGraph = null;
        Cache::forget(self::CACHE_KEY_COMPILED);
    }

    /**
     * Solve campus route with A*, anti-obstacle snapping, last-mile connectors, and Mapbox hybrid fallback.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>|null  $customBaseGraph  Optional injected graph (for deterministic testing)
     * @return array<string, mixed>
     */
    public function computeRoute(array $params, ?array $customBaseGraph = null): array
    {
        $mode = ($params['mode'] ?? 'walking') === 'driving' ? 'driving' : 'walking';
        $graph = $customBaseGraph !== null
            ? $this->mergeOverridesIntoGraph($customBaseGraph)
            : $this->getCompiledGraph();

        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];
        $obstacles = $graph['obstacles'] ?? ['buildings' => [], 'barriers' => []];
        $entranceOverrides = $graph['entrance_overrides'] ?? [];

        if (empty($nodes) || empty($edges)) {
            throw new \RuntimeException('Graf jaringan jalan Kampus USU belum tersedia.');
        }

        // 1. Resolve Origin & Destination Coordinates + Metadata + Safe Entrance Points
        $originRaw = $this->resolveEndpoint(
            $params['origin_type'] ?? 'point',
            $params['origin_id'] ?? null,
            isset($params['origin_lat']) ? (float) $params['origin_lat'] : null,
            isset($params['origin_lng']) ? (float) $params['origin_lng'] : null,
            $params['origin_label'] ?? null,
            'Titik Asal',
            $entranceOverrides,
            $obstacles,
            $nodes
        );

        $destRaw = $this->resolveEndpoint(
            $params['dest_type'] ?? 'point',
            $params['dest_id'] ?? null,
            isset($params['dest_lat']) ? (float) $params['dest_lat'] : null,
            isset($params['dest_lng']) ? (float) $params['dest_lng'] : null,
            $params['dest_label'] ?? null,
            'Tujuan Kampus',
            $entranceOverrides,
            $obstacles,
            $nodes
        );

        $originInCampus = $this->isPointNearCampusGraph($originRaw['entrance'], $nodes);
        $destInCampus = $this->isPointNearCampusGraph($destRaw['entrance'], $nodes);

        // 2. Hybrid Fallback when Origin and/or Destination lies outside the core USU graph
        if (!$originInCampus || !$destInCampus) {
            return $this->solveHybridExternalRoute(
                $originRaw,
                $destRaw,
                $originInCampus,
                $destInCampus,
                $mode,
                $graph
            );
        }

        return $this->solveInternalCampusRoute($originRaw, $destRaw, $mode, $graph);
    }

    /**
     * Solve strictly inside USU campus A* graph with obstacle-free snapping & last-mile detours.
     *
     * @param  array<string, mixed>  $originInfo
     * @param  array<string, mixed>  $destInfo
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    protected function solveInternalCampusRoute(
        array $originInfo,
        array $destInfo,
        string $mode,
        array $graph,
        ?string $engineSource = null
    ): array {
        $engineSource = $engineSource ?: ($mode === 'driving' ? self::ENGINE_DRIVING : self::ENGINE_WALKING);
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $obstacles = $graph['obstacles'];

        $oEntrance = $originInfo['entrance']; // [lng, lat]
        $dEntrance = $destInfo['entrance'];   // [lng, lat]

        $allowedBuildingIds = array_values(array_filter([
            $originInfo['own_building_id'] ?? null,
            $destInfo['own_building_id'] ?? null,
        ]));

        // Honest arrival status when origin & destination are genuinely within 20 meters
        $rawDist = $this->geometryGuard->haversineMeters(
            (float) $originInfo['raw_lat'],
            (float) $originInfo['raw_lng'],
            (float) $destInfo['raw_lat'],
            (float) $destInfo['raw_lng']
        );
        $directDist = $this->geometryGuard->haversineMeters(
            $oEntrance[1],
            $oEntrance[0],
            $dEntrance[1],
            $dEntrance[0]
        );
        $nearDist = min($rawDist, $directDist);

        if ($nearDist < 20.0) {
            return $this->formatAlreadyAtDestinationResponse(
                $mode,
                $engineSource,
                $originInfo,
                $destInfo,
                $nearDist
            );
        }

        // Build base adjacency + projection-aware edge snaps with temporary virtual edge splits
        $prepared = $this->prepareWorkingGraphWithProjectedSnaps(
            $edges,
            $nodes,
            $mode,
            $oEntrance,
            $dEntrance,
            $obstacles,
            $originInfo['own_building_id'] ? [$originInfo['own_building_id']] : [],
            $destInfo['own_building_id'] ? [$destInfo['own_building_id']] : []
        );

        $workingNodes = $prepared['nodes'];
        $adjacency = $prepared['adjacency'];
        $originSnaps = $prepared['origin_snaps'];
        $destSnaps = $prepared['dest_snaps'];

        $connectedModeNodes = [];
        foreach ($adjacency as $nid => $neighbors) {
            if (!empty($neighbors) && isset($workingNodes[$nid])) {
                $connectedModeNodes[$nid] = $workingNodes[$nid];
            }
        }

        if (empty($connectedModeNodes)) {
            throw new \RuntimeException("Tidak ada jaringan jalan yang tersedia untuk mode {$mode}.");
        }

        if (empty($originSnaps)) {
            $originSnaps = $this->findLegalSnapCandidates(
                $oEntrance,
                $connectedModeNodes,
                $obstacles,
                $originInfo['own_building_id'] ? [$originInfo['own_building_id']] : []
            );
        }
        if (empty($destSnaps)) {
            $destSnaps = $this->findLegalSnapCandidates(
                $dEntrance,
                $connectedModeNodes,
                $obstacles,
                $destInfo['own_building_id'] ? [$destInfo['own_building_id']] : []
            );
        }

        // Last-resort node fallback only if still empty
        if (empty($originSnaps)) {
            $fallback = $this->geometryGuard->findNearestNodes($oEntrance, $connectedModeNodes, 3, 600.0);
            foreach ($fallback as $n) {
                $originSnaps[$n['id']] = [
                    'node_id' => $n['id'],
                    'coords' => [$oEntrance, [$n['lng'], $n['lat']]],
                    'distance_m' => $n['distance_m'],
                ];
            }
        }
        if (empty($destSnaps)) {
            $fallback = $this->geometryGuard->findNearestNodes($dEntrance, $connectedModeNodes, 3, 600.0);
            foreach ($fallback as $n) {
                $destSnaps[$n['id']] = [
                    'node_id' => $n['id'],
                    'coords' => [[$n['lng'], $n['lat']], $dEntrance],
                    'distance_m' => $n['distance_m'],
                ];
            }
        }

        $blacklistedEdgeIds = [];
        $bestSolution = null;

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $solution = $this->runMultiTerminalAStar(
                $originSnaps,
                $destSnaps,
                $workingNodes,
                $adjacency,
                $dEntrance,
                $blacklistedEdgeIds
            );

            if ($solution === null) {
                break;
            }

            $oSnapInfo = $originSnaps[$solution['start_node']];
            $dSnapInfo = $destSnaps[$solution['end_node']];

            $mainEdges = $solution['edges'];
            $accessPolylines = [];
            if ($oSnapInfo['distance_m'] >= 1.5) {
                $accessPolylines[] = $oSnapInfo['coords'];
            }
            if ($dSnapInfo['distance_m'] >= 1.5) {
                $accessPolylines[] = $dSnapInfo['coords'];
            }

            $audit = $this->geometryGuard->auditRouteGeometry(
                $mainEdges,
                $accessPolylines,
                $obstacles,
                $allowedBuildingIds
            );

            $bestSolution = [
                'edges' => $mainEdges,
                'origin_snap' => $oSnapInfo,
                'dest_snap' => $dSnapInfo,
                'audit' => $audit,
            ];

            if ($audit['verified'] || empty($audit['violating_edge_ids'])) {
                break;
            }

            foreach ($audit['violating_edge_ids'] as $badId) {
                $blacklistedEdgeIds[$badId] = true;
            }
        }

        if ($bestSolution === null) {
            throw new \RuntimeException('Tidak ditemukan jalur bebas hambatan yang menghubungkan titik asal dan tujuan.');
        }

        $mainEdges = $bestSolution['edges'];
        $oSnapInfo = $bestSolution['origin_snap'];
        $dSnapInfo = $bestSolution['dest_snap'];
        $audit = $bestSolution['audit'];

        $mainCoords = [];
        if (!empty($mainEdges)) {
            $mainCoords[] = $mainEdges[0]['uCoord'];
            foreach ($mainEdges as $e) {
                $mainCoords[] = $e['vCoord'];
            }
        } else {
            $onlyNode = $workingNodes[$oSnapInfo['node_id']];
            $mainCoords = [[$onlyNode['lng'], $onlyNode['lat']], [$onlyNode['lng'], $onlyNode['lat']]];
        }

        $accessFeatures = [];
        $originAccessCoords = null;
        $destAccessCoords = null;
        $totalDist = $this->geometryGuard->polylineLengthMeters($mainCoords);

        if ($oSnapInfo['distance_m'] >= 1.5) {
            $originAccessCoords = $oSnapInfo['coords'];
            $totalDist += $this->geometryGuard->polylineLengthMeters($originAccessCoords);
            $accessFeatures[] = [
                'type' => 'Feature',
                'properties' => ['role' => 'origin_access', 'label' => 'Akses keluar area asal'],
                'geometry' => ['type' => 'LineString', 'coordinates' => $originAccessCoords],
            ];
        }
        if ($dSnapInfo['distance_m'] >= 1.5) {
            $destAccessCoords = $dSnapInfo['coords'];
            $totalDist += $this->geometryGuard->polylineLengthMeters($destAccessCoords);
            $accessFeatures[] = [
                'type' => 'Feature',
                'properties' => ['role' => 'destination_access', 'label' => 'Akses halaman / pintu masuk'],
                'geometry' => ['type' => 'LineString', 'coordinates' => $destAccessCoords],
            ];
        }

        $steps = $this->turnByTurn->generate(
            $mainEdges,
            $originAccessCoords,
            $destAccessCoords,
            $originInfo['label'],
            $destInfo['label'],
            $mode,
            $oEntrance,
            $dEntrance
        );

        return $this->formatResponse(
            $mode,
            $engineSource,
            $originInfo,
            $destInfo,
            $totalDist,
            $mainCoords,
            $accessFeatures,
            $steps,
            $audit
        );
    }

    /**
     * Hybrid external fallback: combine Mapbox Directions API for off-campus leg with internal USU graph.
     *
     * @param  array<string, mixed>  $originInfo
     * @param  array<string, mixed>  $destInfo
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    protected function solveHybridExternalRoute(
        array $originInfo,
        array $destInfo,
        bool $originInCampus,
        bool $destInCampus,
        string $mode,
        array $graph
    ): array {
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $adjacency = $this->buildModeAdjacency($edges, $nodes, $mode);
        $connectedNodes = [];
        foreach ($adjacency as $nid => $neighbors) {
            if (!empty($neighbors) && isset($nodes[$nid])) {
                $connectedNodes[$nid] = $nodes[$nid];
            }
        }

        // Case 1: Both off-campus -> single external Directions request
        if (!$originInCampus && !$destInCampus) {
            $ext = $this->fetchExternalRoadDirections($originInfo['entrance'], $destInfo['entrance'], $mode);
            $steps = $this->mergeMapboxSteps($ext['steps'], $originInfo['label'], $destInfo['label'], true, true);
            $audit = ['building_intersections' => 0, 'barrier_intersections' => 0, 'violating_edge_ids' => [], 'verified' => true];

            return $this->formatResponse(
                $mode,
                self::ENGINE_HYBRID,
                $originInfo,
                $destInfo,
                $this->geometryGuard->polylineLengthMeters($ext['coords']),
                $ext['coords'],
                [],
                $steps,
                $audit
            );
        }

        // Case 2: Origin outside campus, Destination inside campus
        if (!$originInCampus && $destInCampus) {
            $gatewayNode = $this->findClosestGatewayNode($originInfo['entrance'], $connectedNodes);
            $gwCoord = [$gatewayNode['lng'], $gatewayNode['lat']];
            $ext = $this->fetchExternalRoadDirections($originInfo['entrance'], $gwCoord, $mode);

            $gatewayEndpoint = [
                'type' => 'point',
                'id' => null,
                'lat' => $gatewayNode['lat'],
                'lng' => $gatewayNode['lng'],
                'raw_lat' => $gatewayNode['lat'],
                'raw_lng' => $gatewayNode['lng'],
                'entrance' => $gwCoord,
                'own_building_id' => null,
                'is_manual_entrance' => false,
                'label' => 'Gerbang Masuk Kampus USU',
            ];

            $campusLeg = $this->solveInternalCampusRoute(
                $gatewayEndpoint,
                $destInfo,
                $mode,
                $graph,
                self::ENGINE_HYBRID
            );

            $campusCoords = $campusLeg['main_route_geojson']['geometry']['coordinates'] ?? [$gwCoord, $destInfo['entrance']];
            $combinedMain = array_merge($ext['coords'], array_slice($campusCoords, 1));
            $accessFeatures = $campusLeg['access_segments_geojson']['features'] ?? [];
            $combinedDist = $this->geometryGuard->polylineLengthMeters($combinedMain)
                + $this->sumAccessFeaturesMeters($accessFeatures);

            $campusSteps = ($campusLeg['status'] ?? '') === 'already_at_destination'
                ? [[
                    'instruction' => "Tiba di tujuan: {$destInfo['label']}",
                    'distance_meters' => 0,
                    'maneuver' => 'arrive',
                    'coordinate' => $destInfo['entrance'],
                ]]
                : array_slice($campusLeg['steps'], 1);

            $steps = array_merge(
                $this->mergeMapboxSteps($ext['steps'], $originInfo['label'], 'Gerbang Masuk Kampus USU', true, false),
                $campusSteps
            );

            return $this->formatResponse(
                $mode,
                self::ENGINE_HYBRID,
                $originInfo,
                $destInfo,
                $combinedDist,
                $combinedMain,
                $accessFeatures,
                $steps,
                $campusLeg['geometry_audit']
            );
        }

        // Case 3: Origin inside campus, Destination outside campus (e.g., off-campus fotocopy #24/#25/#30)
        $gatewayNode = $this->findClosestGatewayNode($destInfo['entrance'], $connectedNodes);
        $gwCoord = [$gatewayNode['lng'], $gatewayNode['lat']];
        $gatewayEndpoint = [
            'type' => 'point',
            'id' => null,
            'lat' => $gatewayNode['lat'],
            'lng' => $gatewayNode['lng'],
            'raw_lat' => $gatewayNode['lat'],
            'raw_lng' => $gatewayNode['lng'],
            'entrance' => $gwCoord,
            'own_building_id' => null,
            'is_manual_entrance' => false,
            'label' => 'Gerbang Kampus USU',
        ];

        $campusLeg = $this->solveInternalCampusRoute(
            $originInfo,
            $gatewayEndpoint,
            $mode,
            $graph,
            self::ENGINE_HYBRID
        );
        $ext = $this->fetchExternalRoadDirections($gwCoord, $destInfo['entrance'], $mode);

        $campusCoords = $campusLeg['main_route_geojson']['geometry']['coordinates'] ?? [$originInfo['entrance'], $gwCoord];
        $combinedMain = array_merge($campusCoords, array_slice($ext['coords'], 1));
        $accessFeatures = $campusLeg['access_segments_geojson']['features'] ?? [];
        $combinedDist = $this->geometryGuard->polylineLengthMeters($combinedMain)
            + $this->sumAccessFeaturesMeters($accessFeatures);

        $campusStepsNoArrive = ($campusLeg['status'] ?? '') === 'already_at_destination'
            ? [[
                'instruction' => "Mulai dari {$originInfo['label']}",
                'distance_meters' => 0,
                'maneuver' => 'depart',
                'coordinate' => $originInfo['entrance'],
            ]]
            : array_slice($campusLeg['steps'], 0, max(1, count($campusLeg['steps']) - 1));

        $steps = array_merge(
            $campusStepsNoArrive,
            $this->mergeMapboxSteps($ext['steps'], 'Gerbang Kampus USU', $destInfo['label'], false, true)
        );

        return $this->formatResponse(
            $mode,
            self::ENGINE_HYBRID,
            $originInfo,
            $destInfo,
            $combinedDist,
            $combinedMain,
            $accessFeatures,
            $steps,
            $campusLeg['geometry_audit']
        );
    }

    /**
     * Fetch genuine road-following directions outside campus: Mapbox Directions primary + OSRM fallback.
     * Never returns a synthetic straight line when both providers fail.
     *
     * @param  array{0: float, 1: float}  $from  [lng, lat]
     * @param  array{0: float, 1: float}  $to    [lng, lat]
     * @return array{distance_m: float, coords: array, steps: array, provider: string}
     */
    protected function fetchExternalRoadDirections(array $from, array $to, string $mode): array
    {
        $token = trim((string) config('services.mapbox.token', env('VITE_MAPBOX_ACCESS_TOKEN', '')));
        $mapboxProfile = $mode === 'driving' ? 'driving' : 'walking';

        // 1. Primary: Mapbox Directions API
        if ($token !== '') {
            $url = "https://api.mapbox.com/directions/v5/mapbox/{$mapboxProfile}/{$from[0]},{$from[1]};{$to[0]},{$to[1]}";
            try {
                $resp = Http::timeout(7)->get($url, [
                    'geometries' => 'geojson',
                    'overview' => 'full',
                    'steps' => 'true',
                    'language' => 'id',
                    'access_token' => $token,
                ]);

                $coords = $resp->json('routes.0.geometry.coordinates');
                if ($resp->successful() && is_array($coords) && count($coords) >= 2) {
                    $dist = $this->geometryGuard->polylineLengthMeters($coords);
                    if ($dist > 0.0) {
                        $rawSteps = $resp->json('routes.0.legs.0.steps') ?? [];
                        return [
                            'distance_m' => $dist,
                            'coords' => $coords,
                            'steps' => is_array($rawSteps) ? $rawSteps : [],
                            'provider' => 'mapbox',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CampusRoutingService: Mapbox Directions API gagal: ' . $e->getMessage());
            }
        }

        // 2. Fallback: OSRM Public API
        $osrmProfile = $mode === 'driving' ? 'driving' : 'foot';
        $osrmUrl = "https://router.project-osrm.org/route/v1/{$osrmProfile}/{$from[0]},{$from[1]};{$to[0]},{$to[1]}";
        try {
            $resp = Http::timeout(6)->get($osrmUrl, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'true',
            ]);

            $coords = $resp->json('routes.0.geometry.coordinates');
            if ($resp->successful() && is_array($coords) && count($coords) >= 2) {
                $dist = $this->geometryGuard->polylineLengthMeters($coords);
                if ($dist > 0.0) {
                    $rawSteps = $resp->json('routes.0.legs.0.steps') ?? [];
                    return [
                        'distance_m' => $dist,
                        'coords' => $coords,
                        'steps' => $this->normalizeOsrmSteps(is_array($rawSteps) ? $rawSteps : []),
                        'provider' => 'osrm',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CampusRoutingService: OSRM fallback gagal: ' . $e->getMessage());
        }

        throw new ExternalRoutingUnavailableException(
            'Layanan rute jalan raya luar kampus (Mapbox & OSRM) sedang tidak dapat diakses saat ini. Silakan pilih Gedung Asal atau klik titik di area Kampus USU.'
        );
    }

    /**
     * Convert OSRM turn steps into the normalized Mapbox step structure expected by mergeMapboxSteps().
     */
    protected function normalizeOsrmSteps(array $osrmSteps): array
    {
        $out = [];
        foreach ($osrmSteps as $step) {
            $m = is_array($step['maneuver'] ?? null) ? $step['maneuver'] : [];
            $type = (string) ($m['type'] ?? 'continue');
            $modifier = (string) ($m['modifier'] ?? '');
            $roadName = trim((string) ($step['name'] ?? ''));

            $turnLabel = match ($modifier) {
                'left', 'sharp left' => 'Belok kiri',
                'slight left' => 'Serong kiri',
                'right', 'sharp right' => 'Belok kanan',
                'slight right' => 'Serong kanan',
                'uturn' => 'Putar balik',
                default => 'Lanjut',
            };

            $instr = match ($type) {
                'depart' => $roadName !== '' ? "Mulai menyusuri {$roadName}" : 'Mulai perjalanan',
                'arrive' => 'Menuju gerbang masuk Kampus USU',
                'roundabout', 'rotary' => $roadName !== '' ? "Masuk bundaran lalu ambil jalan ke {$roadName}" : 'Lewati bundaran',
                default => $roadName !== '' ? "{$turnLabel} ke {$roadName}" : $turnLabel,
            };

            $out[] = [
                'distance' => (float) ($step['distance'] ?? 0.0),
                'maneuver' => [
                    'instruction' => $instr,
                    'type' => $type,
                    'location' => $m['location'] ?? [98.65652, 3.565245],
                ],
            ];
        }

        return $out;
    }

    protected function sumAccessFeaturesMeters(array $accessFeatures): float
    {
        $sum = 0.0;
        foreach ($accessFeatures as $feat) {
            $coords = $feat['geometry']['coordinates'] ?? [];
            if (is_array($coords) && count($coords) >= 2) {
                $sum += $this->geometryGuard->polylineLengthMeters($coords);
            }
        }

        return $sum;
    }

    /**
     * Load usu_campus_graph.json (cached) and merge live active DB CampusRoutingOverrides.
     *
     * @return array<string, mixed>
     */
    public function getCompiledGraph(): array
    {
        if (self::$memoryCompiledGraph !== null) {
            return self::$memoryCompiledGraph;
        }

        $cached = Cache::remember(self::CACHE_KEY_COMPILED, 3600, function () {
            $base = $this->loadBaseGraphJson();
            return $this->mergeOverridesIntoGraph($base);
        });

        self::$memoryCompiledGraph = $cached;
        return $cached;
    }

    /**
     * Load base JSON graph from runtime storage or snapshot fallback.
     *
     * @return array<string, mixed>
     */
    public function loadBaseGraphJson(): array
    {
        foreach ([$this->graphBuilder->getRuntimeGraphPath(), $this->graphBuilder->getSnapshotGraphPath()] as $path) {
            if (is_file($path)) {
                $raw = @file_get_contents($path);
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && !empty($decoded['nodes']) && !empty($decoded['edges'])) {
                        return $decoded;
                    }
                }
            }
        }

        return ['nodes' => [], 'edges' => [], 'obstacles' => ['buildings' => [], 'barriers' => []], 'stats' => []];
    }

    /**
     * Merge active DB CampusRoutingOverride rows dynamically into a base graph.
     *
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    public function mergeOverridesIntoGraph(array $graph): array
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];
        $obstacles = $graph['obstacles'] ?? ['buildings' => [], 'barriers' => []];
        $barriers = $obstacles['barriers'] ?? [];
        $entranceOverrides = [];

        if (!Schema::hasTable('campus_routing_overrides')) {
            return [
                'nodes' => $nodes,
                'edges' => $edges,
                'obstacles' => ['buildings' => $obstacles['buildings'] ?? [], 'barriers' => $barriers],
                'entrance_overrides' => $entranceOverrides,
                'stats' => $graph['stats'] ?? [],
            ];
        }

        $overrides = CampusRoutingOverride::where('is_active', true)->orderBy('id')->get();
        $customBarriers = [];
        $walkwaysAndBridges = [];

        foreach ($overrides as $ov) {
            $coords = is_array($ov->coordinates_json) ? $ov->coordinates_json : [];
            $meta = is_array($ov->metadata_json) ? $ov->metadata_json : [];

            if ($ov->type === 'entrance') {
                $tType = $meta['target_type'] ?? null;
                $tId = $meta['target_id'] ?? null;
                $pt = $coords[0] ?? null;
                if ($tType && $tId && is_array($pt) && count($pt) >= 2) {
                    $entranceOverrides["{$tType}:{$tId}"] = [(float) $pt[0], (float) $pt[1]];
                }
                continue;
            }

            if ($ov->type === 'barrier' && count($coords) >= 2) {
                $normCoords = array_map(static fn ($c): array => [(float) $c[0], (float) $c[1]], $coords);
                $customBarrier = [
                    'id' => 'custom_barrier_' . $ov->id,
                    'kind' => 'custom_barrier',
                    'tag' => 'custom_barrier',
                    'name' => $ov->name,
                    'bbox' => $this->geometryGuard->computeBbox($normCoords),
                    'coords' => $normCoords,
                    'is_custom' => true,
                ];
                $barriers[] = $customBarrier;
                $customBarriers[] = $customBarrier;
                continue;
            }

            if (in_array($ov->type, ['walkway', 'bridge'], true) && count($coords) >= 2) {
                $walkwaysAndBridges[] = $ov;
            }
        }

        // Sever existing non-bridge base edges intersected by any custom barrier
        if (!empty($customBarriers)) {
            foreach ($edges as &$edge) {
                if (!empty($edge['is_bridge'])) {
                    continue;
                }
                $u = $nodes[$edge['u']] ?? null;
                $v = $nodes[$edge['v']] ?? null;
                if (!$u || !$v) continue;
                if ($this->geometryGuard->segmentCrossesBarrier([$u['lng'], $u['lat']], [$v['lng'], $v['lat']], $customBarriers, true)) {
                    $edge['blocked_by_barrier'] = true;
                }
            }
            unset($edge);
        }

        // Snapshot existing nodes before inserting overrides so stitch-ins connect to real roads/adjacent walkways cleanly
        foreach ($walkwaysAndBridges as $ov) {
            $coords = array_map(static fn ($c): array => [(float) $c[0], (float) $c[1]], $ov->coordinates_json);
            $meta = is_array($ov->metadata_json) ? $ov->metadata_json : [];
            $isBridge = $ov->type === 'bridge';
            $allowVehicle = (bool) ($meta['allow_vehicle'] ?? false);

            $ovNodeIds = [];
            foreach ($coords as $idx => $pt) {
                $nid = "ov_{$ov->id}_{$idx}";
                $nodes[$nid] = [
                    'id' => $nid,
                    'lat' => $pt[1],
                    'lng' => $pt[0],
                    'is_custom' => true,
                ];
                $ovNodeIds[] = $nid;
            }

            // Internal segments along override polyline
            $nCount = count($ovNodeIds);
            for ($i = 0; $i < $nCount - 1; $i++) {
                $uId = $ovNodeIds[$i];
                $vId = $ovNodeIds[$i + 1];
                $d = $this->geometryGuard->haversineMeters(
                    $nodes[$uId]['lat'],
                    $nodes[$uId]['lng'],
                    $nodes[$vId]['lat'],
                    $nodes[$vId]['lng']
                );
                $edges[] = [
                    'id' => "ov_e_{$ov->id}_{$i}",
                    'way_id' => "ov_{$ov->id}",
                    'u' => $uId,
                    'v' => $vId,
                    'distance_m' => max(0.5, round($d, 3)),
                    'walkable' => true,
                    'drivable' => $allowVehicle,
                    'oneway_vehicle' => 0,
                    'is_bridge' => $isBridge,
                    'osm_culvert' => $isBridge,
                    'road_class' => $isBridge ? 'bridge_foot' : 'campus_walkway',
                    'name' => $ov->name,
                    'walk_weight' => 1.00,
                    'drive_weight' => 1.05,
                    'is_custom' => true,
                ];
            }

            // Stitch start/end (and intermediate) vertices to nearest nodes in graph
            foreach ($ovNodeIds as $idx => $ovNid) {
                $isEndpoint = ($idx === 0 || $idx === $nCount - 1);
                $searchRadius = $isEndpoint ? 85.0 : 22.0;
                $pt = [$nodes[$ovNid]['lng'], $nodes[$ovNid]['lat']];
                $candidates = $this->geometryGuard->findNearestNodes($pt, $nodes, 8, $searchRadius);

                $stitched = 0;
                foreach ($candidates as $cand) {
                    $cid = $cand['id'];
                    if (str_starts_with($cid, "ov_{$ov->id}_")) {
                        continue;
                    }
                    $cPt = [$cand['lng'], $cand['lat']];
                    $crosses = $this->geometryGuard->segmentCrossesObstacle(
                        $pt,
                        $cPt,
                        ['buildings' => $obstacles['buildings'] ?? [], 'barriers' => $barriers],
                        [],
                        false,
                        false
                    );
                    if ($crosses) {
                        continue;
                    }
                    $edges[] = [
                        'id' => "ov_stitch_{$ov->id}_{$idx}_{$cid}",
                        'way_id' => "ov_stitch_{$ov->id}",
                        'u' => $ovNid,
                        'v' => $cid,
                        'distance_m' => max(0.5, round($cand['distance_m'], 3)),
                        'walkable' => true,
                        'drivable' => $allowVehicle,
                        'oneway_vehicle' => 0,
                        'is_bridge' => false,
                        'osm_culvert' => false,
                        'road_class' => 'campus_walkway',
                        'name' => $ov->name,
                        'walk_weight' => 1.00,
                        'drive_weight' => 1.05,
                        'is_custom' => true,
                    ];
                    $stitched++;
                    if ($stitched >= 2) {
                        break;
                    }
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'obstacles' => [
                'buildings' => $obstacles['buildings'] ?? [],
                'barriers' => $barriers,
            ],
            'entrance_overrides' => $entranceOverrides,
            'stats' => $graph['stats'] ?? [],
        ];
    }

    /**
     * Resolve endpoint record + manual/safe entrance coordinate.
     *
     * @return array<string, mixed>
     */
    protected function resolveEndpoint(
        ?string $type,
        mixed $id,
        ?float $lat,
        ?float $lng,
        ?string $customLabel,
        string $fallbackLabel,
        array $entranceOverrides,
        array $obstacles,
        array $graphNodes
    ): array {
        $label = $customLabel ?: $fallbackLabel;
        $manualEntrance = null;
        $rawLat = $lat;
        $rawLng = $lng;

        if ($type === 'building' && $id) {
            $b = Building::find($id);
            if (!$b) {
                throw new \InvalidArgumentException("Gedung ID {$id} tidak ditemukan.");
            }
            $rawLat = (float) $b->latitude;
            $rawLng = (float) $b->longitude;
            $label = $b->code ? "{$b->code} — {$b->name}" : $b->name;
            if ($b->entrance_lat !== null && $b->entrance_lng !== null) {
                $manualEntrance = [(float) $b->entrance_lng, (float) $b->entrance_lat];
            } elseif (isset($entranceOverrides["building:{$b->id}"])) {
                $manualEntrance = $entranceOverrides["building:{$b->id}"];
            }
        } elseif ($type === 'fotocopy' && $id) {
            $p = PhotocopyPlace::find($id);
            if (!$p) {
                throw new \InvalidArgumentException("Mitra Fotokopi ID {$id} tidak ditemukan.");
            }
            $rawLat = (float) $p->latitude;
            $rawLng = (float) $p->longitude;
            $label = $p->name;
            if ($p->entrance_lat !== null && $p->entrance_lng !== null) {
                $manualEntrance = [(float) $p->entrance_lng, (float) $p->entrance_lat];
            } elseif (isset($entranceOverrides["fotocopy:{$p->id}"])) {
                $manualEntrance = $entranceOverrides["fotocopy:{$p->id}"];
            }
        }

        if ($rawLat === null || $rawLng === null || !is_finite($rawLat) || !is_finite($rawLng)) {
            throw new \InvalidArgumentException("Koordinat {$fallbackLabel} tidak lengkap atau tidak valid.");
        }

        $safeEntrance = $this->geometryGuard->computeSafeEntrancePoint(
            [$rawLng, $rawLat],
            $manualEntrance,
            $obstacles['buildings'] ?? [],
            $obstacles['barriers'] ?? [],
            $graphNodes
        );

        return [
            'type' => $type ?: 'point',
            'id' => $id ? (int) $id : null,
            'raw_lat' => $rawLat,
            'raw_lng' => $rawLng,
            'lat' => $safeEntrance['lat'],
            'lng' => $safeEntrance['lng'],
            'entrance' => [$safeEntrance['lng'], $safeEntrance['lat']],
            'own_building_id' => $safeEntrance['own_building_id'],
            'is_manual_entrance' => $safeEntrance['is_manual_entrance'],
            'label' => $label,
        ];
    }

    /**
     * Find top obstacle-free candidate graph nodes within 250m (expanding to 450m if needed).
     *
     * @param  array{0: float, 1: float}  $point
     * @return array<string, array{node_id: string, coords: array, distance_m: float}>
     */
    protected function findLegalSnapCandidates(
        array $point,
        array $connectedNodes,
        array $obstacles,
        array $allowedBuildingIds
    ): array {
        $valid = [];

        foreach ([250.0, 450.0] as $radius) {
            $candidates = $this->geometryGuard->findNearestNodes($point, $connectedNodes, 30, $radius);
            foreach ($candidates as $cand) {
                $cPt = [$cand['lng'], $cand['lat']];
                $connector = $this->geometryGuard->buildLastMileConnector(
                    $point,
                    $cPt,
                    $obstacles,
                    $allowedBuildingIds
                );
                if ($connector !== null) {
                    $dist = 0.0;
                    $cnt = count($connector);
                    for ($i = 0; $i < $cnt - 1; $i++) {
                        $dist += $this->geometryGuard->haversineMeters(
                            $connector[$i][1],
                            $connector[$i][0],
                            $connector[$i + 1][1],
                            $connector[$i + 1][0]
                        );
                    }
                    $valid[$cand['id']] = [
                        'node_id' => $cand['id'],
                        'coords' => $connector,
                        'distance_m' => $dist,
                    ];
                    if (count($valid) >= 6) {
                        return $valid;
                    }
                }
            }
            if (!empty($valid)) {
                return $valid;
            }
        }

        return $valid;
    }

    protected function isEdgeAllowedForMode(array $edge, array $nodes, string $mode): bool
    {
        if (!empty($edge['blocked_by_barrier'])) {
            return false;
        }
        $u = (string) ($edge['u'] ?? '');
        $v = (string) ($edge['v'] ?? '');
        if (!isset($nodes[$u], $nodes[$v])) {
            return false;
        }

        if ($mode === 'driving') {
            if (empty($edge['drivable'])) {
                return false;
            }
            $roadClass = strtolower((string) ($edge['road_class'] ?? ''));
            if (in_array($roadClass, self::NON_DRIVABLE_ROAD_CLASSES, true)) {
                return false;
            }

            return true;
        }

        return !empty($edge['walkable']);
    }

    /**
     * Prepare working nodes/adjacency + projection-aware edge snaps (with virtual split nodes when snapping mid-edge).
     *
     * @return array{nodes: array, adjacency: array, origin_snaps: array, dest_snaps: array}
     */
    protected function prepareWorkingGraphWithProjectedSnaps(
        array $edges,
        array $nodes,
        string $mode,
        array $oEntrance,
        array $dEntrance,
        array $obstacles,
        array $originAllowedBuildingIds,
        array $destAllowedBuildingIds
    ): array {
        $workingNodes = $nodes;
        $adjacency = $this->buildModeAdjacency($edges, $nodes, $mode);

        $oCandidates = $this->findProjectedEdgeSnapCandidates($oEntrance, $edges, $nodes, $mode, $obstacles, $originAllowedBuildingIds);
        $dCandidates = $this->findProjectedEdgeSnapCandidates($dEntrance, $edges, $nodes, $mode, $obstacles, $destAllowedBuildingIds);

        $originSnaps = [];
        $destSnaps = [];
        $oVirtualRecords = [];
        $dVirtualRecords = [];

        $attachSplitSnap = function (array $cand, string $side, int $idx) use (&$workingNodes, &$adjacency, $mode, &$oVirtualRecords, &$dVirtualRecords): array {
            $edge = $cand['edge'];
            $t = (float) $cand['t'];
            $u = (string) $edge['u'];
            $v = (string) $edge['v'];
            $projPt = $cand['proj_point'];
            $baseId = (string) ($edge['id'] ?? ($u . '_' . $v));
            $uCoord = [(float) $workingNodes[$u]['lng'], (float) $workingNodes[$u]['lat']];
            $vCoord = [(float) $workingNodes[$v]['lng'], (float) $workingNodes[$v]['lat']];
            $edgeDist = max(0.5, (float) ($edge['distance_m'] ?? $this->geometryGuard->haversineMeters($uCoord[1], $uCoord[0], $vCoord[1], $vCoord[0])));

            if ($t <= 0.05) {
                $targetNode = $u;
            } elseif ($t >= 0.95) {
                $targetNode = $v;
            } else {
                $targetNode = "__snap_{$side}_{$idx}";
                $workingNodes[$targetNode] = [
                    'id' => $targetNode,
                    'lat' => $projPt[1],
                    'lng' => $projPt[0],
                    'is_virtual_snap' => true,
                ];

                $distU = max(0.5, round($edgeDist * $t, 3));
                $distV = max(0.5, round($edgeDist * (1.0 - $t), 3));
                $weightFactor = $this->computeEdgeModeWeight($edge, $mode);
                $oneway = (int) ($edge['oneway_vehicle'] ?? 0);
                $allowForward = ($mode === 'walking') || ($oneway !== -1);
                $allowBackward = ($mode === 'walking') || ($oneway !== 1);

                $partUEdge = array_merge($edge, [
                    'id' => "{$baseId}__u_{$side}_{$idx}",
                    'u' => $u,
                    'v' => $targetNode,
                    'distance_m' => $distU,
                ]);
                $partVEdge = array_merge($edge, [
                    'id' => "{$baseId}__v_{$side}_{$idx}",
                    'u' => $targetNode,
                    'v' => $v,
                    'distance_m' => $distV,
                ]);

                if ($allowForward) {
                    $adjacency[$u][] = [
                        'to' => $targetNode,
                        'cost' => max(0.1, $distU * $weightFactor),
                        'edge' => array_merge($partUEdge, ['uCoord' => $uCoord, 'vCoord' => $projPt]),
                    ];
                    $adjacency[$targetNode][] = [
                        'to' => $v,
                        'cost' => max(0.1, $distV * $weightFactor),
                        'edge' => array_merge($partVEdge, ['uCoord' => $projPt, 'vCoord' => $vCoord]),
                    ];
                }
                if ($allowBackward) {
                    $adjacency[$targetNode][] = [
                        'to' => $u,
                        'cost' => max(0.1, $distU * $weightFactor),
                        'edge' => array_merge($partUEdge, ['u' => $targetNode, 'v' => $u, 'uCoord' => $projPt, 'vCoord' => $uCoord]),
                    ];
                    $adjacency[$v][] = [
                        'to' => $targetNode,
                        'cost' => max(0.1, $distV * $weightFactor),
                        'edge' => array_merge($partVEdge, ['u' => $v, 'v' => $targetNode, 'uCoord' => $vCoord, 'vCoord' => $projPt]),
                    ];
                }
            }

            $record = [
                'node_id' => $targetNode,
                'base_id' => $baseId,
                'edge' => $edge,
                't' => $t,
                'coord' => [$workingNodes[$targetNode]['lng'], $workingNodes[$targetNode]['lat']],
            ];
            if ($side === 'o') {
                $oVirtualRecords[] = $record;
            } else {
                $dVirtualRecords[] = $record;
            }

            return [
                'node_id' => $targetNode,
                'coords' => $cand['coords'],
                'distance_m' => $cand['distance_m'],
            ];
        };

        foreach ($oCandidates as $idx => $cand) {
            $snap = $attachSplitSnap($cand, 'o', $idx);
            $nid = $snap['node_id'];
            if (!isset($originSnaps[$nid]) || $snap['distance_m'] < $originSnaps[$nid]['distance_m']) {
                $originSnaps[$nid] = $snap;
            }
        }
        foreach ($dCandidates as $idx => $cand) {
            $snap = $attachSplitSnap($cand, 'd', $idx);
            $nid = $snap['node_id'];
            if (!isset($destSnaps[$nid]) || $snap['distance_m'] < $destSnaps[$nid]['distance_m']) {
                $destSnaps[$nid] = $snap;
            }
        }

        // Direct link between virtual snap nodes lying along the exact same edge (avoids U-turn backtracking)
        foreach ($oVirtualRecords as $oRec) {
            foreach ($dVirtualRecords as $dRec) {
                if ($oRec['base_id'] !== $dRec['base_id'] || $oRec['node_id'] === $dRec['node_id']) {
                    continue;
                }
                $edge = $oRec['edge'];
                $oneway = (int) ($edge['oneway_vehicle'] ?? 0);
                $allowForward = ($mode === 'walking') || ($oneway !== -1);
                $allowBackward = ($mode === 'walking') || ($oneway !== 1);
                $canReach = ($oRec['t'] <= $dRec['t'] && $allowForward) || ($oRec['t'] >= $dRec['t'] && $allowBackward);
                if (!$canReach) {
                    continue;
                }
                $dDirect = max(0.5, round($this->geometryGuard->haversineMeters($oRec['coord'][1], $oRec['coord'][0], $dRec['coord'][1], $dRec['coord'][0]), 3));
                $weightFactor = $this->computeEdgeModeWeight($edge, $mode);
                $adjacency[$oRec['node_id']][] = [
                    'to' => $dRec['node_id'],
                    'cost' => max(0.1, $dDirect * $weightFactor),
                    'edge' => array_merge($edge, [
                        'id' => "{$oRec['base_id']}__same_{$oRec['node_id']}_{$dRec['node_id']}",
                        'u' => $oRec['node_id'],
                        'v' => $dRec['node_id'],
                        'distance_m' => $dDirect,
                        'uCoord' => $oRec['coord'],
                        'vCoord' => $dRec['coord'],
                    ]),
                ];
            }
        }

        return [
            'nodes' => $workingNodes,
            'adjacency' => $adjacency,
            'origin_snaps' => $originSnaps,
            'dest_snaps' => $destSnaps,
        ];
    }

    /**
     * Find top obstacle-free projected points on mode-eligible road/walkway segments.
     *
     * @return array<int, array{edge: array, proj_point: array{0: float, 1: float}, t: float, coords: array, distance_m: float}>
     */
    protected function findProjectedEdgeSnapCandidates(
        array $point,
        array $edges,
        array $nodes,
        string $mode,
        array $obstacles,
        array $allowedBuildingIds
    ): array {
        $isDest = false;
        // Both start->proj and proj->start have identical corridor geometry for obstacle checking
        unset($isDest);

        foreach ([250.0, 450.0] as $radius) {
            $degPad = ($radius + 350.0) / 111000.0;
            $rawCandidates = [];

            foreach ($edges as $edge) {
                if (!$this->isEdgeAllowedForMode($edge, $nodes, $mode)) {
                    continue;
                }
                $u = $nodes[$edge['u']];
                $v = $nodes[$edge['v']];
                if ((abs($u['lat'] - $point[1]) > $degPad || abs($u['lng'] - $point[0]) > $degPad)
                    && (abs($v['lat'] - $point[1]) > $degPad || abs($v['lng'] - $point[0]) > $degPad)) {
                    continue;
                }

                $uCoord = [(float) $u['lng'], (float) $u['lat']];
                $vCoord = [(float) $v['lng'], (float) $v['lat']];
                $proj = $this->geometryGuard->projectPointOnSegment($point, $uCoord, $vCoord);
                if ($proj['distance_m'] > $radius) {
                    continue;
                }

                $rawCandidates[] = [
                    'edge' => $edge,
                    'proj_point' => $proj['point'],
                    't' => $proj['t'],
                    'straight_dist' => $proj['distance_m'],
                ];
            }

            usort($rawCandidates, static fn (array $a, array $b): int => $a['straight_dist'] <=> $b['straight_dist']);

            $within60 = [];
            $beyond60 = [];
            $bestWithin60Dist = INF;
            $tested = 0;

            foreach ($rawCandidates as $cand) {
                if (!empty($within60) && $cand['straight_dist'] > min(60.0, $bestWithin60Dist + 18.0)) {
                    break;
                }
                if ($tested >= 18) {
                    break;
                }
                $tested++;

                // Fast path: if direct sightline is clear and <= 60m, no need to run corner visibility graph
                $directClear = !$this->geometryGuard->segmentCrossesObstacle(
                    $point,
                    $cand['proj_point'],
                    $obstacles,
                    $allowedBuildingIds
                );
                $connector = $directClear
                    ? [$point, $cand['proj_point']]
                    : $this->geometryGuard->buildLastMileConnector(
                        $point,
                        $cand['proj_point'],
                        $obstacles,
                        $allowedBuildingIds
                    );

                if ($connector === null) {
                    continue;
                }
                $dist = $this->geometryGuard->polylineLengthMeters($connector);
                $item = [
                    'edge' => $cand['edge'],
                    'proj_point' => $cand['proj_point'],
                    't' => $cand['t'],
                    'coords' => $connector,
                    'distance_m' => $dist,
                ];
                if ($dist <= 60.0) {
                    $within60[] = $item;
                    if ($dist < $bestWithin60Dist) {
                        $bestWithin60Dist = $dist;
                    }
                    if (count($within60) >= 6 && $bestWithin60Dist < 12.0) {
                        break;
                    }
                } else {
                    $beyond60[] = $item;
                    if (empty($within60) && count($beyond60) >= 4) {
                        break;
                    }
                }
            }

            $selected = !empty($within60) ? $within60 : $beyond60;
            if (!empty($selected)) {
                usort($selected, static fn (array $a, array $b): int => $a['distance_m'] <=> $b['distance_m']);
                $bestActual = $selected[0]['distance_m'];
                if ($bestActual <= 60.0) {
                    $maxAllowed = min(60.0, $bestActual + 18.0);
                    $selected = array_values(array_filter(
                        $selected,
                        static fn (array $it): bool => $it['distance_m'] <= $maxAllowed
                    ));
                }
                return array_slice($selected, 0, 6);
            }
        }

        return [];
    }

    protected function computeEdgeModeWeight(array $edge, string $mode): float
    {
        if ($mode === 'driving') {
            return (float) ($edge['drive_weight'] ?? 1.0);
        }

        $baseWeight = (float) ($edge['walk_weight'] ?? 1.0);
        $roadClass = strtolower((string) ($edge['road_class'] ?? ''));

        return in_array($roadClass, self::PEDESTRIAN_PRIORITY_CLASSES, true)
            ? $baseWeight * 0.92
            : $baseWeight * 1.10;
    }

    /**
     * Directed/weighted adjacency map for selected travel mode.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function buildModeAdjacency(array $edges, array $nodes, string $mode): array
    {
        $adj = [];

        foreach ($edges as $edge) {
            if (!$this->isEdgeAllowedForMode($edge, $nodes, $mode)) {
                continue;
            }
            $u = (string) ($edge['u'] ?? '');
            $v = (string) ($edge['v'] ?? '');

            $dist = (float) ($edge['distance_m'] ?? 1.0);
            $weight = $this->computeEdgeModeWeight($edge, $mode);
            $cost = max(0.1, $dist * $weight);

            $uCoord = [(float) $nodes[$u]['lng'], (float) $nodes[$u]['lat']];
            $vCoord = [(float) $nodes[$v]['lng'], (float) $nodes[$v]['lat']];
            $oneway = (int) ($edge['oneway_vehicle'] ?? 0);

            $allowUV = ($mode === 'walking') || ($oneway !== -1);
            $allowVU = ($mode === 'walking') || ($oneway !== 1);

            if ($allowUV) {
                $adj[$u][] = [
                    'to' => $v,
                    'cost' => $cost,
                    'edge' => array_merge($edge, ['uCoord' => $uCoord, 'vCoord' => $vCoord]),
                ];
            }
            if ($allowVU) {
                $adj[$v][] = [
                    'to' => $u,
                    'cost' => $cost,
                    'edge' => array_merge($edge, ['uCoord' => $vCoord, 'vCoord' => $uCoord]),
                ];
            }
        }

        return $adj;
    }

    /**
     * Multi-origin-snap, multi-destination-snap A* using SplPriorityQueue min-heap.
     *
     * @return array{start_node: string, end_node: string, edges: array}|null
     */
    protected function runMultiTerminalAStar(
        array $originSnaps,
        array $destSnaps,
        array $nodes,
        array $adjacency,
        array $destPoint,
        array $blacklistedEdgeIds
    ): ?array {
        $pq = new \SplPriorityQueue();
        $pq->setExtractFlags(\SplPriorityQueue::EXTR_DATA);

        $gScore = [];
        $parent = [];
        $startNodeOf = [];
        $closed = [];

        foreach ($originSnaps as $nid => $snap) {
            if (!isset($adjacency[$nid])) continue;
            $g0 = (float) $snap['distance_m'];
            $gScore[$nid] = $g0;
            $startNodeOf[$nid] = $nid;
            $h0 = $this->geometryGuard->haversineMeters($nodes[$nid]['lat'], $nodes[$nid]['lng'], $destPoint[1], $destPoint[0]);
            $pq->insert(['node' => $nid, 'f' => $g0 + $h0], -($g0 + $h0));
        }

        $bestDestCost = INF;
        $bestDestNode = null;

        while (!$pq->isEmpty()) {
            $item = $pq->extract();
            $curr = $item['node'];

            if (isset($closed[$curr])) {
                continue;
            }
            if ($item['f'] >= $bestDestCost) {
                break;
            }
            $closed[$curr] = true;

            if (isset($destSnaps[$curr])) {
                $totalToDest = $gScore[$curr] + (float) $destSnaps[$curr]['distance_m'];
                if ($totalToDest < $bestDestCost) {
                    $bestDestCost = $totalToDest;
                    $bestDestNode = $curr;
                }
            }

            foreach ($adjacency[$curr] ?? [] as $arc) {
                $edgeId = (string) ($arc['edge']['id'] ?? '');
                if ($edgeId !== '' && isset($blacklistedEdgeIds[$edgeId])) {
                    continue;
                }

                $nxt = $arc['to'];
                if (isset($closed[$nxt])) {
                    continue;
                }

                $tentativeG = $gScore[$curr] + $arc['cost'];
                if (!isset($gScore[$nxt]) || $tentativeG < $gScore[$nxt]) {
                    $gScore[$nxt] = $tentativeG;
                    $parent[$nxt] = ['prev' => $curr, 'edge' => $arc['edge']];
                    $startNodeOf[$nxt] = $startNodeOf[$curr];
                    $h = $this->geometryGuard->haversineMeters($nodes[$nxt]['lat'], $nodes[$nxt]['lng'], $destPoint[1], $destPoint[0]);
                    $f = $tentativeG + $h;
                    $pq->insert(['node' => $nxt, 'f' => $f], -$f);
                }
            }
        }

        if ($bestDestNode === null) {
            return null;
        }

        $edgesOut = [];
        $cur = $bestDestNode;
        while (isset($parent[$cur])) {
            array_unshift($edgesOut, $parent[$cur]['edge']);
            $cur = $parent[$cur]['prev'];
        }

        return [
            'start_node' => $startNodeOf[$bestDestNode] ?? $cur,
            'end_node' => $bestDestNode,
            'edges' => $edgesOut,
        ];
    }

    protected function isPointNearCampusGraph(array $point, array $nodes): bool
    {
        $b = CampusGraphBuilderService::BBOX;
        $pad = 0.0005;
        if ($point[1] < $b['south'] - $pad || $point[1] > $b['north'] + $pad
            || $point[0] < $b['west'] - $pad || $point[0] > $b['east'] + $pad) {
            return false;
        }

        return !empty($this->geometryGuard->findNearestNodes($point, $nodes, 1, 250.0));
    }

    /**
     * @param  array{0: float, 1: float}  $externalPoint
     */
    protected function findClosestGatewayNode(array $externalPoint, array $connectedNodes): array
    {
        $best = null;
        $bestDist = INF;
        foreach ($connectedNodes as $node) {
            $d = $this->geometryGuard->haversineMeters($externalPoint[1], $externalPoint[0], $node['lat'], $node['lng']);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $node;
            }
        }
        if ($best === null) {
            throw new \RuntimeException('Tidak ada simpul gerbang kampus yang terhubung.');
        }

        return $best;
    }

    protected function mergeMapboxSteps(array $rawSteps, string $fromLabel, string $toLabel, bool $includeDepart, bool $includeArrive): array
    {
        $out = [];
        if ($includeDepart) {
            $firstCoord = $rawSteps[0]['maneuver']['location'] ?? [98.65652, 3.565245];
            $out[] = [
                'instruction' => "Mulai perjalanan dari {$fromLabel}",
                'distance_meters' => 0,
                'maneuver' => 'depart',
                'coordinate' => $firstCoord,
            ];
        }

        foreach ($rawSteps as $idx => $step) {
            $instr = trim((string) ($step['maneuver']['instruction'] ?? 'Lanjutkan perjalanan'));
            $dist = (int) round((float) ($step['distance'] ?? 0));
            $coord = $step['maneuver']['location'] ?? [98.65652, 3.565245];
            $type = (string) ($step['maneuver']['type'] ?? 'straight');
            if ($type === 'arrive' && !$includeArrive) {
                continue;
            }
            if ($type === 'depart' && !$includeDepart) {
                continue;
            }
            $out[] = [
                'instruction' => $instr . ($dist > 0 ? " ({$dist} m)" : ''),
                'distance_meters' => $dist,
                'maneuver' => $type === 'turn' ? 'turn_right' : 'straight',
                'coordinate' => $coord,
            ];
        }

        if ($includeArrive) {
            $last = end($rawSteps);
            $lastCoord = $last['maneuver']['location'] ?? [98.65652, 3.565245];
            $out[] = [
                'instruction' => "Tiba di {$toLabel}",
                'distance_meters' => 0,
                'maneuver' => 'arrive',
                'coordinate' => $lastCoord,
            ];
        }

        return $out;
    }

    protected function formatAlreadyAtDestinationResponse(
        string $mode,
        string $engineSource,
        array $originInfo,
        array $destInfo,
        float $distanceMeters
    ): array {
        $distRounded = max(1, (int) round($distanceMeters));

        return [
            'status' => 'already_at_destination',
            'already_at_destination' => true,
            'mode' => $mode,
            'engine_source' => $engineSource,
            'engine_label' => $engineSource,
            'message' => "Anda sudah berada di sekitar lokasi tujuan (±{$distRounded} m).",
            'origin' => [
                'type' => $originInfo['type'],
                'id' => $originInfo['id'],
                'label' => $originInfo['label'],
                'lat' => $originInfo['lat'],
                'lng' => $originInfo['lng'],
                'raw_lat' => $originInfo['raw_lat'],
                'raw_lng' => $originInfo['raw_lng'],
            ],
            'destination' => [
                'type' => $destInfo['type'],
                'id' => $destInfo['id'],
                'label' => $destInfo['label'],
                'lat' => $destInfo['lat'],
                'lng' => $destInfo['lng'],
                'raw_lat' => $destInfo['raw_lat'],
                'raw_lng' => $destInfo['raw_lng'],
                'is_manual_entrance' => (bool) $destInfo['is_manual_entrance'],
            ],
            'distance_meters' => $distRounded,
            'distance_text' => "±{$distRounded} m",
            'duration_minutes' => 0,
            'duration_text' => 'Sudah tiba',
            'main_route_geojson' => null,
            'access_segments_geojson' => [
                'type' => 'FeatureCollection',
                'features' => [],
            ],
            'steps' => [],
            'geometry_audit' => [
                'building_intersections' => 0,
                'barrier_intersections' => 0,
                'verified' => true,
            ],
        ];
    }

    protected function formatResponse(
        string $mode,
        string $engineSource,
        array $originInfo,
        array $destInfo,
        float $distanceMeters,
        array $mainLineCoords,
        array $accessFeatures,
        array $steps,
        array $audit
    ): array {
        $distRounded = (int) round($distanceMeters);
        $speed = $mode === 'driving' ? self::DRIVING_SPEED_M_PER_MIN : self::WALKING_SPEED_M_PER_MIN;
        $durationMinutes = max(1, (int) ceil($distanceMeters / $speed));
        $distText = $distRounded >= 1000
            ? number_format($distRounded / 1000, 2, ',', '.') . ' km'
            : "{$distRounded} m";
        $durText = $mode === 'driving'
            ? "{$durationMinutes} mnt berkendara"
            : "{$durationMinutes} mnt jalan kaki";

        return [
            'status' => 'ok',
            'mode' => $mode,
            'engine_source' => $engineSource,
            'engine_label' => $engineSource,
            'origin' => [
                'type' => $originInfo['type'],
                'id' => $originInfo['id'],
                'label' => $originInfo['label'],
                'lat' => $originInfo['lat'],
                'lng' => $originInfo['lng'],
                'raw_lat' => $originInfo['raw_lat'],
                'raw_lng' => $originInfo['raw_lng'],
            ],
            'destination' => [
                'type' => $destInfo['type'],
                'id' => $destInfo['id'],
                'label' => $destInfo['label'],
                'lat' => $destInfo['lat'],
                'lng' => $destInfo['lng'],
                'raw_lat' => $destInfo['raw_lat'],
                'raw_lng' => $destInfo['raw_lng'],
                'is_manual_entrance' => (bool) $destInfo['is_manual_entrance'],
            ],
            'distance_meters' => $distRounded,
            'distance_text' => $distText,
            'duration_minutes' => $durationMinutes,
            'duration_text' => $durText,
            'main_route_geojson' => [
                'type' => 'Feature',
                'properties' => [
                    'mode' => $mode,
                    'engine_source' => $engineSource,
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $mainLineCoords,
                ],
            ],
            'access_segments_geojson' => [
                'type' => 'FeatureCollection',
                'features' => $accessFeatures,
            ],
            'steps' => $steps,
            'geometry_audit' => [
                'building_intersections' => (int) ($audit['building_intersections'] ?? 0),
                'barrier_intersections' => (int) ($audit['barrier_intersections'] ?? 0),
                'verified' => (bool) ($audit['verified'] ?? false),
            ],
        ];
    }
}
