<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminUserSeeder::class);
    }

    public function test_admin_can_authenticate_with_fixed_credentials_and_role(): void
    {
        $response = $this->post('/login', [
            'nim' => 'adminususclassmapi',
            'password' => 'adminusu123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));

        $admin = auth()->user();
        $this->assertEquals('admin_usuclassmap', $admin->role);
        $this->assertEquals('Admin USUStarterMap', $admin->name);
        $this->assertEquals('adminususclassmapi', $admin->email);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_admin_route_is_protected_against_guests(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admin_route_is_protected_against_regular_users(): void
    {
        $user = User::create([
            'name' => 'Mahasiswa Biasa',
            'email' => 'mhs@students.usu.ac.id',
            'nim' => '12345678',
            'semester' => '1',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admin_dashboard_renders_welcome_banner_metrics_and_admin_badge(): void
    {
        $admin = User::where('email', 'adminususclassmapi')->first();

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Selamat Datang di Admin Panel USUStarterMap');
        $response->assertSee('Total Jadwal');
        $response->assertSee('Total Dosen');
        $response->assertSee('Total Gedung');
        $response->assertSee('Total Ruangan');
        $response->assertSee(route('admin.schedules.index'));
        $response->assertSee(route('admin.buildings.index'));
        $response->assertSee(route('admin.rooms.index'));
        $response->assertSee('Tambah Jadwal Pertama');

        // Verify badge and profile texts in layout
        $response->assertSee('Admin USUStarterMap');
        $response->assertSee('adminususclassmapi');
        $response->assertDontSee('superadmin');
    }

    public function test_building_create_split_panel_page_renders_with_leaflet(): void
    {
        $admin = User::where('email', 'adminususclassmapi')->first();

        $response = $this->actingAs($admin)->get('/admin/buildings/create');

        $response->assertStatus(200);
        $response->assertSee('Tambah Gedung Baru');
        $response->assertSee('Latitude (Read-Only) 🔒');
        $response->assertSee('Longitude (Read-Only) 🔒');
        $response->assertSee('buildingMap');
        $response->assertSee('leaflet.js');
    }

    public function test_room_create_page_renders_with_searchable_select_and_drag_drop(): void
    {
        $admin = User::where('email', 'adminususclassmapi')->first();
        Building::create([
            'name' => 'Gedung FASILKOM-TI',
            'code' => 'FST-1',
            'latitude' => 3.5652417,
            'longitude' => 98.6568434,
            'floor_count' => 3,
        ]);

        $response = $this->actingAs($admin)->get('/admin/rooms/create');

        $response->assertStatus(200);
        $response->assertSee('Gedung FASILKOM-TI');
        $response->assertSee('Area Drag & Drop Bergaris Putus-putus', false);
        $response->assertSee('Foto Panorama 360 Derajat');
    }

    public function test_rooms_index_shows_photo_indicators(): void
    {
        $admin = User::where('email', 'adminususclassmapi')->first();
        $building = Building::create([
            'name' => 'Gedung FST',
            'code' => 'FST-02',
            'latitude' => 3.5652417,
            'longitude' => 98.6568434,
            'floor_count' => 3,
        ]);

        Room::create([
            'building_id' => $building->id,
            'name' => 'Lab 1',
            'floor' => 1,
            'capacity' => 30,
            'photo_360_path' => 'rooms/360/sample.jpg',
        ]);

        Room::create([
            'building_id' => $building->id,
            'name' => 'Lab 2',
            'floor' => 2,
            'capacity' => 40,
            'photo_360_path' => null,
        ]);

        $response = $this->actingAs($admin)->get('/admin/rooms');

        $response->assertStatus(200);
        $response->assertSee('Ada Foto');
        $response->assertSee('Kosong');
    }
}
