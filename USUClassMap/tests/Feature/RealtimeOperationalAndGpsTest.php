<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Database\Seeders\BuildingSeeder;
use Database\Seeders\PhotocopySeeder;
use Database\Seeders\ServiceMasterSeeder;
use Database\Seeders\TrialAdminFotocopySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeOperationalAndGpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceMasterSeeder::class);
        $this->seed(BuildingSeeder::class);
        $this->seed(PhotocopySeeder::class);
        $this->seed(TrialAdminFotocopySeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_buildings_status_at_0655_wib_monday_is_closed_and_red(): void
    {
        $nowWib = Carbon::create(2026, 11, 23, 6, 55, 0, 'Asia/Jakarta');
        $this->assertSame(Carbon::MONDAY, $nowWib->dayOfWeek);
        Carbon::setTestNow($nowWib);

        $response = $this->getJson('/api/buildings');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('evaluated_at_wib', '06:55');

        $buildings = $response->json('data');
        $this->assertNotEmpty($buildings);

        foreach ($buildings as $building) {
            $op = $building['operational_status'] ?? [];
            $this->assertFalse($op['is_open_now'] ?? true, "Building {$building['code']} should be closed at 06:55 WIB.");
            $this->assertSame('Tutup', $op['status_label'] ?? null);
            $this->assertSame('#DC2626', $op['marker_color'] ?? null);
        }
    }

    public function test_buildings_status_at_0705_wib_monday_is_open_and_green(): void
    {
        $nowWib = Carbon::create(2026, 11, 23, 7, 5, 0, 'Asia/Jakarta');
        $this->assertSame(Carbon::MONDAY, $nowWib->dayOfWeek);
        Carbon::setTestNow($nowWib);

        $response = $this->getJson('/api/buildings');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('evaluated_at_wib', '07:05');

        $buildings = $response->json('data');
        $this->assertNotEmpty($buildings);

        foreach ($buildings as $building) {
            $op = $building['operational_status'] ?? [];
            $this->assertTrue($op['is_open_now'] ?? false, "Building {$building['code']} should be open at 07:05 WIB.");
            $this->assertSame('Buka', $op['status_label'] ?? null);
            $this->assertSame('#10B981', $op['marker_color'] ?? null);
        }
    }

    public function test_buildings_status_at_1605_wib_monday_is_closed_and_red(): void
    {
        $nowWib = Carbon::create(2026, 11, 23, 16, 5, 0, 'Asia/Jakarta');
        $this->assertSame(Carbon::MONDAY, $nowWib->dayOfWeek);
        Carbon::setTestNow($nowWib);

        $response = $this->getJson('/api/buildings');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('evaluated_at_wib', '16:05');

        $buildings = $response->json('data');
        $this->assertNotEmpty($buildings);

        foreach ($buildings as $building) {
            $op = $building['operational_status'] ?? [];
            $this->assertFalse($op['is_open_now'] ?? true, "Building {$building['code']} should be closed at 16:05 WIB.");
            $this->assertSame('Tutup', $op['status_label'] ?? null);
            $this->assertSame('#DC2626', $op['marker_color'] ?? null);
        }
    }

    public function test_buildings_status_at_1000_wib_sunday_is_closed_and_red(): void
    {
        $nowWib = Carbon::create(2026, 11, 22, 10, 0, 0, 'Asia/Jakarta');
        $this->assertSame(Carbon::SUNDAY, $nowWib->dayOfWeek);
        Carbon::setTestNow($nowWib);

        $response = $this->getJson('/api/buildings');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('evaluated_at_wib', '10:00');

        $buildings = $response->json('data');
        $this->assertNotEmpty($buildings);

        foreach ($buildings as $building) {
            $op = $building['operational_status'] ?? [];
            $this->assertFalse($op['is_open_now'] ?? true, "Building {$building['code']} should be closed on Sunday.");
            $this->assertSame('Tutup', $op['status_label'] ?? null);
            $this->assertSame('#DC2626', $op['marker_color'] ?? null);
        }
    }

    public function test_fotocopy_trial_admin_status_at_0704_wib_monday_is_closed_and_red(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 7, 4, 0, 'Asia/Jakarta'));

        $response = $this->getJson('/api/copy-shops?per_page=50');
        $response->assertStatus(200);

        $trial = collect($response->json('data'))->firstWhere('name', 'Fotocopy Trial Admin');
        $this->assertNotNull($trial, 'Fotocopy Trial Admin must exist in /api/copy-shops.');

        $this->assertFalse($trial['operational_status']['is_open_now'] ?? true);
        $this->assertSame('Tutup', $trial['operational_status']['status_label'] ?? null);
        $this->assertSame('#DC2626', $trial['operational_status']['marker_color'] ?? null);
        $this->assertFalse($trial['status']['is_open_now'] ?? true);
        $this->assertSame('#DC2626', $trial['status']['marker_color'] ?? null);
    }

    public function test_fotocopy_trial_admin_status_at_0800_wib_monday_is_open_and_teal(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 8, 0, 0, 'Asia/Jakarta'));

        $response = $this->getJson('/api/copy-shops?per_page=50');
        $response->assertStatus(200);

        $trial = collect($response->json('data'))->firstWhere('name', 'Fotocopy Trial Admin');
        $this->assertNotNull($trial, 'Fotocopy Trial Admin must exist in /api/copy-shops.');

        $this->assertTrue($trial['operational_status']['is_open_now'] ?? false);
        $this->assertSame('Buka', $trial['operational_status']['status_label'] ?? null);
        $this->assertSame('#0D9488', $trial['operational_status']['marker_color'] ?? null);
        $this->assertTrue($trial['status']['is_open_now'] ?? false);
        $this->assertSame('#0D9488', $trial['status']['marker_color'] ?? null);
    }

    public function test_smart_fotocopy_search_uses_gps_anchor_and_ranks_nearest_store(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 10, 0, 0, 'Asia/Jakarta'));

        $response = $this->getJson('/api/fotocopy/smart-search?q=print+a4+termurah+terdekat&lat=3.56268&lng=98.65912');
        $response->assertStatus(200)
            ->assertJsonPath('anchor_type', 'gps')
            ->assertJsonPath('anchor.type', 'gps');

        $results = collect($response->json('results'));
        $this->assertNotEmpty($results);

        $trialMatch = $results->first(function (array $item) {
            return ($item['store']['name'] ?? '') === 'Fotocopy Trial Admin';
        });

        $this->assertNotNull($trialMatch, 'Fotocopy Trial Admin must be returned near GPS coordinates.');
        $this->assertLessThan(20, (float) ($trialMatch['store']['distance_meters'] ?? 999999));
    }
}
