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
        Log::info('[USUClassMap Sync] Menerima data sinkronisasi jadwal dari Chrome Extension:', [
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
}
