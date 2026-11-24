<?php

namespace App\Services\Routing;

class GeometryGuardService
{
    private const EARTH_RADIUS_M = 6371000.0;

    public function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * Compute bounding box [minLng, minLat, maxLng, maxLat] for coordinate pairs [[lng, lat], ...].
     *
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function computeBbox(array $coords): array
    {
        $minLng = INF;
        $minLat = INF;
        $maxLng = -INF;
        $maxLat = -INF;

        foreach ($coords as $pt) {
            $lng = (float) ($pt[0] ?? 0.0);
            $lat = (float) ($pt[1] ?? 0.0);
            if ($lng < $minLng) $minLng = $lng;
            if ($lng > $maxLng) $maxLng = $lng;
            if ($lat < $minLat) $minLat = $lat;
            if ($lat > $maxLat) $maxLat = $lat;
        }

        return [$minLng, $minLat, $maxLng, $maxLat];
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $a
     * @param  array{0: float, 1: float, 2: float, 3: float}  $b
     */
    public function bboxesOverlap(array $a, array $b, float $padDeg = 0.0): bool
    {
        return !($a[2] + $padDeg < $b[0]
            || $a[0] - $padDeg > $b[2]
            || $a[3] + $padDeg < $b[1]
            || $a[1] - $padDeg > $b[3]);
    }

    /**
     * 2D segment intersection with shared-endpoint exemption within $endpointEps.
     * Points use [lng, lat] format.
     *
     * @param  array{0: float, 1: float}  $a1
     * @param  array{0: float, 1: float}  $a2
     * @param  array{0: float, 1: float}  $b1
     * @param  array{0: float, 1: float}  $b2
     */
    public function segmentsIntersect(
        array $a1,
        array $a2,
        array $b1,
        array $b2,
        bool $ignoreSharedEndpoint = true,
        float $endpointEps = 1e-7
    ): bool {
        if ($ignoreSharedEndpoint) {
            if ($this->pointsClose($a1, $b1, $endpointEps)
                || $this->pointsClose($a1, $b2, $endpointEps)
                || $this->pointsClose($a2, $b1, $endpointEps)
                || $this->pointsClose($a2, $b2, $endpointEps)) {
                return false;
            }
        }

        $ax1 = (float) $a1[0]; $ay1 = (float) $a1[1];
        $ax2 = (float) $a2[0]; $ay2 = (float) $a2[1];
        $bx1 = (float) $b1[0]; $by1 = (float) $b1[1];
        $bx2 = (float) $b2[0]; $by2 = (float) $b2[1];

        if (max($ax1, $ax2) < min($bx1, $bx2) - 1e-11
            || max($bx1, $bx2) < min($ax1, $ax2) - 1e-11
            || max($ay1, $ay2) < min($by1, $by2) - 1e-11
            || max($by1, $by2) < min($ay1, $ay2) - 1e-11) {
            return false;
        }

        $o1 = $this->orient($ax1, $ay1, $ax2, $ay2, $bx1, $by1);
        $o2 = $this->orient($ax1, $ay1, $ax2, $ay2, $bx2, $by2);
        $o3 = $this->orient($bx1, $by1, $bx2, $by2, $ax1, $ay1);
        $o4 = $this->orient($bx1, $by1, $bx2, $by2, $ax2, $ay2);

        $tol = 1e-13;
        if (($o1 > $tol && $o2 < -$tol || $o1 < -$tol && $o2 > $tol)
            && ($o3 > $tol && $o4 < -$tol || $o3 < -$tol && $o4 > $tol)) {
            return true;
        }

        if (abs($o1) <= $tol && $this->onSegment($ax1, $ay1, $bx1, $by1, $ax2, $ay2)) return true;
        if (abs($o2) <= $tol && $this->onSegment($ax1, $ay1, $bx2, $by2, $ax2, $ay2)) return true;
        if (abs($o3) <= $tol && $this->onSegment($bx1, $by1, $ax1, $ay1, $bx2, $by2)) return true;
        if (abs($o4) <= $tol && $this->onSegment($bx1, $by1, $ax2, $ay2, $bx2, $by2)) return true;

        return false;
    }

    /**
     * Ray-casting point-in-polygon check for [lng, lat] point and closed/open ring.
     *
     * @param  array{0: float, 1: float}  $point
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    public function pointInPolygon(array $point, array $ring): bool
    {
        $n = count($ring);
        if ($n < 3) {
            return false;
        }

        $x = (float) $point[0];
        $y = (float) $point[1];
        $inside = false;

        $j = $n - 1;
        for ($i = 0; $i < $n; $i++) {
            $xi = (float) $ring[$i][0];
            $yi = (float) $ring[$i][1];
            $xj = (float) $ring[$j][0];
            $yj = (float) $ring[$j][1];

            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-15) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
            $j = $i;
        }

        return $inside;
    }

    /**
     * Check whether a segment intersects any wall of a polygon or runs through its interior.
     *
     * @param  array{0: float, 1: float}  $start
     * @param  array{0: float, 1: float}  $end
     * @param  array<string, mixed>  $polygon  ['bbox' => ..., 'ring' => ...]
     */
    public function segmentCrossesPolygonInterior(array $start, array $end, array $polygon): bool
    {
        $ring = $polygon['ring'] ?? [];
        if (count($ring) < 3) {
            return false;
        }

        $segBbox = $this->computeBbox([$start, $end]);
        $polyBbox = $polygon['bbox'] ?? $this->computeBbox($ring);
        if (!$this->bboxesOverlap($segBbox, $polyBbox, 1e-6)) {
            return false;
        }

        $mid = [($start[0] + $end[0]) * 0.5, ($start[1] + $end[1]) * 0.5];
        if ($this->pointInPolygon($mid, $ring)) {
            return true;
        }

        $edgeCount = count($ring);
        for ($i = 0; $i < $edgeCount - 1; $i++) {
            if ($this->segmentsIntersect($start, $end, $ring[$i], $ring[$i + 1], true, 1e-6)) {
                return true;
            }
        }
        // Check closing segment if not already explicitly closed
        if (!$this->pointsClose($ring[0], $ring[$edgeCount - 1], 1e-8)) {
            if ($this->segmentsIntersect($start, $end, $ring[$edgeCount - 1], $ring[0], true, 1e-6)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether segment intersects any linear barrier polyline (ditch, wall, fence, custom barrier).
     *
     * @param  array{0: float, 1: float}  $start
     * @param  array{0: float, 1: float}  $end
     * @param  array<int, array<string, mixed>>  $barriers
     * @param  bool  $customOnly
     */
    public function segmentCrossesBarrier(array $start, array $end, array $barriers, bool $customOnly = false): bool
    {
        $segBbox = $this->computeBbox([$start, $end]);

        foreach ($barriers as $barrier) {
            if ($customOnly && empty($barrier['is_custom'])) {
                continue;
            }
            $coords = $barrier['coords'] ?? [];
            if (count($coords) < 2) {
                continue;
            }
            $bBox = $barrier['bbox'] ?? $this->computeBbox($coords);
            if (!$this->bboxesOverlap($segBbox, $bBox, 1e-6)) {
                continue;
            }

            $n = count($coords);
            for ($i = 0; $i < $n - 1; $i++) {
                if ($this->segmentsIntersect($start, $end, $coords[$i], $coords[$i + 1], true, 1e-7)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Comprehensive check: true if segment crosses another building polygon or crosses a barrier line.
     *
     * @param  array{0: float, 1: float}  $start
     * @param  array{0: float, 1: float}  $end
     * @param  array{buildings?: array, barriers?: array}  $obstacles
     * @param  array<int, string>  $allowedBuildingIds
     */
    public function segmentCrossesObstacle(
        array $start,
        array $end,
        array $obstacles,
        array $allowedBuildingIds = [],
        bool $allowOsmWaterway = false,
        bool $allowAllBarriers = false
    ): bool {
        $allowedMap = !empty($allowedBuildingIds) ? array_flip($allowedBuildingIds) : [];

        foreach ($obstacles['buildings'] ?? [] as $poly) {
            $polyId = (string) ($poly['id'] ?? '');
            if ($polyId !== '' && isset($allowedMap[$polyId])) {
                continue;
            }
            if ($this->segmentCrossesPolygonInterior($start, $end, $poly)) {
                return true;
            }
        }

        if ($allowAllBarriers) {
            return false;
        }

        $barriers = $obstacles['barriers'] ?? [];
        if ($allowOsmWaterway) {
            return $this->segmentCrossesBarrier($start, $end, $barriers, true);
        }

        return $this->segmentCrossesBarrier($start, $end, $barriers, false);
    }

    /**
     * Determine a legal, unobstructed entrance point ([lng, lat]) for an endpoint.
     *
     * @param  array{0: float, 1: float}  $targetPoint  [lng, lat]
     * @param  array{0: float, 1: float}|null  $manualEntrance  [lng, lat]
     * @param  array<int, array<string, mixed>>  $buildingPolygons
     * @param  array<int, array<string, mixed>>  $barriers
     * @param  array<string, array{id: string, lat: float, lng: float}>  $graphNodes
     * @return array{lng: float, lat: float, own_building_id: ?string, is_manual_entrance: bool, shifted: bool}
     */
    public function computeSafeEntrancePoint(
        array $targetPoint,
        ?array $manualEntrance,
        array $buildingPolygons,
        array $barriers,
        array $graphNodes
    ): array {
        $ownBuilding = $this->findContainingBuildingPolygon($targetPoint, $buildingPolygons);
        $ownId = $ownBuilding ? (string) ($ownBuilding['id'] ?? '') : null;

        if ($manualEntrance !== null) {
            return [
                'lng' => (float) $manualEntrance[0],
                'lat' => (float) $manualEntrance[1],
                'own_building_id' => $ownId,
                'is_manual_entrance' => true,
                'shifted' => !$this->pointsClose($targetPoint, $manualEntrance, 1e-6),
            ];
        }

        if (!$ownBuilding) {
            return [
                'lng' => (float) $targetPoint[0],
                'lat' => (float) $targetPoint[1],
                'own_building_id' => null,
                'is_manual_entrance' => false,
                'shifted' => false,
            ];
        }

        // Project target inside building to closest outward edge offsets (~2.0m outside walls)
        $ring = $ownBuilding['ring'] ?? [];
        $candidates = [];
        $count = count($ring);
        $center = $this->polygonCentroid($ring);

        for ($i = 0; $i < $count - 1; $i++) {
            $p1 = $ring[$i];
            $p2 = $ring[$i + 1];
            foreach ([0.25, 0.5, 0.75] as $t) {
                $proj = [
                    $p1[0] + $t * ($p2[0] - $p1[0]),
                    $p1[1] + $t * ($p2[1] - $p1[1]),
                ];
                $shifted = $this->pushPointOutwardFromRing($proj, $p1, $p2, $center, $ring, 2.2);
                if (!$this->pointInPolygon($shifted, $ring)) {
                    $candidates[] = $shifted;
                }
            }
        }

        if (empty($candidates)) {
            return [
                'lng' => (float) $targetPoint[0],
                'lat' => (float) $targetPoint[1],
                'own_building_id' => $ownId,
                'is_manual_entrance' => false,
                'shifted' => false,
            ];
        }

        // Collect nearby graph nodes (within 180m) for fast obstacle-free evaluation
        $nearNodes = $this->findNearestNodes($targetPoint, $graphNodes, 18, 220.0);
        $obstacles = ['buildings' => $buildingPolygons, 'barriers' => $barriers];

        $bestPoint = $candidates[0];
        $bestScore = INF;

        foreach ($candidates as $cand) {
            $centroidDist = $this->haversineMeters($targetPoint[1], $targetPoint[0], $cand[1], $cand[0]);
            $minRoadCost = INF;

            foreach ($nearNodes as $node) {
                $nc = [$node['lng'], $node['lat']];
                $d = $this->haversineMeters($cand[1], $cand[0], $nc[1], $nc[0]);
                if ($d >= $minRoadCost) {
                    continue;
                }
                if (!$this->segmentCrossesObstacle($cand, $nc, $obstacles, $ownId ? [$ownId] : [])) {
                    $minRoadCost = $d;
                }
            }

            // Fallback: if all direct lines blocked, score by raw road distance + penalty
            if ($minRoadCost === INF && !empty($nearNodes)) {
                foreach ($nearNodes as $node) {
                    $d = $this->haversineMeters($cand[1], $cand[0], $node['lat'], $node['lng']);
                    $minRoadCost = min($minRoadCost, $d + 150.0);
                }
            }

            $totalScore = $minRoadCost + ($centroidDist * 0.25);
            if ($totalScore < $bestScore) {
                $bestScore = $totalScore;
                $bestPoint = $cand;
            }
        }

        return [
            'lng' => (float) $bestPoint[0],
            'lat' => (float) $bestPoint[1],
            'own_building_id' => $ownId,
            'is_manual_entrance' => false,
            'shifted' => true,
        ];
    }

    /**
     * Build an obstacle-free last-mile path between an endpoint [lng, lat] and a snapped graph node [lng, lat].
     * Uses direct line-of-sight when unobstructed, otherwise solves a local visibility graph around nearby polygon/barrier corners.
     *
     * @param  array{0: float, 1: float}  $endpoint
     * @param  array{0: float, 1: float}  $snapCoord
     * @param  array{buildings?: array, barriers?: array}  $obstacles
     * @param  array<int, string>  $allowedBuildingIds
     * @return array<int, array{0: float, 1: float}>|null
     */
    public function buildLastMileConnector(
        array $endpoint,
        array $snapCoord,
        array $obstacles,
        array $allowedBuildingIds = []
    ): ?array {
        if ($this->pointsClose($endpoint, $snapCoord, 1e-6)) {
            return [$endpoint, $snapCoord];
        }

        if (!$this->segmentCrossesObstacle($endpoint, $snapCoord, $obstacles, $allowedBuildingIds)) {
            return [$endpoint, $snapCoord];
        }

        // Gather buffered corner waypoints from obstacles near this corridor
        $corridorBbox = $this->computeBbox([$endpoint, $snapCoord]);
        $padDeg = 0.0005; // ~55m around last-mile corridor
        $waypoints = [$endpoint, $snapCoord];

        foreach ($obstacles['buildings'] ?? [] as $poly) {
            $polyBbox = $poly['bbox'] ?? $this->computeBbox($poly['ring'] ?? []);
            if (!$this->bboxesOverlap($corridorBbox, $polyBbox, $padDeg)) {
                continue;
            }
            $ring = $poly['ring'] ?? [];
            $cnt = count($ring);
            if ($cnt < 3) continue;
            $center = $this->polygonCentroid($ring);
            for ($i = 0; $i < $cnt; $i++) {
                $v = $ring[$i];
                if ($i === $cnt - 1 && $this->pointsClose($v, $ring[0], 1e-8)) {
                    continue;
                }
                $buffered = $this->pushPointAwayFrom($v, $center, 2.0);
                if (!$this->isPointInsideAnyBuilding($buffered, $obstacles['buildings'] ?? [])) {
                    $waypoints[] = $buffered;
                }
                if (count($waypoints) >= 42) {
                    break 2;
                }
            }
        }

        foreach ($obstacles['barriers'] ?? [] as $barrier) {
            $coords = $barrier['coords'] ?? [];
            if (count($coords) < 2) continue;
            $bBox = $barrier['bbox'] ?? $this->computeBbox($coords);
            if (!$this->bboxesOverlap($corridorBbox, $bBox, $padDeg)) {
                continue;
            }
            $n = count($coords);
            $startPt = $coords[0];
            $nextPt = $coords[1];
            $endPt = $coords[$n - 1];
            $prevPt = $coords[$n - 2];
            foreach ([[$startPt, $nextPt], [$endPt, $prevPt]] as [$tip, $inner]) {
                $ext = $this->extendPastEndpoint($tip, $inner, 2.2);
                if (!$this->isPointInsideAnyBuilding($ext, $obstacles['buildings'] ?? [])) {
                    $waypoints[] = $ext;
                }
            }
            if (count($waypoints) >= 50) {
                break;
            }
        }

        $nPts = count($waypoints);
        if ($nPts <= 2) {
            return null;
        }

        // Local Dijkstra over unobstructed visibility segments
        $dist = array_fill(0, $nPts, INF);
        $prev = array_fill(0, $nPts, -1);
        $visited = array_fill(0, $nPts, false);
        $dist[0] = 0.0;

        for ($step = 0; $step < $nPts; $step++) {
            $u = -1;
            $best = INF;
            for ($i = 0; $i < $nPts; $i++) {
                if (!$visited[$i] && $dist[$i] < $best) {
                    $best = $dist[$i];
                    $u = $i;
                }
            }
            if ($u === -1 || $u === 1 || $best === INF) {
                break;
            }
            $visited[$u] = true;

            for ($v = 0; $v < $nPts; $v++) {
                if ($visited[$v] || $u === $v) {
                    continue;
                }
                $d = $this->haversineMeters(
                    $waypoints[$u][1],
                    $waypoints[$u][0],
                    $waypoints[$v][1],
                    $waypoints[$v][0]
                );
                if ($d > 350.0 || $dist[$u] + $d >= $dist[$v]) {
                    continue;
                }
                if (!$this->segmentCrossesObstacle($waypoints[$u], $waypoints[$v], $obstacles, $allowedBuildingIds)) {
                    $dist[$v] = $dist[$u] + $d;
                    $prev[$v] = $u;
                }
            }
        }

        if ($dist[1] === INF) {
            return null;
        }

        $path = [];
        $cur = 1;
        while ($cur !== -1) {
            array_unshift($path, $waypoints[$cur]);
            $cur = $prev[$cur];
        }

        return count($path) >= 2 ? $path : null;
    }

    /**
     * Audit complete route segments against building polygons & barriers.
     *
     * @param  array<int, array<string, mixed>>  $mainEdges  [['id' => ..., 'uCoord' => [lng, lat], 'vCoord' => [lng, lat], 'is_bridge' => bool, 'osm_culvert' => bool], ...]
     * @param  array<int, array<int, array{0: float, 1: float}>>  $accessPolylines
     * @param  array{buildings?: array, barriers?: array}  $obstacles
     * @param  array<int, string>  $allowedBuildingIds
     * @return array{building_intersections: int, barrier_intersections: int, violating_edge_ids: array<int, string>, verified: bool}
     */
    public function auditRouteGeometry(
        array $mainEdges,
        array $accessPolylines,
        array $obstacles,
        array $allowedBuildingIds = []
    ): array {
        $buildingHits = 0;
        $barrierHits = 0;
        $violatingEdges = [];

        foreach ($mainEdges as $edge) {
            $a = $edge['uCoord'];
            $b = $edge['vCoord'];
            $isBridge = !empty($edge['is_bridge']);
            $allowOsmWaterway = !empty($edge['osm_culvert']) || $isBridge;

            $hitBuilding = false;
            foreach ($obstacles['buildings'] ?? [] as $poly) {
                $pid = (string) ($poly['id'] ?? '');
                if ($pid !== '' && in_array($pid, $allowedBuildingIds, true)) {
                    continue;
                }
                if ($this->segmentCrossesPolygonInterior($a, $b, $poly)) {
                    $hitBuilding = true;
                    break;
                }
            }

            $hitBarrier = false;
            if (!$isBridge) {
                $hitBarrier = $this->segmentCrossesBarrier(
                    $a,
                    $b,
                    $obstacles['barriers'] ?? [],
                    $allowOsmWaterway
                );
            }

            if ($hitBuilding) {
                $buildingHits++;
            }
            if ($hitBarrier) {
                $barrierHits++;
            }
            if (($hitBuilding || $hitBarrier) && !empty($edge['id'])) {
                $violatingEdges[] = (string) $edge['id'];
            }
        }

        foreach ($accessPolylines as $line) {
            $cnt = count($line);
            for ($i = 0; $i < $cnt - 1; $i++) {
                $a = $line[$i];
                $b = $line[$i + 1];

                foreach ($obstacles['buildings'] ?? [] as $poly) {
                    $pid = (string) ($poly['id'] ?? '');
                    if ($pid !== '' && in_array($pid, $allowedBuildingIds, true)) {
                        continue;
                    }
                    if ($this->segmentCrossesPolygonInterior($a, $b, $poly)) {
                        $buildingHits++;
                        break;
                    }
                }

                if ($this->segmentCrossesBarrier($a, $b, $obstacles['barriers'] ?? [], false)) {
                    $barrierHits++;
                }
            }
        }

        return [
            'building_intersections' => $buildingHits,
            'barrier_intersections' => $barrierHits,
            'violating_edge_ids' => array_values(array_unique($violatingEdges)),
            'verified' => ($buildingHits === 0 && $barrierHits === 0),
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  array<int, array<string, mixed>>  $buildingPolygons
     * @return array<string, mixed>|null
     */
    public function findContainingBuildingPolygon(array $point, array $buildingPolygons): ?array
    {
        foreach ($buildingPolygons as $poly) {
            $bbox = $poly['bbox'] ?? null;
            if ($bbox && ($point[0] < $bbox[0] || $point[0] > $bbox[2] || $point[1] < $bbox[1] || $point[1] > $bbox[3])) {
                continue;
            }
            if ($this->pointInPolygon($point, $poly['ring'] ?? [])) {
                return $poly;
            }
        }

        return null;
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  array<string, array{id: string, lat: float, lng: float}>  $graphNodes
     * @return array<int, array{id: string, lat: float, lng: float, distance_m: float}>
     */
    public function findNearestNodes(array $point, array $graphNodes, int $limit = 20, float $maxMeters = 300.0): array
    {
        $latDegPad = $maxMeters / 111000.0;
        $lngDegPad = $maxMeters / 111000.0;
        $results = [];

        foreach ($graphNodes as $node) {
            if (abs($node['lat'] - $point[1]) > $latDegPad || abs($node['lng'] - $point[0]) > $lngDegPad) {
                continue;
            }
            $d = $this->haversineMeters($point[1], $point[0], $node['lat'], $node['lng']);
            if ($d <= $maxMeters) {
                $results[] = [
                    'id' => (string) $node['id'],
                    'lat' => (float) $node['lat'],
                    'lng' => (float) $node['lng'],
                    'distance_m' => $d,
                ];
            }
        }

        usort($results, static fn (array $x, array $y): int => $x['distance_m'] <=> $y['distance_m']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Bearing in degrees (0 = North, 90 = East, 180 = South, 270 = West).
     *
     * @param  array{0: float, 1: float}  $from  [lng, lat]
     * @param  array{0: float, 1: float}  $to    [lng, lat]
     */
    public function bearingDegrees(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLng = deg2rad($to[0] - $from[0]);
        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    private function orient(float $ax, float $ay, float $bx, float $by, float $cx, float $cy): float
    {
        return ($bx - $ax) * ($cy - $ay) - ($by - $ay) * ($cx - $ax);
    }

    private function onSegment(float $px, float $py, float $qx, float $qy, float $rx, float $ry): bool
    {
        return $qx <= max($px, $rx) + 1e-11
            && $qx >= min($px, $rx) - 1e-11
            && $qy <= max($py, $ry) + 1e-11
            && $qy >= min($py, $ry) - 1e-11;
    }

    private function pointsClose(array $a, array $b, float $eps): bool
    {
        return abs((float) $a[0] - (float) $b[0]) <= $eps
            && abs((float) $a[1] - (float) $b[1]) <= $eps;
    }

    private function polygonCentroid(array $ring): array
    {
        $cnt = count($ring);
        if ($cnt === 0) return [0.0, 0.0];
        $sumLng = 0.0;
        $sumLat = 0.0;
        $used = 0;
        for ($i = 0; $i < $cnt; $i++) {
            if ($i === $cnt - 1 && $cnt > 1 && $this->pointsClose($ring[$i], $ring[0], 1e-8)) {
                continue;
            }
            $sumLng += $ring[$i][0];
            $sumLat += $ring[$i][1];
            $used++;
        }

        return $used > 0 ? [$sumLng / $used, $sumLat / $used] : [$ring[0][0], $ring[0][1]];
    }

    private function pushPointOutwardFromRing(array $proj, array $p1, array $p2, array $center, array $ring, float $meters): array
    {
        $dx = $p2[0] - $p1[0];
        $dy = $p2[1] - $p1[1];
        $len = hypot($dx, $dy) ?: 1e-9;
        // Two unit normals
        $n1 = [-$dy / $len, $dx / $len];
        $n2 = [$dy / $len, -$dx / $len];
        $degOffset = $meters / 111000.0;

        $cand1 = [$proj[0] + $n1[0] * $degOffset, $proj[1] + $n1[1] * $degOffset];
        $cand2 = [$proj[0] + $n2[0] * $degOffset, $proj[1] + $n2[1] * $degOffset];

        if (!$this->pointInPolygon($cand1, $ring) && $this->pointInPolygon($cand2, $ring)) {
            return $cand1;
        }
        if (!$this->pointInPolygon($cand2, $ring) && $this->pointInPolygon($cand1, $ring)) {
            return $cand2;
        }

        // Otherwise pick the one farther from centroid
        $d1 = hypot($cand1[0] - $center[0], $cand1[1] - $center[1]);
        $d2 = hypot($cand2[0] - $center[0], $cand2[1] - $center[1]);

        return $d1 >= $d2 ? $cand1 : $cand2;
    }

    private function pushPointAwayFrom(array $vertex, array $center, float $meters): array
    {
        $dx = $vertex[0] - $center[0];
        $dy = $vertex[1] - $center[1];
        $len = hypot($dx, $dy) ?: 1e-9;
        $degOffset = $meters / 111000.0;

        return [$vertex[0] + ($dx / $len) * $degOffset, $vertex[1] + ($dy / $len) * $degOffset];
    }

    private function extendPastEndpoint(array $tip, array $inner, float $meters): array
    {
        $dx = $tip[0] - $inner[0];
        $dy = $tip[1] - $inner[1];
        $len = hypot($dx, $dy) ?: 1e-9;
        $degOffset = $meters / 111000.0;

        return [$tip[0] + ($dx / $len) * $degOffset, $tip[1] + ($dy / $len) * $degOffset];
    }

    /**
     * Orthogonal projection of $point [lng, lat] onto segment [$a, $b] scaled by cos(lat) for meter accuracy.
     *
     * @param  array{0: float, 1: float}  $point
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @return array{point: array{0: float, 1: float}, t: float, distance_m: float}
     */
    public function projectPointOnSegment(array $point, array $a, array $b): array
    {
        $cosLat = cos(deg2rad((float) $point[1]));
        if (abs($cosLat) < 1e-6) {
            $cosLat = 1e-6;
        }

        $ax = (float) $a[0] * $cosLat;
        $ay = (float) $a[1];
        $bx = (float) $b[0] * $cosLat;
        $by = (float) $b[1];
        $px = (float) $point[0] * $cosLat;
        $py = (float) $point[1];

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lenSq = ($dx * $dx) + ($dy * $dy);

        $t = $lenSq > 1e-18
            ? max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $lenSq))
            : 0.0;

        $projLng = (float) $a[0] + $t * ((float) $b[0] - (float) $a[0]);
        $projLat = (float) $a[1] + $t * ((float) $b[1] - (float) $a[1]);
        $projPt = [$projLng, $projLat];

        return [
            'point' => $projPt,
            't' => $t,
            'distance_m' => $this->haversineMeters((float) $point[1], (float) $point[0], $projLat, $projLng),
        ];
    }

    /**
     * Total actual length along a GeoJSON coordinate polyline in meters.
     *
     * @param  array<int, array{0: float, 1: float}>  $coords
     */
    public function polylineLengthMeters(array $coords): float
    {
        $cnt = count($coords);
        if ($cnt < 2) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $cnt - 1; $i++) {
            $sum += $this->haversineMeters(
                (float) $coords[$i][1],
                (float) $coords[$i][0],
                (float) $coords[$i + 1][1],
                (float) $coords[$i + 1][0]
            );
        }

        return $sum;
    }

    private function isPointInsideAnyBuilding(array $point, array $buildingPolygons): bool
    {
        return $this->findContainingBuildingPolygon($point, $buildingPolygons) !== null;
    }
}
