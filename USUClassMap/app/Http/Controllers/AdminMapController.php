<?php

namespace App\Http\Controllers;

use App\Jobs\RegenerateRdfJob;
use App\Models\Building;
use App\Models\CampusRoutingOverride;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\PhotocopyPlace;
use App\Models\Room;
use App\Models\RoomMedia;
use App\Models\Schedule;
use App\Services\GitSnapshotSyncService;
use App\Services\Routing\CampusGraphBuilderService;
use App\Services\Routing\CampusRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminMapController extends Controller
{
    public function index()
    {
        $buildings = Building::orderBy('name')->get();
        $courses = Course::orderBy('name')->get();
        $lecturers = Lecturer::orderBy('name')->get();
        $photocopyPlaces = PhotocopyPlace::orderBy('name')->get([
            'id',
            'name',
            'latitude',
            'longitude',
            'entrance_lat',
            'entrance_lng',
            'address',
        ]);

        return view('features.admin.map', compact('buildings', 'courses', 'lecturers', 'photocopyPlaces'));
    }

    public function storeBuilding(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30|unique:buildings,code',
            'faculty' => 'nullable|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'description' => 'nullable|string',
            'floor_count' => 'required|integer|min:1|max:20',
        ], [
            'required' => 'Kolom :attribute wajib diisi.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'unique' => ':attribute sudah terdaftar.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'between' => 'Kolom :attribute harus antara :min dan :max.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'min' => 'Kolom :attribute minimal :min.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $building = Building::create($request->all());
            RegenerateRdfJob::dispatch();
            return response()->json([
                'success' => true,
                'message' => 'Gedung berhasil ditambahkan!',
                'building' => $building
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    public function storeRoom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'building_id' => 'required|exists:buildings,id',
            'name' => 'required|string|max:255',
            'floor' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
        ], [
            'required' => 'Kolom :attribute wajib diisi.',
            'exists' => ':attribute tidak valid.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'min' => 'Kolom :attribute minimal :min.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $room = Room::create($request->all());
            $room->load('building');
            RegenerateRdfJob::dispatch();

            return response()->json([
                'success' => true,
                'message' => 'Ruangan berhasil ditambahkan!',
                'room' => $room
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    public function storeSchedule(Request $request)
    {
        $rules = [
            'room_id' => 'required|exists:rooms,id',
            'course_mode' => 'required|in:existing,new',
            'lecturer_id' => 'required|exists:lecturers,id',
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
            'class_group' => 'required|string|max:10',
            'semester' => 'required|string|max:20',
        ];

        if ($request->course_mode === 'new') {
            $rules['new_course_code'] = 'required|string|max:50|unique:courses,code';
            $rules['new_course_name'] = 'required|string|max:255';
        } else {
            $rules['course_id'] = 'required|exists:courses,id';
        }

        $validator = Validator::make($request->all(), $rules, [
            'required' => 'Kolom :attribute wajib diisi.',
            'exists' => ':attribute tidak valid.',
            'unique' => ':attribute sudah terdaftar.',
            'in' => 'Format :attribute tidak valid.',
            'date_format' => 'Format waktu tidak valid (HH:MM).',
            'after' => 'Waktu selesai harus setelah waktu mulai.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'max' => 'Kolom :attribute maksimal :max karakter.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $course = null;
            if ($request->course_mode === 'new') {
                $course = Course::create([
                    'code' => $request->new_course_code,
                    'name' => $request->new_course_name,
                    'credits' => 2,
                    'type' => 'MKWU',
                ]);
                $courseId = $course->id;
            } else {
                $courseId = $request->course_id;
            }

            $schedule = Schedule::create([
                'course_id' => $courseId,
                'lecturer_id' => $request->lecturer_id,
                'room_id' => $request->room_id,
                'day' => $request->day,
                'time_start' => $request->time_start,
                'time_end' => $request->time_end,
                'class_group' => $request->class_group,
                'semester' => $request->semester,
            ]);

            $schedule->load(['course', 'lecturer', 'room.building']);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil ditambahkan!',
                'schedule' => $schedule,
                'new_course' => $course ? [
                    'id' => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                ] : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    public function getRoomsByBuilding($buildingId)
    {
        try {
            $rooms = Room::where('building_id', $buildingId)
                ->orderBy('floor')
                ->orderBy('name')
                ->get();

            return response()->json($rooms);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    public function updateBuilding(Request $request, Building $building)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30|unique:buildings,code,' . $building->id,
            'faculty' => 'nullable|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'description' => 'nullable|string',
            'floor_count' => 'required|integer|min:1|max:20',
        ], [
            'required' => 'Kolom :attribute wajib diisi.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'unique' => ':attribute sudah terdaftar.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'between' => 'Kolom :attribute harus antara :min dan :max.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'min' => 'Kolom :attribute minimal :min.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $building->update($request->only([
                'name', 'code', 'faculty', 'latitude', 'longitude', 'description', 'floor_count'
            ]));
            RegenerateRdfJob::dispatch();

            return response()->json([
                'success' => true,
                'message' => 'Gedung berhasil diperbarui!',
                'building' => $building->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    public function updateRoom(Request $request, Room $room)
    {
        $validator = Validator::make($request->all(), [
            'building_id' => 'required|exists:buildings,id',
            'name' => 'required|string|max:255',
            'floor' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
        ], [
            'required' => 'Kolom :attribute wajib diisi.',
            'exists' => ':attribute tidak valid.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'min' => 'Kolom :attribute minimal :min.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $room->update($request->only(['building_id', 'name', 'floor', 'capacity']));
            $room->load('building');
            RegenerateRdfJob::dispatch();

            return response()->json([
                'success' => true,
                'message' => 'Data ruangan berhasil diperbarui!',
                'room' => $room
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    public function getRoomMedia($roomId)
    {
        try {
            $media = RoomMedia::where('room_id', $roomId)
                ->orderByDesc('is_primary')
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'media' => $media
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat media ruangan.'
            ], 500);
        }
    }

    public function storeRoomMedia(Request $request, $roomId)
    {
        $room = Room::findOrFail($roomId);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpeg,png,jpg,mp4|max:51200',
            'type' => 'required|in:photo,360,video',
            'is_primary' => 'nullable|boolean',
            'metadata' => 'nullable',
        ], [
            'file.required' => 'File media wajib diunggah.',
            'file.file' => 'Unggahan harus berupa file yang valid.',
            'file.mimes' => 'Format file harus berupa JPG, PNG, atau MP4.',
            'file.max' => 'Ukuran file maksimal 50 MB.',
            'type.required' => 'Tipe media wajib dipilih.',
            'type.in' => 'Tipe media harus berupa Foto Biasa, 360°, atau Video Rute.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $path = $file->store('room_media', 'public');

            $metadata = null;
            if ($request->filled('metadata')) {
                $metadata = is_array($request->metadata) ? $request->metadata : json_decode($request->metadata, true);
            }

            // If marked as primary or if this is the first media of this type
            $isPrimary = $request->boolean('is_primary');

            $media = RoomMedia::create([
                'room_id' => $room->id,
                'type' => $request->type,
                'file_path' => $path,
                'is_primary' => $isPrimary,
                'metadata' => $metadata,
            ]);

            // Sync photo_360_path if this is 360 photo
            if ($request->type === '360' || $isPrimary) {
                $room->update(['photo_360_path' => $path]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Media ' . strtoupper($request->type) . ' berhasil diunggah!',
                'media' => $media
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah file media: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateMediaMetadata(Request $request, $mediaId)
    {
        $media = RoomMedia::findOrFail($mediaId);

        $validator = Validator::make($request->all(), [
            'metadata' => 'required',
        ], [
            'metadata.required' => 'Konfigurasi metadata hotspot wajib disertakan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metadata = is_array($request->metadata) ? $request->metadata : json_decode($request->metadata, true);
            $media->metadata = $metadata;
            $media->save();

            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi hotspot 360° berhasil disimpan!',
                'media' => $media
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan konfigurasi hotspot 360°.'
            ], 500);
        }
    }

    public function destroyRoomMedia($mediaId)
    {
        try {
            $media = RoomMedia::findOrFail($mediaId);

            if (!empty($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }

            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus media.'
            ], 500);
        }
    }

    public function getRoutingData(CampusRoutingService $routingService)
    {
        $graph = $routingService->getCompiledGraph();
        $stats = is_array($graph['stats'] ?? null) ? $graph['stats'] : [];
        $obstacles = is_array($graph['obstacles'] ?? null) ? $graph['obstacles'] : [];

        // Ringankan payload overlay peta: konversi edges unik menjadi GeoJSON LineString
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];
        $edgeFeatures = [];
        $seenSegments = [];

        foreach ($edges as $edge) {
            if (!is_array($edge) || !empty($edge['blocked_by_barrier'])) {
                continue;
            }
            $fromId = $edge['u'] ?? null;
            $toId = $edge['v'] ?? null;
            if ($fromId === null || $toId === null || !isset($nodes[$fromId], $nodes[$toId])) {
                continue;
            }
            $pairKey = strcmp((string) $fromId, (string) $toId) <= 0
                ? ($fromId . '->' . $toId)
                : ($toId . '->' . $fromId);
            if (isset($seenSegments[$pairKey])) {
                continue;
            }
            $seenSegments[$pairKey] = true;
            $fromNode = $nodes[$fromId];
            $toNode = $nodes[$toId];
            $edgeFeatures[] = [
                'type' => 'Feature',
                'properties' => [
                    'road_class' => $edge['road_class'] ?? 'footway',
                    'walkable' => (bool) ($edge['walkable'] ?? true),
                    'drivable' => (bool) ($edge['drivable'] ?? false),
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [(float) $fromNode['lng'], (float) $fromNode['lat']],
                        [(float) $toNode['lng'], (float) $toNode['lat']],
                    ],
                ],
            ];
        }

        $overrides = CampusRoutingOverride::orderByDesc('id')
            ->get()
            ->map(static fn (CampusRoutingOverride $ov): array => [
                'id' => $ov->id,
                'type' => $ov->type,
                'name' => $ov->name,
                'coordinates' => $ov->coordinates_json ?? [],
                'coordinates_json' => $ov->coordinates_json ?? [],
                'metadata_json' => $ov->metadata_json ?? [],
                'notes' => $ov->metadata_json['notes'] ?? null,
                'is_active' => (bool) $ov->is_active,
                'created_at' => $ov->created_at?->toIso8601String(),
            ])
            ->values();

        $buildings = Building::orderBy('name')->get([
            'id', 'code', 'name', 'latitude', 'longitude', 'entrance_lat', 'entrance_lng',
        ]);
        $photocopyPlaces = PhotocopyPlace::orderBy('name')->get([
            'id', 'name', 'latitude', 'longitude', 'entrance_lat', 'entrance_lng',
        ]);

        return response()->json([
            'success' => true,
            'metadata' => array_merge($stats, [
                'nodes_count' => count($nodes),
                'rendered_edge_count' => count($edgeFeatures),
                'buildings_polygon_count' => count($obstacles['buildings'] ?? []),
                'barriers_count' => count($obstacles['barriers'] ?? []),
            ]),
            'graph_geojson' => [
                'type' => 'FeatureCollection',
                'features' => $edgeFeatures,
            ],
            'overrides' => $overrides,
            'buildings' => $buildings,
            'photocopy_places' => $photocopyPlaces,
        ]);
    }

    public function storeRoutingOverride(Request $request, CampusRoutingService $routingService)
    {
        if (!$request->has('coordinates') && $request->has('coordinates_json')) {
            $request->merge(['coordinates' => $request->input('coordinates_json')]);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:walkway,bridge,barrier',
            'name' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:500',
            'metadata_json' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'coordinates' => 'required|array|min:2',
            'coordinates.*' => 'required|array|size:2',
            'coordinates.*.0' => 'required|numeric|between:-180,180',
            'coordinates.*.1' => 'required|numeric|between:-90,90',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data override rute tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $coords = array_map(
            static fn (array $pair): array => [(float) $pair[0], (float) $pair[1]],
            $request->input('coordinates')
        );

        $meta = is_array($request->input('metadata_json')) ? $request->input('metadata_json') : [];
        if ($request->filled('notes')) {
            $meta['notes'] = $request->input('notes');
        }

        $override = CampusRoutingOverride::create([
            'type' => $request->input('type'),
            'name' => $request->input('name') ?: ('Override ' . strtoupper($request->input('type'))),
            'coordinates_json' => $coords,
            'metadata_json' => !empty($meta) ? $meta : null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        $routingService->invalidateCache();

        return response()->json([
            'success' => true,
            'message' => 'Override rute berhasil disimpan dan graf di-refresh.',
            'override' => array_merge($override->toArray(), [
                'coordinates' => $override->coordinates_json ?? [],
            ]),
        ], 201);
    }

    public function destroyRoutingOverride(CampusRoutingOverride $override, CampusRoutingService $routingService)
    {
        $override->delete();
        $routingService->invalidateCache();

        return response()->json([
            'success' => true,
            'message' => 'Override rute berhasil dihapus.',
        ]);
    }

    public function saveRoutingEntrance(Request $request, CampusRoutingService $routingService)
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|string|in:building,fotocopy',
            'entity_id' => 'required|integer|min:1',
            'entrance_lat' => 'nullable|numeric|between:-90,90|required_with:entrance_lng',
            'entrance_lng' => 'nullable|numeric|between:-180,180|required_with:entrance_lat',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Koordinat pintu masuk tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $entityType = $request->input('entity_type');
        $entity = $entityType === 'building'
            ? Building::findOrFail((int) $request->input('entity_id'))
            : PhotocopyPlace::findOrFail((int) $request->input('entity_id'));

        $lat = $request->input('entrance_lat');
        $lng = $request->input('entrance_lng');

        $entity->entrance_lat = $lat !== null ? (float) $lat : null;
        $entity->entrance_lng = $lng !== null ? (float) $lng : null;
        $entity->save();

        $routingService->invalidateCache();

        return response()->json([
            'success' => true,
            'message' => ($lat !== null && $lng !== null)
                ? 'Titik pintu masuk berhasil diperbarui.'
                : 'Titik pintu masuk dikembalikan ke default.',
            'entity' => [
                'entity_type' => $entityType,
                'id' => $entity->id,
                'name' => $entity->name,
                'latitude' => (float) $entity->latitude,
                'longitude' => (float) $entity->longitude,
                'entrance_lat' => $entity->entrance_lat !== null ? (float) $entity->entrance_lat : null,
                'entrance_lng' => $entity->entrance_lng !== null ? (float) $entity->entrance_lng : null,
            ],
        ]);
    }

    public function rebuildRoutingGraph(
        Request $request,
        CampusGraphBuilderService $builder,
        CampusRoutingService $routingService,
        GitSnapshotSyncService $gitSync
    ) {
        try {
            $graph = $builder->buildAndSave(fromSnapshot: !$request->boolean('live', false));
            $routingService->invalidateCache();
            $gitSync->exportSnapshot();

            return response()->json([
                'success' => true,
                'message' => 'Graf jalan kampus berhasil dibangun ulang dan disinkronkan ke snapshot.',
                'metadata' => $graph['stats'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membangun ulang graf jalan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
