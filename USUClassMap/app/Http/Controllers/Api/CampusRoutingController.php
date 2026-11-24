<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\PhotocopyPlace;
use App\Services\Routing\CampusRoutingService;
use App\Services\Routing\ExternalRoutingUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampusRoutingController extends Controller
{
    public function __construct(
        protected CampusRoutingService $routingService
    ) {}

    /**
     * Compute obstacle-aware pedestrian/vehicle route between campus points or entities.
     */
    public function route(Request $request): JsonResponse
    {
        $this->hydrateCompactEndpoint($request, 'origin', 'origin');
        $this->hydrateCompactEndpoint($request, 'destination', 'dest');

        $validated = $request->validate([
            'origin_lat' => 'nullable|numeric|between:-90,90',
            'origin_lng' => 'nullable|numeric|between:-180,180',
            'origin_type' => 'nullable|in:building,fotocopy,point',
            'origin_id' => 'nullable|integer|min:1',
            'origin_label' => 'nullable|string|max:150',
            'dest_type' => 'nullable|in:building,fotocopy,point',
            'dest_id' => 'nullable|integer|min:1',
            'dest_lat' => 'nullable|numeric|between:-90,90',
            'dest_lng' => 'nullable|numeric|between:-180,180',
            'dest_label' => 'nullable|string|max:150',
            'mode' => 'nullable|in:walking,driving',
        ]);

        if (empty($validated['origin_id']) && (!isset($validated['origin_lat'], $validated['origin_lng']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Titik asal wajib diisi (origin_type+origin_id atau origin_lat+origin_lng).',
            ], 422);
        }

        if (empty($validated['dest_id']) && (!isset($validated['dest_lat'], $validated['dest_lng']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Titik tujuan wajib diisi (dest_type+dest_id atau dest_lat+dest_lng).',
            ], 422);
        }

        if ($coordErr = $this->validateCoordinatePair($validated['origin_lat'] ?? null, $validated['origin_lng'] ?? null, 'Titik asal')) {
            return response()->json(['status' => 'error', 'message' => $coordErr], 422);
        }
        if ($coordErr = $this->validateCoordinatePair($validated['dest_lat'] ?? null, $validated['dest_lng'] ?? null, 'Titik tujuan')) {
            return response()->json(['status' => 'error', 'message' => $coordErr], 422);
        }

        if (!empty($validated['origin_id']) && !empty($validated['dest_id'])
            && ($validated['origin_type'] ?? 'point') === ($validated['dest_type'] ?? 'point')
            && (int) $validated['origin_id'] === (int) $validated['dest_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Titik asal dan tujuan tidak boleh sama.',
            ], 422);
        }

        try {
            $result = $this->routingService->computeRoute($validated);
            return response()->json($result, 200);
        } catch (ExternalRoutingUnavailableException $e) {
            return response()->json([
                'status' => 'external_routing_unavailable',
                'message' => $e->getMessage(),
            ], 503);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() ?: 'Rute tidak dapat dihitung saat ini.',
            ], 422);
        }
    }

    /**
     * Reject Null-Island (0,0) or coordinates outside Indonesia geographic bounds.
     */
    protected function validateCoordinatePair(mixed $lat, mixed $lng, string $label): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }
        $fLat = (float) $lat;
        $fLng = (float) $lng;
        if (!is_finite($fLat) || !is_finite($fLng) || (abs($fLat) < 0.0001 && abs($fLng) < 0.0001)) {
            return "Koordinat {$label} tidak valid (0,0).";
        }
        if ($fLat < -12.0 || $fLat > 8.0 || $fLng < 94.0 || $fLng > 142.0) {
            return "Koordinat {$label} berada di luar wilayah layanan Indonesia.";
        }

        return null;
    }

    /**
     * Parse shorthand query param (?origin=building:18 / ?destination=fotocopy:32 / ?origin=lat,lng).
     */
    protected function hydrateCompactEndpoint(Request $request, string $compactKey, string $prefix): void
    {
        $raw = trim((string) $request->input($compactKey, ''));
        if ($raw === '' || $request->filled("{$prefix}_id") || ($request->filled("{$prefix}_lat") && $request->filled("{$prefix}_lng"))) {
            return;
        }

        if (preg_match('/^(building|fotocopy):(\d+)$/i', $raw, $m)) {
            $request->merge([
                "{$prefix}_type" => strtolower($m[1]),
                "{$prefix}_id" => (int) $m[2],
            ]);
            return;
        }

        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/', $raw, $m)) {
            $request->merge([
                "{$prefix}_type" => 'point',
                "{$prefix}_lat" => (float) $m[1],
                "{$prefix}_lng" => (float) $m[2],
            ]);
        }
    }

    /**
     * Lightweight searchable catalog of all campus buildings & photocopy shops for origin/destination dropdowns.
     */
    public function destinations(): JsonResponse
    {
        $buildings = Building::orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'faculty', 'latitude', 'longitude', 'entrance_lat', 'entrance_lng'])
            ->map(static fn (Building $b): array => [
                'key' => "building:{$b->id}",
                'type' => 'building',
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
                'label' => $b->code ? "{$b->code} — {$b->name}" : $b->name,
                'subtitle' => $b->faculty ?: 'Gedung Kampus USU',
                'lat' => (float) $b->latitude,
                'lng' => (float) $b->longitude,
                'entrance_lat' => $b->entrance_lat !== null ? (float) $b->entrance_lat : null,
                'entrance_lng' => $b->entrance_lng !== null ? (float) $b->entrance_lng : null,
            ])
            ->values();

        $places = PhotocopyPlace::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'location_name', 'latitude', 'longitude', 'entrance_lat', 'entrance_lng'])
            ->map(static fn (PhotocopyPlace $p): array => [
                'key' => "fotocopy:{$p->id}",
                'type' => 'fotocopy',
                'id' => $p->id,
                'code' => 'FC-' . $p->id,
                'name' => $p->name,
                'label' => $p->name,
                'subtitle' => $p->location_name ?: 'Mitra Fotokopi Kampus USU',
                'lat' => (float) $p->latitude,
                'lng' => (float) $p->longitude,
                'entrance_lat' => $p->entrance_lat !== null ? (float) $p->entrance_lat : null,
                'entrance_lng' => $p->entrance_lng !== null ? (float) $p->entrance_lng : null,
            ])
            ->values();

        return response()->json([
            'status' => 'ok',
            'buildings_count' => $buildings->count(),
            'fotocopy_count' => $places->count(),
            'buildings' => $buildings,
            'fotocopy' => $places,
        ]);
    }
}
