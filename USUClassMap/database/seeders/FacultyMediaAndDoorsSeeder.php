<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Room;
use App\Models\RoomMedia;
use App\Observers\GitSnapshotObserver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacultyMediaAndDoorsSeeder extends Seeder
{
    public function run(): void
    {
        $manifestPath = database_path('seeders/snapshots/faculty_media_manifest.json');
        if (!is_file($manifestPath)) {
            $fallback = storage_path('app/media-maps/faculty_media_manifest.json');
            if (is_file($fallback)) {
                $manifestPath = $fallback;
            }
        }

        if (!is_file($manifestPath)) {
            $this->command?->warn('faculty_media_manifest.json tidak ditemukan; FacultyMediaAndDoorsSeeder dilewati.');
            return;
        }

        $payload = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($payload)) {
            return;
        }

        GitSnapshotObserver::withoutSync(function () use ($payload): void {
            DB::transaction(function () use ($payload): void {
                $buildingMapByCode = Building::query()->pluck('id', 'code')->all();
                $buildingMediaRows = $payload['buildings'] ?? [];

                foreach ($buildingMediaRows as $row) {
                    $code = (string) ($row['code'] ?? '');
                    $id = $buildingMapByCode[$code] ?? ($row['id'] ?? null);
                    if (!$id) {
                        continue;
                    }

                    $meta = $row['photo_360_meta'] ?? null;
                    $update = [
                        'photo_360_path' => $row['photo_360_path'] ?? null,
                        'door_photo_path' => $row['door_photo_path'] ?? null,
                        'photo_360_meta' => is_array($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $meta,
                        'updated_at' => now(),
                    ];

                    DB::table('buildings')->where('id', $id)->update($update);
                }

                $rooms = Room::with('building:id,code')->get();
                $roomById = $rooms->keyBy('id');
                $roomByComposite = [];
                foreach ($rooms as $r) {
                    $bCode = (string) ($r->building?->code ?? '');
                    $comp = $bCode . '|' . strtolower(trim((string) $r->name));
                    $roomByComposite[$comp] = $r;
                }

                $roomMediaRows = $payload['rooms'] ?? [];
                $hasRoomMediaTable = Schema::hasTable('room_media');

                foreach ($roomMediaRows as $row) {
                    $comp = ((string) ($row['building_code'] ?? '')) . '|' . strtolower(trim((string) ($row['name'] ?? '')));
                    /** @var Room|null $room */
                    $room = $roomByComposite[$comp] ?? $roomById->get((int) ($row['id'] ?? 0));
                    if (!$room) {
                        continue;
                    }

                    $meta = $row['photo_360_meta'] ?? null;
                    $photo360Path = $row['photo_360_path'] ?? null;
                    $doorPhotoPath = $row['door_photo_path'] ?? null;

                    DB::table('rooms')->where('id', $room->id)->update([
                        'photo_360_path' => $photo360Path,
                        'door_photo_path' => $doorPhotoPath,
                        'photo_360_meta' => is_array($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $meta,
                        'updated_at' => now(),
                    ]);

                    if ($hasRoomMediaTable) {
                        if ($photo360Path) {
                            RoomMedia::updateOrCreate(
                                ['room_id' => $room->id, 'type' => '360'],
                                [
                                    'file_path' => $photo360Path,
                                    'is_primary' => true,
                                    'metadata' => is_array($meta) ? $meta : null,
                                ]
                            );
                        }
                        if ($doorPhotoPath) {
                            RoomMedia::updateOrCreate(
                                ['room_id' => $room->id, 'type' => 'photo'],
                                [
                                    'file_path' => $doorPhotoPath,
                                    'is_primary' => true,
                                    'metadata' => [
                                        'door_source' => is_array($meta) ? ($meta['door_source'] ?? 'rectilinear_360') : 'rectilinear_360',
                                    ],
                                ]
                            );
                        }
                    }
                }
            });
        });
    }
}
