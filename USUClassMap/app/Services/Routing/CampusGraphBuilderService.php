<?php

namespace App\Services\Routing;

use App\Models\Building;
use App\Models\PhotocopyPlace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CampusGraphBuilderService
{
    public const BBOX = [
        'south' => 3.5525,
        'west' => 98.6475,
        'north' => 3.5720,
        'east' => 98.6655,
    ];

    protected const OVERPASS_MIRRORS = [
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
        'https://overpass-api.de/api/interpreter',
    ];

    public function __construct(
        protected GeometryGuardService $geometryGuard
    ) {}

    public function getRuntimeGraphPath(): string
    {
        return storage_path('app/routing/usu_campus_graph.json');
    }

    public function getSnapshotGraphPath(): string
    {
        return database_path('seeders/snapshots/campus_routing_graph.json');
    }

    /**
     * Build or load compiled OSM Campus Graph and persist to runtime + Git snapshot paths.
     *
     * @return array<string, mixed>
     */
    public function buildAndSave(bool $fromSnapshot = false): array
    {
        $sourceLabel = 'overpass_api';
        $graphData = null;

        if ($fromSnapshot) {
            $graphData = $this->loadFromSnapshotFile();
            $sourceLabel = 'snapshot_file';
        } else {
            [$osmElements, $fetchedSource] = $this->fetchOsmElements();
            if ($osmElements !== null) {
                $graphData = $this->compileOsmElements($osmElements, $fetchedSource);
                $sourceLabel = $fetchedSource;
            } else {
                $graphData = $this->loadFromSnapshotFile();
                $sourceLabel = 'snapshot_fallback';
            }
        }

        if ($graphData === null) {
            throw new \RuntimeException('Gagal mengambil data OSM Overpass dan snapshot campus_routing_graph.json belum tersedia.');
        }

        // Always refresh building/photocopy connectivity stats against current DB if tables exist
        $graphData['stats']['source'] = $sourceLabel;
        $graphData['stats']['connectivity'] = $this->computeConnectivitySummary($graphData['nodes'] ?? []);
        $graphData['generated_at'] = now()->toIso8601String();

        $this->saveJson($this->getRuntimeGraphPath(), $graphData);
        $this->saveJson($this->getSnapshotGraphPath(), $graphData);

        return $graphData;
    }

    /**
     * Query Overpass API with fast mirror timeouts, falling back to OSM 0.6 Map JSON tiles.
     *
     * @return array{0: array<int, array<string, mixed>>|null, 1: string}
     */
    protected function fetchOsmElements(): array
    {
        $b = self::BBOX;
        $bboxStr = "{$b['south']},{$b['west']},{$b['north']},{$b['east']}";
        $query = <<<OVERPASS
[out:json][timeout:25];
(
  way["highway"~"^(footway|path|pedestrian|steps|corridor|living_street|residential|service|unclassified|tertiary|secondary|primary|track)$"]({$bboxStr});
  way["building"]({$bboxStr});
  way["waterway"~"^(ditch|drain|stream|canal)$"]({$bboxStr});
  way["barrier"~"^(wall|fence|hedge|retaining_wall)$"]({$bboxStr});
);
(._;>;);
out body qt;
OVERPASS;

        foreach (self::OVERPASS_MIRRORS as $mirror) {
            try {
                $resp = Http::asForm()
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'USUClassMap-CampusRouter/1.0',
                        'Accept' => 'application/json',
                    ])
                    ->post($mirror, ['data' => $query]);

                if ($resp->successful()) {
                    $elements = $resp->json('elements');
                    if (is_array($elements) && count($elements) > 0) {
                        Log::info('CampusGraphBuilder: Berhasil fetch OSM Overpass.', [
                            'mirror' => $mirror,
                            'elements' => count($elements),
                        ]);
                        return [$elements, 'overpass_live'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CampusGraphBuilder: Mirror Overpass gagal: ' . $e->getMessage(), [
                    'mirror' => $mirror,
                ]);
            }
        }

        $tileElements = $this->fetchViaOsmMapTilesFallback();
        if ($tileElements !== null) {
            return [$tileElements, 'osm_map_api_v06'];
        }

        return [null, 'none'];
    }

    /**
     * Fetch USU bounding box via small 0.0045-deg tiles from api.openstreetmap.org/api/0.6/map.json.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function fetchViaOsmMapTilesFallback(): ?array
    {
        $b = self::BBOX;
        $stepLat = 0.0049;
        $stepLng = 0.0045;
        $tileUrls = [];

        for ($lat = $b['south']; $lat < $b['north'] - 1e-6; $lat += $stepLat) {
            for ($lng = $b['west']; $lng < $b['east'] - 1e-6; $lng += $stepLng) {
                $s = round($lat, 5);
                $n = round(min($lat + $stepLat + 0.00015, $b['north']), 5);
                $w = round($lng, 5);
                $e = round(min($lng + $stepLng + 0.00015, $b['east']), 5);
                $tileUrls[] = "https://api.openstreetmap.org/api/0.6/map.json?bbox={$w},{$s},{$e},{$n}";
            }
        }

        $byTypeId = [];
        foreach (array_chunk($tileUrls, 4) as $batch) {
            try {
                $responses = Http::pool(function ($pool) use ($batch) {
                    $reqs = [];
                    foreach ($batch as $url) {
                        $reqs[] = $pool
                            ->connectTimeout(12)
                            ->timeout(90)
                            ->withHeaders([
                                'User-Agent' => 'USUClassMap-CampusRouter/1.0',
                                'Accept' => 'application/json',
                            ])
                            ->get($url);
                    }
                    return $reqs;
                });

                foreach ($responses as $idx => $resp) {
                    if ($resp instanceof \Throwable) {
                        Log::warning('CampusGraphBuilder: Tile OSM 0.6 pool error: ' . $resp->getMessage(), ['url' => $batch[$idx] ?? '']);
                        continue;
                    }
                    if ($resp->successful()) {
                        $elements = $resp->json('elements');
                        if (is_array($elements)) {
                            foreach ($elements as $el) {
                                $t = $el['type'] ?? '';
                                $id = $el['id'] ?? null;
                                if (($t === 'node' || $t === 'way') && $id !== null) {
                                    $byTypeId["{$t}_{$id}"] = $el;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $ex) {
                Log::warning('CampusGraphBuilder: Tile OSM 0.6 batch gagal: ' . $ex->getMessage());
            }
        }

        if (!empty($byTypeId)) {
            Log::info('CampusGraphBuilder: Berhasil fetch graf USU via OSM 0.6 Map API tiles.', [
                'elements' => count($byTypeId),
            ]);
            return array_values($byTypeId);
        }

        return null;
    }

    /**
     * Compile raw Overpass elements into normalized nodes, edges, building polygons, and barrier lines.
     *
     * @param  array<int, array<string, mixed>>  $elements
     * @return array<string, mixed>
     */
    public function compileOsmElements(array $elements, string $sourceTag = 'overpass_live'): array
    {
        $osmNodes = [];
        $ways = [];

        foreach ($elements as $el) {
            $type = $el['type'] ?? '';
            if ($type === 'node' && isset($el['id'], $el['lat'], $el['lon'])) {
                $osmNodes[(string) $el['id']] = [
                    'lat' => (float) $el['lat'],
                    'lng' => (float) $el['lon'],
                ];
            } elseif ($type === 'way' && isset($el['id'], $el['nodes']) && is_array($el['nodes'])) {
                $ways[] = $el;
            }
        }

        $buildingPolygons = [];
        $barriers = [];
        $rawEdges = [];
        $referencedHighwayNodeIds = [];

        foreach ($ways as $way) {
            $tags = $way['tags'] ?? [];
            $wayId = (string) $way['id'];
            $nodeIds = $way['nodes'];

            // 1. Building polygon obstacles
            if (!empty($tags['building']) && count($nodeIds) >= 4) {
                $ring = [];
                foreach ($nodeIds as $nid) {
                    $key = (string) $nid;
                    if (isset($osmNodes[$key])) {
                        $ring[] = [$osmNodes[$key]['lng'], $osmNodes[$key]['lat']];
                    }
                }
                if (count($ring) >= 3) {
                    $buildingPolygons[] = [
                        'id' => $wayId,
                        'name' => (string) ($tags['name'] ?? ($tags['building'] ?? 'Gedung')),
                        'bbox' => $this->geometryGuard->computeBbox($ring),
                        'ring' => $ring,
                    ];
                }
            }

            // 2. Linear barriers: ditches, drains, streams, walls, fences
            $waterway = strtolower((string) ($tags['waterway'] ?? ''));
            $barrierTag = strtolower((string) ($tags['barrier'] ?? ''));
            if (in_array($waterway, ['ditch', 'drain', 'stream', 'canal'], true)
                || in_array($barrierTag, ['wall', 'fence', 'hedge', 'retaining_wall'], true)) {
                $coords = [];
                foreach ($nodeIds as $nid) {
                    $key = (string) $nid;
                    if (isset($osmNodes[$key])) {
                        $coords[] = [$osmNodes[$key]['lng'], $osmNodes[$key]['lat']];
                    }
                }
                if (count($coords) >= 2) {
                    $barriers[] = [
                        'id' => $wayId,
                        'kind' => $waterway !== '' ? 'waterway' : 'barrier',
                        'tag' => $waterway !== '' ? $waterway : $barrierTag,
                        'name' => (string) ($tags['name'] ?? ''),
                        'bbox' => $this->geometryGuard->computeBbox($coords),
                        'coords' => $coords,
                        'is_custom' => false,
                    ];
                }
            }

            // 3. Highway / pedestrian network ways split into 2-point straight segments
            $roadClass = strtolower((string) ($tags['highway'] ?? ''));
            if ($roadClass !== '' && count($nodeIds) >= 2) {
                $footTag = strtolower((string) ($tags['foot'] ?? ''));
                $accessTag = strtolower((string) ($tags['access'] ?? ''));
                $motorTag = strtolower((string) ($tags['motor_vehicle'] ?? ''));
                $onewayTag = strtolower((string) ($tags['oneway'] ?? ''));
                $bridgeTag = strtolower((string) ($tags['bridge'] ?? ''));
                $tunnelTag = strtolower((string) ($tags['tunnel'] ?? ''));
                $layerVal = (int) ($tags['layer'] ?? 0);

                $walkable = !in_array($footTag, ['no', 'use_sidepath'], true)
                    && !($accessTag === 'no' && !in_array($footTag, ['yes', 'permissive', 'designated'], true));

                $drivable = in_array($roadClass, ['living_street', 'residential', 'service', 'unclassified', 'tertiary', 'secondary', 'primary', 'track'], true)
                    && !in_array($motorTag, ['no', 'private'], true)
                    && !in_array($accessTag, ['no', 'private'], true);

                if (!$walkable && !$drivable) {
                    continue;
                }

                $onewayVehicle = 0;
                if (in_array($onewayTag, ['yes', '1', 'true'], true)) {
                    $onewayVehicle = 1;
                } elseif (in_array($onewayTag, ['-1', 'reverse'], true)) {
                    $onewayVehicle = -1;
                }

                $isBridge = ($bridgeTag !== '' && $bridgeTag !== 'no')
                    || ($tunnelTag !== '' && $tunnelTag !== 'no')
                    || $layerVal > 0;

                $walkWeight = match ($roadClass) {
                    'footway', 'pedestrian', 'corridor' => 1.00,
                    'path', 'steps', 'living_street' => 1.05,
                    'track' => 1.10,
                    'service', 'unclassified' => 1.12,
                    'residential', 'tertiary', 'secondary' => 1.18,
                    'primary' => 1.45,
                    default => 1.15,
                };

                $driveWeight = in_array($roadClass, ['service', 'living_street', 'track'], true) ? 1.08 : 1.00;
                $roadName = (string) ($tags['name'] ?? '');

                $countN = count($nodeIds);
                for ($i = 0; $i < $countN - 1; $i++) {
                    $u = (string) $nodeIds[$i];
                    $v = (string) $nodeIds[$i + 1];
                    if ($u === $v || !isset($osmNodes[$u], $osmNodes[$v])) {
                        continue;
                    }
                    $dist = $this->geometryGuard->haversineMeters(
                        $osmNodes[$u]['lat'],
                        $osmNodes[$u]['lng'],
                        $osmNodes[$v]['lat'],
                        $osmNodes[$v]['lng']
                    );
                    if ($dist < 0.05) {
                        continue;
                    }

                    $referencedHighwayNodeIds[$u] = true;
                    $referencedHighwayNodeIds[$v] = true;

                    $rawEdges[] = [
                        'id' => "e_{$wayId}_{$i}",
                        'way_id' => $wayId,
                        'u' => $u,
                        'v' => $v,
                        'distance_m' => round($dist, 3),
                        'walkable' => $walkable,
                        'drivable' => $drivable,
                        'oneway_vehicle' => $onewayVehicle,
                        'is_bridge' => $isBridge,
                        'osm_culvert' => false,
                        'road_class' => $roadClass,
                        'name' => $roadName,
                        'walk_weight' => $walkWeight,
                        'drive_weight' => $driveWeight,
                    ];
                }
            }
        }

        // Mark mapped OSM road/footway segments that cross an OSM waterway line as culvert crossings
        $waterwayBarriers = array_values(array_filter(
            $barriers,
            static fn (array $b): bool => ($b['kind'] ?? '') === 'waterway'
        ));
        foreach ($rawEdges as &$edge) {
            $uCoord = [$osmNodes[$edge['u']]['lng'], $osmNodes[$edge['u']]['lat']];
            $vCoord = [$osmNodes[$edge['v']]['lng'], $osmNodes[$edge['v']]['lat']];
            if ($this->geometryGuard->segmentCrossesBarrier($uCoord, $vCoord, $waterwayBarriers, false)) {
                $edge['osm_culvert'] = true;
            }
        }
        unset($edge);

        // Connected components cleanup on walkable edges: keep main network & drop tiny islands (< 15 nodes)
        $adj = [];
        foreach ($rawEdges as $edge) {
            if (!$edge['walkable']) {
                continue;
            }
            $adj[$edge['u']][] = $edge['v'];
            $adj[$edge['v']][] = $edge['u'];
        }

        $visited = [];
        $components = [];
        foreach (array_keys($referencedHighwayNodeIds) as $startNode) {
            if (isset($visited[$startNode])) {
                continue;
            }
            $queue = [$startNode];
            $visited[$startNode] = true;
            $comp = [];
            $head = 0;
            while ($head < count($queue)) {
                $curr = $queue[$head++];
                $comp[] = $curr;
                foreach ($adj[$curr] ?? [] as $nxt) {
                    if (!isset($visited[$nxt])) {
                        $visited[$nxt] = true;
                        $queue[] = $nxt;
                    }
                }
            }
            $components[] = $comp;
        }

        usort($components, static fn (array $a, array $b): int => count($b) <=> count($a));

        $keptNodeIds = [];
        $removedIslands = 0;
        $removedIslandNodes = 0;
        foreach ($components as $idx => $comp) {
            if ($idx === 0 || count($comp) >= 15) {
                foreach ($comp as $nid) {
                    $keptNodeIds[$nid] = true;
                }
            } else {
                $removedIslands++;
                $removedIslandNodes += count($comp);
            }
        }

        $cleanNodes = [];
        foreach (array_keys($keptNodeIds) as $nid) {
            $cleanNodes[$nid] = [
                'id' => $nid,
                'lat' => round($osmNodes[$nid]['lat'], 7),
                'lng' => round($osmNodes[$nid]['lng'], 7),
            ];
        }

        $cleanEdges = array_values(array_filter(
            $rawEdges,
            static fn (array $e): bool => isset($cleanNodes[$e['u']], $cleanNodes[$e['v']])
        ));

        return [
            'version' => 1,
            'bbox' => self::BBOX,
            'generated_at' => now()->toIso8601String(),
            'stats' => [
                'source' => $sourceTag,
                'node_count' => count($cleanNodes),
                'edge_count' => count($cleanEdges),
                'walkable_edge_count' => count(array_filter($cleanEdges, static fn (array $e): bool => $e['walkable'])),
                'drivable_edge_count' => count(array_filter($cleanEdges, static fn (array $e): bool => $e['drivable'])),
                'building_polygon_count' => count($buildingPolygons),
                'barrier_count' => count($barriers),
                'waterway_count' => count($waterwayBarriers),
                'removed_isolated_islands' => $removedIslands,
                'removed_isolated_nodes' => $removedIslandNodes,
            ],
            'nodes' => $cleanNodes,
            'edges' => $cleanEdges,
            'obstacles' => [
                'buildings' => $buildingPolygons,
                'barriers' => $barriers,
            ],
        ];
    }

    /**
     * Evaluate snap connectivity for all DB buildings & photocopy places inside campus bbox.
     *
     * @param  array<string, array{id: string, lat: float, lng: float}>  $nodes
     * @return array<string, mixed>
     */
    protected function computeConnectivitySummary(array $nodes): array
    {
        if (empty($nodes)) {
            return ['buildings_total' => 0, 'buildings_connected' => 0, 'places_total' => 0, 'places_in_bbox_connected' => 0];
        }

        $bTotal = 0;
        $bConnected = 0;
        if (Schema::hasTable('buildings')) {
            $buildings = Building::whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'latitude', 'longitude']);
            $bTotal = $buildings->count();
            foreach ($buildings as $b) {
                $nearest = $this->geometryGuard->findNearestNodes([(float) $b->longitude, (float) $b->latitude], $nodes, 1, 350.0);
                if (!empty($nearest)) {
                    $bConnected++;
                }
            }
        }

        $pTotal = 0;
        $pInBboxConnected = 0;
        $bbox = self::BBOX;
        if (Schema::hasTable('photocopy_places')) {
            $places = PhotocopyPlace::whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'latitude', 'longitude']);
            $pTotal = $places->count();
            foreach ($places as $p) {
                $lat = (float) $p->latitude;
                $lng = (float) $p->longitude;
                if ($lat >= $bbox['south'] && $lat <= $bbox['north'] && $lng >= $bbox['west'] && $lng <= $bbox['east']) {
                    $nearest = $this->geometryGuard->findNearestNodes([$lng, $lat], $nodes, 1, 350.0);
                    if (!empty($nearest)) {
                        $pInBboxConnected++;
                    }
                }
            }
        }

        return [
            'buildings_total' => $bTotal,
            'buildings_connected' => $bConnected,
            'places_total' => $pTotal,
            'places_in_bbox_connected' => $pInBboxConnected,
        ];
    }

    public function loadFromSnapshotFile(): ?array
    {
        $path = $this->getSnapshotGraphPath();
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) && !empty($decoded['nodes']) ? $decoded : null;
    }

    protected function saveJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $path,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}
