<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use App\Services\GitSnapshotSyncService;
use App\Services\VirtualWalk\FibVirtualWalkGraphService;
use App\Support\CampusMediaUrl;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FacultyMediaAndDoorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Faculty360AndDoorPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminUserSeeder::class);
        app(GitSnapshotSyncService::class)->importAll(true);
        $this->seed(FacultyMediaAndDoorsSeeder::class);
    }

    public function test_100_percent_of_buildings_and_rooms_have_existing_360_and_door_files_on_disk(): void
    {
        $buildings = Building::all();
        $rooms = Room::with('building')->get();

        $this->assertGreaterThanOrEqual(77, $buildings->count());
        $this->assertGreaterThanOrEqual(133, $rooms->count());

        foreach ($buildings as $b) {
            $this->assertNotEmpty($b->photo_360_path, "Building {$b->code} tidak memiliki photo_360_path.");
            $this->assertNotEmpty($b->door_photo_path, "Building {$b->code} tidak memiliki door_photo_path.");
            $this->assertIsArray($b->photo_360_meta, "Building {$b->code} tidak memiliki photo_360_meta.");

            $p360 = CampusMediaUrl::publicFilePath($b->photo_360_path);
            $pDoor = CampusMediaUrl::publicFilePath($b->door_photo_path);

            $this->assertNotNull($p360);
            $this->assertNotNull($pDoor);
            $this->assertFileExists($p360, "File 360 gedung hilang: {$b->photo_360_path}");
            $this->assertFileExists($pDoor, "File pintu gedung hilang: {$b->door_photo_path}");
            $this->assertGreaterThan(5120, filesize($p360), "Ukuran foto 360 terlalu kecil: {$b->photo_360_path}");
            $this->assertGreaterThan(5120, filesize($pDoor), "Ukuran foto pintu terlalu kecil: {$b->door_photo_path}");
        }

        foreach ($rooms as $r) {
            $this->assertNotEmpty($r->photo_360_path, "Room #{$r->id} ({$r->name}) tidak memiliki photo_360_path.");
            $this->assertNotEmpty($r->door_photo_path, "Room #{$r->id} ({$r->name}) tidak memiliki door_photo_path.");
            $this->assertIsArray($r->photo_360_meta, "Room #{$r->id} ({$r->name}) tidak memiliki photo_360_meta.");

            $p360 = CampusMediaUrl::publicFilePath($r->photo_360_path);
            $pDoor = CampusMediaUrl::publicFilePath($r->door_photo_path);

            $this->assertNotNull($p360);
            $this->assertNotNull($pDoor);
            $this->assertFileExists($p360, "File 360 ruangan hilang: {$r->photo_360_path}");
            $this->assertFileExists($pDoor, "File pintu ruangan hilang: {$r->door_photo_path}");
            $this->assertGreaterThan(5120, filesize($p360), "Ukuran foto 360 ruangan terlalu kecil: {$r->photo_360_path}");
            $this->assertGreaterThan(5120, filesize($pDoor), "Ukuran foto pintu ruangan terlalu kecil: {$r->door_photo_path}");
        }
    }

    public function test_faculty_buildings_and_matched_fib_gl_rooms_use_correct_source_assets(): void
    {
        $expectedPools = [
            'FASILKOM-TI' => 'images/foto-360p/fasilkom/',
            'FT-Elektro_K' => 'images/foto-360p/elektro/',
            'FT-Industri_J' => 'images/foto-360p/teknik-industri/',
            'FF' => 'images/foto-360p/farmasi/',
            'FISIP' => 'images/foto-360p/fisip/',
            'FKM-B' => 'images/foto-360p/fkm/',
            'FMIPA-U5' => 'images/foto-360p/fmipa/',
            'FH' => 'images/foto-360p/hukum/',
            'FPSI' => 'images/foto-360p/psikologi/',
            'FKEP' => 'images/foto-360p/f-kep/',
            'FIB' => 'images/foto-360p/fib/',
        ];

        foreach ($expectedPools as $code => $prefix) {
            $b = Building::where('code', $code)->firstOrFail();
            $this->assertStringStartsWith(
                $prefix,
                (string) $b->photo_360_path,
                "Building {$code} harus memakai 360 fakultas {$prefix}, aktual: {$b->photo_360_path}"
            );
        }

        // Ruangan FIB & GL terpetakan harus memakai foto pintu asli (images/doors/real/...)
        $realSamples = [
            ['id' => 17, 'expected' => 'images/doors/real/fib-k-302.webp'],
            ['id' => 18, 'expected' => 'images/doors/real/fib-k-301.webp'],
            ['id' => 47, 'expected' => 'images/doors/real/fib-h-205.webp'],
            ['id' => 30, 'expected' => 'images/doors/real/fib-o-104.webp'],
            ['id' => 114, 'expected' => 'images/doors/real/gl-4a.webp'],
            ['id' => 115, 'expected' => 'images/doors/real/gl-2c.webp'],
        ];

        foreach ($realSamples as $sample) {
            $room = Room::findOrFail($sample['id']);
            $this->assertSame($sample['expected'], $room->door_photo_path);
        }

        // Ruangan non-real memakai foto pintu perspektif rektilinear hasil proyeksi 360°
        $fasilkomRoom = Room::whereHas('building', fn ($q) => $q->where('code', 'FASILKOM-TI'))->firstOrFail();
        $this->assertStringStartsWith('images/foto-360p/fasilkom/', (string) $fasilkomRoom->photo_360_path);
        $this->assertStringStartsWith('images/doors/from-360/', (string) $fasilkomRoom->door_photo_path);
    }

    public function test_buildings_api_exposes_non_null_media_without_unsplash_and_fib_walk_remains_intact(): void
    {
        $response = $this->getJson('/api/buildings');
        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('images.unsplash.com', (string) $content);

        $rows = $response->json('data');
        $this->assertGreaterThanOrEqual(77, count($rows));

        foreach ($rows as $bRow) {
            $this->assertNotEmpty($bRow['photo_360']);
            $this->assertNotEmpty($bRow['door_photo']);
            $this->assertIsArray($bRow['photo_360_meta']);
            foreach ($bRow['rooms'] ?? [] as $rRow) {
                $this->assertNotEmpty($rRow['photo_360']);
                $this->assertNotEmpty($rRow['door_photo']);
                $this->assertIsArray($rRow['photo_360_meta']);
            }
        }

        // Pastikan FIB Virtual Walk 67 scene (#428-#495, #434 dikecualikan) tetap 100% utuh
        $graph = app(FibVirtualWalkGraphService::class)->getGraph();
        $this->assertSame(67, $graph['stats']['active_scenes']);
        $this->assertArrayNotHasKey('434', $graph['scenes']);
        $this->assertArrayHasKey('428', $graph['scenes']);
        $this->assertArrayHasKey('495', $graph['scenes']);

        $this->get('/virtual-walk/fib?scene=428')->assertOk();
    }
}
