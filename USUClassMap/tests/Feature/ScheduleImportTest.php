<?php

namespace Tests\Feature;

use App\Imports\ScheduleImport;
use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ScheduleImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Building $building;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@usu.ac.id',
            'password' => bcrypt('Admin123!'),
            'role' => 'superadmin',
        ]);

        $this->building = Building::create([
            'name' => 'Gedung Kuliah Bersama 1',
            'code' => 'GK1',
            'latitude' => 3.56521234,
            'longitude' => 98.65651234,
            'description' => 'Gedung perkuliahan MKWK & MKWU USU',
            'floor_count' => 3,
        ]);

        $this->room = Room::create([
            'building_id' => $this->building->id,
            'name' => 'GK1-101',
            'floor' => 1,
            'capacity' => 45,
            'photo_360_path' => null,
        ]);
    }

    public function test_schedule_import_directly_creates_records(): void
    {
        $import = new ScheduleImport();

        // Simulate row 1
        $schedule = $import->model([
            'kode_mk' => 'MKW101',
            'nama_mk' => 'Pendidikan Agama Islam',
            'sks' => 2,
            'tipe_mk' => 'MKWU',
            'nama_dosen' => 'Dr. H. Ahmad Fauzi, M.A.',
            'nama_ruangan' => 'GK1-101',
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:40',
            'semester' => 'Ganjil 2024/2025',
            'kelompok_kelas' => 'A',
        ]);

        $this->assertNotNull($schedule);
        $schedule->save();

        $this->assertDatabaseHas('courses', ['code' => 'MKW101', 'name' => 'Pendidikan Agama Islam']);
        $this->assertDatabaseHas('lecturers', ['name' => 'Dr. H. Ahmad Fauzi, M.A.']);
        $this->assertDatabaseHas('schedules', [
            'day' => 'Senin',
            'class_group' => 'A',
            'semester' => 'Ganjil 2024/2025',
        ]);
        $this->assertEquals(1, $import->getSuccessCount());
        $this->assertEquals(0, $import->getFailCount());
    }

    public function test_schedule_import_fails_gracefully_when_room_not_found(): void
    {
        $import = new ScheduleImport();

        $schedule = $import->model([
            'kode_mk' => 'MKW102',
            'nama_mk' => 'Pancasila',
            'sks' => 2,
            'tipe_mk' => 'MKWU',
            'nama_dosen' => 'Prof. Dr. Siti Rahmah',
            'nama_ruangan' => 'RUANG_TIDAK_ADA_999',
            'hari' => 'Selasa',
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:40',
            'semester' => 'Ganjil 2024/2025',
            'kelompok_kelas' => 'B',
        ]);

        $this->assertNull($schedule);
        $this->assertEquals(0, $import->getSuccessCount());
        $this->assertEquals(1, $import->getFailCount());
        $this->assertNotEmpty($import->getErrors());
    }
}
