<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Search\QueryIntentParserService;
use App\Services\Search\SmartPhotocopySearchService;
use App\Services\Search\SpatialLandmarkResolverService;
use App\Services\SparqlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartSearchApiController extends Controller
{
    public function __construct(
        protected QueryIntentParserService $intentParser,
        protected SmartPhotocopySearchService $photocopySearch,
        protected SpatialLandmarkResolverService $spatialResolver,
        protected SparqlService $sparqlService
    ) {
    }

    /**
     * Unified Smart Search Endpoint:
     * GET /api/smart-search?q=...&lat=...&lng=...&day=...&scope=all|schedule|building|fotocopy
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $day = $request->filled('day') ? trim((string) $request->input('day')) : null;
        $scope = strtolower(trim((string) $request->input('scope', 'all')));
        if (!in_array($scope, ['all', 'schedule', 'building', 'fotocopy'], true)) {
            $scope = 'all';
        }

        $lat = $request->filled('lat') && is_numeric($request->input('lat')) ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') && is_numeric($request->input('lng')) ? (float) $request->input('lng') : null;
        $limit = max(1, min(30, (int) $request->input('limit', 12)));

        if ($q === '' && $day === null) {
            return response()->json([
                'status'        => 'success',
                'query'         => $q,
                'intent'        => 'empty',
                'parsed'        => null,
                'advisory_note' => null,
                'fotocopy'      => null,
                'spatial'       => null,
                'schedules'     => ['count' => 0, 'source' => null, 'data' => []],
                'cards'         => [],
                'actions'       => [],
            ]);
        }

        $parsed = $this->intentParser->parse($q, ['day' => $day]);
        $primaryIntent = $parsed['primary_intent'];
        $secondaryIntents = $parsed['secondary_intents'] ?? [];

        $runFotocopy = ($scope === 'fotocopy')
            || ($scope === 'all' && ($primaryIntent === 'smart_fotocopy_search' || in_array('smart_fotocopy_search', $secondaryIntents, true)));

        $runSpatial = ($scope === 'building')
            || ($scope === 'all' && ($primaryIntent === 'spatial_building_finder' || in_array('spatial_building_finder', $secondaryIntents, true)));

        $runSchedule = ($scope === 'schedule')
            || ($scope === 'all' && ($primaryIntent === 'schedule_search' || in_array('schedule_search', $secondaryIntents, true) || ($day !== null && !$runFotocopy && !$runSpatial)));

        // If forced into scope=all with route_navigation or general_campus_qa, populate relevant domain data
        if ($scope === 'all' && !$runFotocopy && !$runSpatial && !$runSchedule) {
            if (!empty($parsed['spatial_constraints']) || !empty($parsed['mentioned_buildings'])) {
                $runSpatial = true;
            } else {
                $runSchedule = true;
            }
        }

        $fotocopyResult = null;
        $spatialResult = null;
        $schedulesResult = ['count' => 0, 'source' => null, 'data' => []];
        $advisoryNotes = [];
        $cards = [];
        $actions = [];

        if ($runFotocopy) {
            $fotocopyResult = $this->photocopySearch->search($q, [
                'parsed'    => $parsed,
                'lat'       => $lat,
                'lng'       => $lng,
                'sort'      => $request->input('sort'),
                'open_now'  => $request->boolean('open_now'),
                'max_price' => $request->input('max_price'),
                'limit'     => $limit,
            ]);
            if (!empty($fotocopyResult['advisory_note'])) {
                $advisoryNotes[] = $fotocopyResult['advisory_note'];
            }
            foreach ($fotocopyResult['results'] ?? [] as $item) {
                $cards[] = [
                    'type' => 'fotocopy_item',
                    'data' => $item,
                ];
            }
            if (!empty($fotocopyResult['results'][0]['actions'])) {
                $firstActions = $fotocopyResult['results'][0]['actions'];
                $actions[] = ['label' => 'Pesan di Toko Teratas', 'url' => $firstActions['order_url'], 'type' => 'order'];
                $actions[] = ['label' => 'Rute GPS ke Toko', 'url' => $firstActions['route_url'], 'type' => 'route'];
            }
        }

        if ($runSpatial) {
            $spatialResult = $this->spatialResolver->resolve($q, [
                'parsed' => $parsed,
                'day'    => $day,
                'limit'  => min(5, $limit),
            ]);
            if (!empty($spatialResult['advisory_note'])) {
                $advisoryNotes[] = $spatialResult['advisory_note'];
            }
            foreach ($spatialResult['candidates'] ?? [] as $cand) {
                $cards[] = [
                    'type' => 'spatial_building',
                    'data' => $cand,
                ];
            }
            if (!empty($spatialResult['candidates'][0]['actions'])) {
                $firstAct = $spatialResult['candidates'][0]['actions'];
                $actions[] = ['label' => 'Lihat Kandidat Utama di Peta', 'url' => $firstAct['focus_url'], 'type' => 'focus'];
                $actions[] = ['label' => 'Rute GPS ke Gedung', 'url' => $firstAct['route_url'], 'type' => 'route'];
            }
        }

        if ($runSchedule) {
            $scheduleKeyword = !empty($parsed['course_terms'])
                ? implode(' ', $parsed['course_terms'])
                : ($q !== '' ? $q : '');

            if ($scheduleKeyword === '' && !empty($parsed['mentioned_buildings'])) {
                $scheduleKeyword = (string) $parsed['mentioned_buildings'][0]['code'];
            }

            $res = $this->sparqlService->searchSchedules($scheduleKeyword, $parsed['day'] ?? $day);
            $schedItems = array_slice($res['data'] ?? [], 0, $limit);
            $schedulesResult = [
                'count'  => count($res['data'] ?? []),
                'source' => $res['source'] ?? 'SPARQL',
                'data'   => $schedItems,
            ];
            foreach (array_slice($schedItems, 0, 8) as $s) {
                $cards[] = [
                    'type' => 'schedule',
                    'data' => $s,
                ];
            }
        }

        return response()->json([
            'status'        => 'success',
            'query'         => $q,
            'scope'         => $scope,
            'intent'        => $primaryIntent,
            'parsed'        => $parsed,
            'advisory_note' => !empty($advisoryNotes) ? implode(' ', $advisoryNotes) : null,
            'fotocopy'      => $fotocopyResult,
            'spatial'       => $spatialResult,
            'schedules'     => $schedulesResult,
            'cards'         => $cards,
            'actions'       => $actions,
        ]);
    }

    /**
     * Dedicated Smart Photocopy & ATK Search Endpoint:
     * GET /api/fotocopy/smart-search?q=...&lat=...&lng=...&sort=...&open_now=...&max_price=...
     */
    public function fotocopy(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $lat = $request->filled('lat') && is_numeric($request->input('lat')) ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') && is_numeric($request->input('lng')) ? (float) $request->input('lng') : null;
        $sort = $request->filled('sort') ? trim((string) $request->input('sort')) : null;
        $openNow = $request->boolean('open_now');
        $maxPrice = $request->filled('max_price') && is_numeric($request->input('max_price'))
            ? (int) $request->input('max_price')
            : null;
        $limit = max(1, min(40, (int) $request->input('limit', 18)));

        $result = $this->photocopySearch->search($q, [
            'lat'       => $lat,
            'lng'       => $lng,
            'sort'      => $sort,
            'open_now'  => $openNow,
            'max_price' => $maxPrice,
            'limit'     => $limit,
        ]);

        return response()->json($result);
    }
}
