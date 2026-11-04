<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase1To3ComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Building $building;
    protected Room $room;
    protected Lecturer $lecturer;
    protected Course $course;

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

        $this->lecturer = Lecturer::create([
            'name' => 'Dr. Ahmad Fauzi, M.A.',
            'nip' => '198501012010121001',
            'email' => 'ahmad.fauzi@usu.ac.id',
            'phone' => '081234567890',
        ]);

        $this->course = Course::create([
            'code' => 'UNI101',
            'name' => 'Pendidikan Agama Islam',
            'credits' => 2,
            'type' => 'MKWU',
        ]);
    }

    /**
     * TAHAP 1: Public views test
     */
    public function test_public_pages_are_accessible(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('USUClassMap');

        $response = $this->get('/map');
        $response->assertStatus(200);
        $response->assertSee('Cari mata kuliah atau dosen');

        $response = $this->get('/bookmarks');
        $response->assertStatus(200);
        $response->assertSee('Jadwal Tersimpan');

        $response = $this->get('/faq');
        $response->assertStatus(200);
        $response->assertSee('Pertanyaan yang Sering Diajukan');
    }

    /**
     * TAHAP 2: Auth and role middleware test
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('SATU');
    }

    public function test_unauthenticated_user_cannot_access_admin_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_login_and_access_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@usu.ac.id',
            'password' => 'Admin123!',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);

        $dashboardResponse = $this->get('/admin/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Dashboard');
        $dashboardResponse->assertSee('Admin Panel');
    }

    /**
     * TAHAP 3: CRUD operations test
     */
    public function test_admin_can_create_building(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/buildings', [
            'name' => 'Gedung Serbaguna',
            'code' => 'GSG',
            'latitude' => 3.56000000,
            'longitude' => 98.65000000,
            'description' => 'Auditorium utama',
            'floor_count' => 2,
        ]);

        $response->assertRedirect(route('admin.buildings.index'));
        $this->assertDatabaseHas('buildings', ['code' => 'GSG']);
    }

    public function test_admin_can_create_room_with_360_photo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('room_panorama.jpg', 2000, 1000);

        $response = $this->actingAs($this->admin)->post('/admin/rooms', [
            'building_id' => $this->building->id,
            'name' => 'GK1-102',
            'floor' => 1,
            'capacity' => 50,
            'photo_360' => $file,
        ]);

        $response->assertRedirect(route('admin.rooms.index'));
        $this->assertDatabaseHas('rooms', ['name' => 'GK1-102']);

        $room = Room::where('name', 'GK1-102')->first();
        $this->assertNotNull($room->photo_360_path);
        Storage::disk('public')->assertExists($room->photo_360_path);
    }

    public function test_admin_can_create_lecturer(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/lecturers', [
            'name' => 'Dr. Budi Utomo, M.Kom.',
            'nip' => '198702022012121002',
            'email' => 'budi.utomo@usu.ac.id',
            'phone' => '081299998888',
        ]);

        $response->assertRedirect(route('admin.lecturers.index'));
        $this->assertDatabaseHas('lecturers', ['name' => 'Dr. Budi Utomo, M.Kom.']);
    }

    public function test_admin_can_create_course(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/courses', [
            'code' => 'UNI102',
            'name' => 'Pendidikan Kewarganegaraan',
            'credits' => 2,
            'type' => 'MKWU',
        ]);

        $response->assertRedirect(route('admin.courses.index'));
        $this->assertDatabaseHas('courses', ['code' => 'UNI102']);
    }

    public function test_admin_can_create_schedule(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/schedules', [
            'course_id' => $this->course->id,
            'lecturer_id' => $this->lecturer->id,
            'room_id' => $this->room->id,
            'day' => 'Senin',
            'time_start' => '08:00',
            'time_end' => '09:40',
            'semester' => 'Ganjil 2024/2025',
            'class_group' => 'A',
        ]);

        $response->assertRedirect(route('admin.schedules.index'));
        $this->assertDatabaseHas('schedules', [
            'course_id' => $this->course->id,
            'day' => 'Senin',
            'class_group' => 'A',
        ]);
    }

    public function test_admin_can_download_excel_template(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/import/download-template');
        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }
}
