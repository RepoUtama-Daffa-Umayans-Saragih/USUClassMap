<?php

namespace App\Http\Controllers;

use App\Services\VirtualWalk\FibVirtualWalkGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FibVirtualWalkController extends Controller
{
    public function __construct(
        protected FibVirtualWalkGraphService $walkService
    ) {
    }

    /**
     * Mode Publik / Mahasiswa: Jalan Virtual 360° Fakultas Ilmu Budaya (FIB).
     * GET /virtual-walk/fib?scene=495
     */
    public function publicView(Request $request)
    {
        $graph = $this->walkService->getGraph(false);
        $scenes = $graph['scenes'] ?? [];

        $requested = (int) $request->query('scene', FibVirtualWalkGraphService::START_SCENE_ID);
        $initialSceneId = isset($scenes[(string) $requested])
            ? $requested
            : (int) ($graph['start_scene_id'] ?? FibVirtualWalkGraphService::START_SCENE_ID);

        return view('features.virtual-walk.fib', [
            'isAdminMode'    => false,
            'initialSceneId' => $initialSceneId,
            'graph'          => $graph,
        ]);
    }

    /**
     * Mode Admin & Kalibrasi Arah Panah 1-Klik.
     * GET /admin/cek-foto/fib-walk?scene=435
     */
    public function adminView(Request $request)
    {
        $graph = $this->walkService->getGraph(false);
        $scenes = $graph['scenes'] ?? [];

        $defaultAdminStart = isset($scenes['435']) ? 435 : ($graph['start_scene_id'] ?? FibVirtualWalkGraphService::START_SCENE_ID);
        $requested = (int) $request->query('scene', $defaultAdminStart);
        $initialSceneId = isset($scenes[(string) $requested]) ? $requested : $defaultAdminStart;

        return view('features.virtual-walk.fib', [
            'isAdminMode'    => true,
            'initialSceneId' => $initialSceneId,
            'graph'          => $graph,
        ]);
    }

    /**
     * Endpoint JSON publik graf 67 scene FIB 360°.
     * GET /api/virtual-walk/fib/graph
     */
    public function graph(): JsonResponse
    {
        $graph = $this->walkService->getGraph(false);

        return response()->json([
            'status' => 'success',
            'data'   => $graph,
        ]);
    }

    /**
     * Stream panorama 4K terkompresi (4096x2048) untuk kompatibilitas WebGL & preloading 0ms.
     * GET /virtual-walk/fib/pano/{id}
     */
    public function servePanorama(int $id)
    {
        $path = $this->walkService->getCachedPanoramaPath($id);
        if (!$path || !is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Simpan pembaruan label, metadata, orientasi default, dan hotspot panah pada scene tertentu.
     * POST /admin/cek-foto/fib-walk/scene/{id}
     */
    public function updateScene(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'label'                   => 'sometimes|string|max:180',
            'zone'                    => 'sometimes|string|max:120',
            'floor'                   => 'sometimes|integer|min:1|max:5',
            'type'                    => 'sometimes|string|in:room,corridor,stairs,junction,outdoor',
            'default_pitch'           => 'sometimes|numeric|between:-85,85',
            'default_yaw'             => 'sometimes|numeric|between:-360,360',
            'hotspots'                => 'sometimes|array|max:30',
            'hotspots.*.target_id'    => 'required|integer',
            'hotspots.*.label'        => 'required|string|max:180',
            'hotspots.*.kind'         => 'nullable|string|in:walk,room,stairs_up,stairs_down',
            'hotspots.*.yaw'          => 'required|numeric|between:-360,360',
            'hotspots.*.pitch'        => 'required|numeric|between:-85,85',
            'hotspots.*.target_yaw'   => 'nullable|numeric|between:-360,360',
            'hotspots.*.target_pitch' => 'nullable|numeric|between:-85,85',
        ]);

        try {
            $result = $this->walkService->updateScene($id, $validated);

            return response()->json([
                'status'  => 'success',
                'message' => "Kalibrasi titik #{$id} berhasil disimpan permanen.",
                'scene'   => $result['scene'],
                'stats'   => $result['stats'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Tambah koneksi jalan dua arah antar dua foto FIB secara dinamis.
     * POST /admin/cek-foto/fib-walk/link
     */
    public function addLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_id' => 'required|integer',
            'to_id'   => 'required|integer|different:from_id',
            'yaw'     => 'nullable|numeric|between:-360,360',
            'pitch'   => 'nullable|numeric|between:-85,85',
            'kind'    => 'nullable|string|in:walk,room,stairs_up,stairs_down',
            'label'   => 'nullable|string|max:180',
        ]);

        try {
            $result = $this->walkService->addBidirectionalLink(
                (int) $validated['from_id'],
                (int) $validated['to_id'],
                $validated
            );

            return response()->json([
                'status'     => 'success',
                'message'    => "Koneksi dua arah #{$validated['from_id']} <-> #{$validated['to_id']} berhasil ditambahkan.",
                'from_scene' => $result['from_scene'],
                'to_scene'   => $result['to_scene'],
                'stats'      => $result['stats'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Hapus koneksi jalan dua arah antara dua foto FIB.
     * DELETE /admin/cek-foto/fib-walk/link
     */
    public function removeLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_id' => 'required|integer',
            'to_id'   => 'required|integer',
        ]);

        $result = $this->walkService->removeBidirectionalLink(
            (int) $validated['from_id'],
            (int) $validated['to_id']
        );

        return response()->json([
            'status'     => 'success',
            'message'    => "Koneksi #{$validated['from_id']} <-> #{$validated['to_id']} berhasil dihapus.",
            'from_scene' => $result['from_scene'],
            'stats'      => $result['stats'],
        ]);
    }
}
