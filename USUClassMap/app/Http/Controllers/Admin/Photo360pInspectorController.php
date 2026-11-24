<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Foto360p\Photo360pMarkdownBuilder;
use App\Services\Foto360p\Photo360pScanRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Photo360pInspectorController extends Controller
{
    public function __construct(
        protected Photo360pScanRepository $repository,
        protected Photo360pMarkdownBuilder $builder
    ) {
    }

    /**
     * Render isolated proof inspector page at /admin/cek-foto/360p.
     */
    public function index()
    {
        $payload = $this->repository->load();
        $stats = $this->builder->stats($payload);

        return view('features.admin.cek-foto-360p', [
            'payload' => $payload,
            'stats'   => $stats,
        ]);
    }

    /**
     * Persist manual admin verification / room edits to scan_results.json and sync DAFTAR_RUANGAN_DEEP_SCAN_360P.md.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'verified_by_admin'      => 'required|boolean',
            'admin_note'             => 'nullable|string|max:1000',
            'status'                 => 'nullable|string|in:detected,no_text,blurry',
            'rooms'                  => 'nullable|array|max:20',
            'rooms.*.room_name'      => 'required|string|max:120',
            'rooms.*.raw_text'       => 'nullable|string|max:200',
            'rooms.*.category'       => 'nullable|string|max:40',
            'rooms.*.yaw'            => 'nullable|numeric|between:-360,360',
            'rooms.*.pitch'          => 'nullable|numeric|between:-90,90',
            'rooms.*.evidence_note'  => 'nullable|string|max:500',
            'rooms.*.source'         => 'nullable|string|max:20',
        ]);

        $existing = $this->repository->findItem($id);
        if ($existing === null) {
            return response()->json([
                'status'  => 'error',
                'message' => "Foto #{$id} tidak ditemukan.",
            ], 404);
        }

        $proj = $existing['projection'] ?? Photo360pScanRepository::projectionFor(
            (int) ($existing['width'] ?? 0),
            (int) ($existing['height'] ?? 0)
        );

        if ($request->has('rooms')) {
            $rooms = [];
            foreach ($validated['rooms'] ?? [] as $r) {
                $roomName = trim((string) $r['room_name']);
                if ($roomName === '') {
                    continue;
                }
                $rawText = trim((string) ($r['raw_text'] ?? ''));
                if ($rawText === '') {
                    $rawText = $roomName;
                }
                $yaw = round((float) ($r['yaw'] ?? 0.0), 1);
                $pitch = round((float) ($r['pitch'] ?? 0.0), 1);
                $pct = Photo360pScanRepository::coordsToPercentages($yaw, $pitch, $proj);

                $rooms[] = [
                    'room_name'     => $roomName,
                    'raw_text'      => $rawText,
                    'verified_read' => $rawText,
                    'category'      => (string) ($r['category'] ?? 'ruang_kuliah'),
                    'confidence'    => 'admin',
                    'yaw'           => $yaw,
                    'pitch'         => $pitch,
                    'x_pct'         => $pct['x_pct'],
                    'y_pct'         => $pct['y_pct'],
                    'evidence_note' => (string) ($r['evidence_note'] ?? 'Diverifikasi oleh admin'),
                    'source'        => !empty($r['source']) ? (string) $r['source'] : 'admin',
                ];
            }
        } else {
            $rooms = $existing['detected_rooms'] ?? [];
        }

        $finalStatus = !empty($rooms)
            ? 'detected'
            : (string) ($validated['status'] ?? ($existing['status'] !== 'detected' ? $existing['status'] : 'no_text'));

        $updated = $this->repository->updateItem($id, [
            'detected_rooms'    => $rooms,
            'status'            => $finalStatus,
            'verified_by_admin' => (bool) $validated['verified_by_admin'],
            'admin_note'        => (string) ($validated['admin_note'] ?? ''),
            'verified_at'       => Carbon::now('Asia/Jakarta')->toIso8601String(),
        ]);

        $fullPayload = $this->repository->load();
        $this->builder->write($fullPayload);
        $stats = $this->builder->stats($fullPayload);

        return response()->json([
            'status' => 'success',
            'item'   => $updated,
            'stats'  => $stats,
        ]);
    }

    /**
     * Download isolated scan list in Markdown, CSV, or JSON format.
     */
    public function export(string $format): StreamedResponse
    {
        $format = strtolower($format);
        $data = $this->repository->load();

        return match ($format) {
            'md' => response()->streamDownload(function () use ($data): void {
                echo $this->builder->build($data);
            }, 'DAFTAR_RUANGAN_DEEP_SCAN_360P.md', [
                'Content-Type' => 'text/markdown; charset=UTF-8',
            ]),
            'csv' => response()->streamDownload(function () use ($data): void {
                echo $this->builder->csv($data);
            }, 'daftar-ruangan-360p.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]),
            'json' => response()->streamDownload(function () use ($data): void {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }, 'scan_results_360p.json', [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]),
            default => abort(404),
        };
    }
}
