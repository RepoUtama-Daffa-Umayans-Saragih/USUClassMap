<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6PublicFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $building = Building::create([
            'name' => 'Gedung Kuliah Bersama 1',
            'code' => 'GK1',
            'latitude' => 3.56524500,
            'longitude' => 98.65652000,
            'description' => 'Gedung utama perkuliahan MKWK & MKWU',
            'floor_count' => 3,
        ]);

        $room = Room::create([
            'building_id' => $building->id,
            'name' => 'GK1-101',
            'floor' => 1,
            'capacity' => 50,
            'photo_360_path' => null,
        ]);

        $lecturer = Lecturer::create([
            'name' => 'Prof. Dr. H. M. Arif Nasution, M.A.',
            'nip' => '196504151990031002',
            'email' => 'arif.nasution@usu.ac.id',
            'phone' => '081260112233',
        ]);

        $course = Course::create([
            'code' => 'UNI101',
            'name' => 'Pendidikan Agama Islam',
            'credits' => 2,
            'type' => 'MKWU',
        ]);

        Schedule::create([
            'course_id' => $course->id,
            'lecturer_id' => $lecturer->id,
            'room_id' => $room->id,
            'day' => 'Senin',
            'time_start' => '08:00',
            'time_end' => '09:40',
            'semester' => 'Ganjil 2024/2025',
            'class_group' => 'A',
        ]);
    }

    /**
     * Test 1: Verify FAQ page rendering and 8 FAQ items from FaqController
     */
    public function test_faq_page_renders_with_questions_and_answers(): void
    {
        $response = $this->get('/faq');

        $response->assertStatus(200);
        $response->assertSee('Pertanyaan yang Sering Diajukan');
        $response->assertSee('Pusat Bantuan');
        $response->assertSee('Apa itu aplikasi USUClassMap?');
        $response->assertSee('Bagaimana cara mencari jadwal kuliah saya?');
        $response->assertSee('Apa itu Tur Visual 360°');
        $response->assertSee('Bagaimana cara mendapatkan rute petunjuk arah');
        $response->assertSee('Bagaimana cara menyimpan jadwal favorit saya ke Bookmark?');
        $response->assertSee('Bagaimana data jadwal diproses di USUClassMap?');
        $response->assertSee('Apakah mahasiswa perlu login');
        $response->assertSee('Jadwal kuliah saya tidak ditemukan');
    }

    /**
     * Test 2: Verify Bookmarks page rendering and UI elements
     */
    public function test_bookmarks_page_renders_properly(): void
    {
        $response = $this->get('/bookmarks');

        $response->assertStatus(200);
        $response->assertSee('Jadwal Tersimpan');
        $response->assertSee('Jadwal Favorit');
        $response->assertSee('bookmarks-list-container', false);
        $response->assertSee('empty-bookmarks-state', false);
    }

    /**
     * Test 3: Verify Map page has recent searches container and search input
     */
    public function test_map_page_contains_recent_searches_section(): void
    {
        $response = $this->get('/map');

        $response->assertStatus(200);
        $response->assertSee('map-search-input', false);
        $response->assertSee('recent-searches-container', false);
        $response->assertSee('day-filters', false);
        $response->assertSee('search-results', false);
    }

    /**
     * Test 4: Verify custom 404 page renders with USU green branding
     */
    public function test_custom_404_error_page_renders(): void
    {
        $response = $this->get('/non-existent-page-url-404-check');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Halaman Tidak Ditemukan');
        $response->assertSee('Kembali ke Peta');
    }
}
