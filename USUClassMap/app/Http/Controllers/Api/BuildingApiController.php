<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Services\OperationalStatusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BuildingApiController extends Controller
{
    public function __construct(
        protected ?OperationalStatusService $operationalStatus = null
    ) {
        $this->operationalStatus = $operationalStatus ?? app(OperationalStatusService::class);
    }

    /**
     * Get all campus buildings with GPS coordinates, rooms, and contextual schedules.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $nowWib = Carbon::now(OperationalStatusService::TIMEZONE);

        $buildings = Building::with([
            'rooms' => function ($query) {
                $query->orderBy('floor')->orderBy('name');
            },
            'rooms.schedules.course',
            'rooms.schedules.lecturer'
        ])->orderBy('name')->get();

        $data = $buildings->map(function ($b) use ($user, $nowWib) {
            $opStatus = $this->operationalStatus->evaluateBuildingStatus($b, $nowWib);

            return [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'faculty' => $b->faculty,
                'category' => $b->category,
                'latitude' => (float) $b->latitude,
                'longitude' => (float) $b->longitude,
                'description' => $b->description,
                'photo_360' => $b->photo_360_url,
                'door_photo' => $b->door_photo_url,
                'photo_360_meta' => $b->photo_360_meta,
                'floor_count' => (int) $b->floor_count,
                'rooms_count' => $b->rooms->count(),
                'operational_status' => $opStatus,
                'rooms' => $b->rooms->map(function ($r) use ($user) {
                    return [
                        'id' => $r->id,
                        'name' => $r->name,
                        'floor' => (int) $r->floor,
                        'capacity' => (int) $r->capacity,
                        'photo_360' => $r->photo_360_url,
                        'door_photo' => $r->door_photo_url,
                        'photo_360_meta' => $r->photo_360_meta,
                        'schedules' => $r->schedules->map(function ($s) use ($user) {
                            $isMyClass = false;
                            if ($user && !empty($user->semester) && $s->semester === $user->semester) {
                                $isMyClass = true;
                            }
                            return [
                                'id' => $s->id,
                                'is_my_class' => (bool) $isMyClass,
                                'mata_kuliah' => [
                                    'id' => $s->course->id ?? null,
                                    'kode' => $s->course->code ?? '-',
                                    'nama' => $s->course->name ?? '-',
                                    'sks' => $s->course->credits ?? 0,
                                ],
                                'dosen' => [
                                    'id' => $s->lecturer->id ?? null,
                                    'nama' => $s->lecturer->name ?? '-',
                                ],
                                'hari' => $s->day,
                                'jam_mulai' => $s->time_start ? substr($s->time_start, 0, 5) : '--:--',
                                'jam_selesai' => $s->time_end ? substr($s->time_end, 0, 5) : '--:--',
                                'semester' => $s->semester,
                                'kelas' => $s->class_group,
                                'kelompok_kelas' => $s->class_group,
                            ];
                        })->values(),
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'evaluated_at_wib' => $nowWib->format('H:i'),
            'count' => $data->count(),
            'data' => $data,
        ]);
    }

    /**
     * Get specific building details.
     *
     * @param Building $building
     * @return JsonResponse
     */
    public function show(Building $building): JsonResponse
    {
        $building->load(['rooms.schedules.course', 'rooms.schedules.lecturer']);
        $opStatus = $this->operationalStatus->evaluateBuildingStatus($building);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $building->id,
                'name' => $building->name,
                'code' => $building->code,
                'latitude' => (float) $building->latitude,
                'longitude' => (float) $building->longitude,
                'description' => $building->description,
                'photo_360' => $building->photo_360_url,
                'door_photo' => $building->door_photo_url,
                'photo_360_meta' => $building->photo_360_meta,
                'floor_count' => (int) $building->floor_count,
                'operational_status' => $opStatus,
                'rooms' => $building->rooms->map(fn ($r) => array_merge($r->toArray(), [
                    'photo_360' => $r->photo_360_url,
                    'door_photo' => $r->door_photo_url,
                ]))->values(),
            ],
        ]);
    }
}
