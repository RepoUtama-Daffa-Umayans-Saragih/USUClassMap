<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
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

    /**
     * Get ALL campus schedules with building coordinates and dynamic `is_my_class` attribute.
     * (Phase 6.2 & 6.3 - Do NOT filter out other classes; return all, with is_my_class flag).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function mySchedules(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Build the schedule query - eager load all relationships
        // Return ALL schedules so frontend can display full room availability and highlight user's classes
        $query = Schedule::with([
            'course',
            'lecturer',
            'room.building',
        ]);

        $schedules = $query->get();

        // Transform to include building coordinates for map markers and dynamic is_my_class attribute
        $scheduleData = $schedules
            ->filter(function ($schedule) {
                // Only include schedules that have a room with a building that has coordinates
                return $schedule->room
                    && $schedule->room->building
                    && $schedule->room->building->latitude
                    && $schedule->room->building->longitude;
            })
            ->map(function ($schedule) use ($user) {
                // Determine if this class belongs to the authenticated user
                $isMyClass = false;
                if ($user) {
                    // Check user's semester match (or user_schedules relationship)
                    if (!empty($user->semester) && $schedule->semester === $user->semester) {
                        $isMyClass = true;
                    }
                }

                return [
                    'id' => $schedule->id,
                    'is_my_class' => (bool) $isMyClass,
                    'mata_kuliah' => [
                        'id' => $schedule->course->id ?? null,
                        'kode' => $schedule->course->code ?? '-',
                        'nama' => $schedule->course->name ?? '-',
                        'sks' => $schedule->course->credits ?? 0,
                    ],
                    'dosen' => [
                        'id' => $schedule->lecturer->id ?? null,
                        'nama' => $schedule->lecturer->name ?? '-',
                    ],
                    'ruangan' => [
                        'id' => $schedule->room->id ?? null,
                        'name' => $schedule->room->name ?? '-',
                        'nama' => $schedule->room->name ?? '-',
                        'floor' => $schedule->room->floor ?? 1,
                        'lantai' => $schedule->room->floor ?? 1,
                        'foto_360' => $schedule->room->photo_360_path
                            ? asset('storage/' . $schedule->room->photo_360_path)
                            : null,
                    ],
                    'gedung' => [
                        'id' => $schedule->room->building->id ?? null,
                        'nama' => $schedule->room->building->name ?? '-',
                        'kode' => $schedule->room->building->code ?? '-',
                        'latitude' => (float) $schedule->room->building->latitude,
                        'longitude' => (float) $schedule->room->building->longitude,
                    ],
                    'hari' => $schedule->day,
                    'jam_mulai' => $schedule->time_start ? substr($schedule->time_start, 0, 5) : '--:--',
                    'jam_selesai' => $schedule->time_end ? substr($schedule->time_end, 0, 5) : '--:--',
                    'semester' => $schedule->semester,
                    'kelas' => $schedule->class_group,
                    'kelompok_kelas' => $schedule->class_group,
                ];
            })
            ->values();

        // Group by building for summary
        $buildingSummary = $scheduleData->groupBy('gedung.kode')->map(function ($group) {
            $myClassesCount = $group->where('is_my_class', true)->count();
            return [
                'gedung' => $group->first()['gedung'],
                'total_jadwal' => $group->count(),
                'my_classes_count' => $myClassesCount,
            ];
        })->values();

        $myClassesTotal = $scheduleData->where('is_my_class', true)->count();

        return response()->json([
            'status' => 'success',
            'user' => $user ? [
                'name' => $user->name,
                'nim' => $user->nim,
                'semester' => $user->semester,
            ] : null,
            'total' => $scheduleData->count(),
            'my_classes_total' => $myClassesTotal,
            'buildings_with_schedules' => $buildingSummary->count(),
            'data' => $scheduleData,
            'buildings_summary' => $buildingSummary,
        ]);
    }
}
