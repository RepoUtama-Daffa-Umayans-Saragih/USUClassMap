<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\RoomMedia;
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

        return view('admin.map', compact('buildings', 'courses', 'lecturers'));
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
}
