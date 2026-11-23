<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SparqlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleApiController extends Controller
{
    protected SparqlService $sparqlService;

    public function __construct(SparqlService $sparqlService)
    {
        $this->sparqlService = $sparqlService;
    }

    /**
     * Search schedules via SPARQL with text filter & day filter.
     *
     * @param Request $request Query parameters: 'q' (keyword) and 'day' (Senin-Sabtu)
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $keyword = $request->query('q') ?? $request->query('keyword');
        $day = $request->query('day');

        $result = $this->sparqlService->searchSchedules($keyword, $day);

        return response()->json($result);
    }

    /**
     * Receive and sync schedules exported from Chrome Extension (satu.usu.ac.id).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Log incoming sync payload for audit & diagnostic
        Log::info('[USUStarterMap Sync] Menerima data sinkronisasi jadwal dari Chrome Extension:', [
            'ip' => $request->ip(),
            'total_items' => count($request->input('schedules', [])),
            'payload' => $payload,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data jadwal berhasil diterima dan disinkronkan!',
            'synced_at' => now()->toIso8601String(),
            'count' => count($request->input('schedules', [])),
            'data' => $payload,
        ], 200);
    }

    /**
     * Endpoint SPARQL 1.1 Konsep 1: REGEX Pattern Search.
     * GET /api/sparql/regex-search?pattern=...
     */
    public function regexSearch(Request $request): JsonResponse
    {
        $pattern = $request->query('pattern', 'literacy');
        $limit = (int) $request->query('limit', 25);

        $result = $this->sparqlService->searchByRegex($pattern, $limit);

        return response()->json($result);
    }

    /**
     * Endpoint SPARQL 1.1 Konsep 2: YES/NO Query (ASK).
     * GET /api/sparql/check-room?room=...&day=...&start_time=...
     */
    public function checkRoom(Request $request): JsonResponse
    {
        $room = $request->query('room', '');
        $day = $request->query('day', 'Senin');
        $startTime = $request->query('start_time');

        if (empty($room)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parameter "room" wajib diisi.',
            ], 422);
        }

        $exists = $this->sparqlService->checkRoomOccupiedAsk($room, $day, $startTime);

        return response()->json([
            'status'   => 'success',
            'concept'  => 'SPARQL ASK (YES/NO Query)',
            'source'   => 'SPARQL ASK (Apache Jena Fuseki)',
            'room'     => $room,
            'day'      => $day,
            'time'     => $startTime,
            'exists'   => $exists,
            'message'  => $exists 
                ? "Ruangan '{$room}' memiliki jadwal aktif pada hari {$day}" . ($startTime ? " jam {$startTime}" : "")
                : "Ruangan '{$room}' TIDAK terpakai pada hari {$day}" . ($startTime ? " jam {$startTime}" : ""),
        ]);
    }

    /**
     * Endpoint SPARQL 1.1 Konsep 3: AS (COUNT Aggregation & BIND Assignment).
     * GET /api/sparql/building-stats
     */
    public function buildingStats(Request $request): JsonResponse
    {
        $day = $request->query('day');
        $stats = $this->sparqlService->getBuildingStatsAs();
        $sampleBind = $this->sparqlService->getSchedulesWithDurationAs($day, 5);

        return response()->json([
            'status'             => 'success',
            'concept'            => 'SPARQL AS (COUNT Aggregation & BIND Assignment)',
            'source'             => 'SPARQL 1.1 AS (Apache Jena Fuseki)',
            'total_buildings'    => count($stats),
            'building_schedules' => $stats,
            'sample_duration_as' => $sampleBind,
        ]);
    }
}
