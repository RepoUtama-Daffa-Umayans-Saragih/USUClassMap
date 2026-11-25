<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VirtualWalk\FibVirtualWalkGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FibVirtualWalkTest extends TestCase
{
    use RefreshDatabase;

    public function test_fib_walk_graph_has_67_active_scenes_valid_files_and_full_bfs_connectivity(): void
    {
        /** @var FibVirtualWalkGraphService $service */
        $service = app(FibVirtualWalkGraphService::class);
        $graph = $service->getGraph(false);

        $scenes = $graph['scenes'] ?? [];
        $this->assertCount(67, $scenes);
        $this->assertArrayNotHasKey('434', $scenes);
        $this->assertSame(67, $graph['stats']['active_scenes']);

        foreach ($scenes as $idKey => $sc) {
            $id = (int) $idKey;
            $this->assertGreaterThanOrEqual(428, $id);
            $this->assertLessThanOrEqual(495, $id);
            $this->assertNotSame(434, $id);
            $this->assertTrue((bool) $sc['file_exists'], "Missing public/storage file for scene #{$id}");
            $this->assertFileExists(
                storage_path('app/public/' . $sc['storage_path']),
                "Missing physical photo on disk for scene #{$id}"
            );
            $this->assertNotEmpty($sc['hotspots'], "Scene #{$id} has no outgoing connections");
        }

        $conn = $graph['stats']['connectivity'];
        $this->assertTrue($conn['is_fully_connected']);
        $this->assertSame(67, $conn['active_count']);
        $this->assertSame(67, $conn['reachable_count']);
        $this->assertSame([], $conn['unreachable_ids']);

        // Verify reachable paths from Pintu Masuk #495 to deep rooms across all buildings & upper floors
        foreach ([465, 447, 480, 428, 494] as $destId) {
            $path = $service->shortestPath(495, $destId);
            $this->assertNotEmpty($path, "Scene #495 cannot reach #{$destId}");
            $this->assertSame(495, $path[0]);
            $this->assertSame($destId, end($path));
        }
    }

    public function test_public_fib_virtual_walk_and_graph_api_return_http_200(): void
    {
        $resp = $this->get('/virtual-walk/fib');
        $resp->assertStatus(200)
            ->assertSee('fibVirtualWalkApp', false)
            ->assertSee('Jalan Terusan Pintu Masuk FIB', false);

        $respRoom = $this->get('/virtual-walk/fib?scene=465');
        $respRoom->assertStatus(200)
            ->assertSee('Ruang Kelas H-208', false);

        // Excluded scene 434 safely redirects/falls back to default start scene 495
        $respExcluded = $this->get('/virtual-walk/fib?scene=434');
        $respExcluded->assertStatus(200)
            ->assertSee('"initialSceneId":495', false);

        $apiResp = $this->getJson('/api/virtual-walk/fib/graph');
        $apiResp->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.stats.active_scenes', 67)
            ->assertJsonPath('data.stats.connectivity.is_fully_connected', true);
    }

    public function test_admin_fib_walk_calibration_routes_enforce_admin_authorization(): void
    {
        // Guest blocked
        $this->get('/admin/cek-foto/fib-walk')->assertRedirect('/login');
        $this->postJson('/admin/cek-foto/fib-walk/scene/435', ['label' => 'Test'])->assertStatus(401);

        // Regular student blocked
        $student = User::factory()->create(['role' => 'mahasiswa']);
        $this->actingAs($student)->get('/admin/cek-foto/fib-walk')->assertRedirect('/login');
        $this->actingAs($student)->postJson('/admin/cek-foto/fib-walk/scene/435', ['label' => 'Test'])->assertStatus(403);

        // Admin allowed
        $admin = User::factory()->create(['role' => 'admin_usuclassmap']);
        $this->actingAs($admin)->get('/admin/cek-foto/fib-walk')
            ->assertStatus(200)
            ->assertSee('Kalibrasi Panah Jalan FIB', false)
            ->assertSee('Set ke Tengah Kamera', false);

        // SuperAdmin allowed
        $super = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($super)->get('/admin/cek-foto/fib-walk')->assertStatus(200);
    }

    public function test_admin_can_save_calibration_and_manage_bidirectional_links(): void
    {
        /** @var FibVirtualWalkGraphService $service */
        $service = app(FibVirtualWalkGraphService::class);
        $service->getGraph(false);

        $runtimePath = $service->getRuntimePath();
        $snapshotPath = $service->getSnapshotPath();
        $origRuntime = is_file($runtimePath) ? file_get_contents($runtimePath) : null;
        $origSnapshot = is_file($snapshotPath) ? file_get_contents($snapshotPath) : null;

        try {
            $admin = User::factory()->create(['role' => 'admin_usuclassmap']);
            $graph = $service->getGraph(false);
            $scene435 = $graph['scenes']['435'];
            $hotspots = $scene435['hotspots'];
            $hotspots[0]['yaw'] = 42.5;
            $hotspots[0]['pitch'] = -6.5;

            $saveResp = $this->actingAs($admin)->postJson('/admin/cek-foto/fib-walk/scene/435', [
                'label'         => $scene435['label'],
                'zone'          => $scene435['zone'],
                'floor'         => $scene435['floor'],
                'type'          => $scene435['type'],
                'default_pitch' => -1.5,
                'default_yaw'   => 18.0,
                'hotspots'      => $hotspots,
            ]);

            $saveResp->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('scene.default_yaw', 18)
                ->assertJsonPath('scene.hotspots.0.yaw', 42.5);

            // Re-read from disk to prove persistence
            $reloaded = $service->getGraph(false);
            $this->assertEquals(42.5, $reloaded['scenes']['435']['hotspots'][0]['yaw']);
            $this->assertEquals(-6.5, $reloaded['scenes']['435']['hotspots'][0]['pitch']);

            // Add new bidirectional link 428 <-> 441
            $addResp = $this->actingAs($admin)->postJson('/admin/cek-foto/fib-walk/link', [
                'from_id' => 428,
                'to_id'   => 441,
                'yaw'     => 90.0,
                'pitch'   => -5.0,
                'kind'    => 'room',
            ]);
            $addResp->assertStatus(200)->assertJsonPath('status', 'success');

            $afterAdd = $service->getGraph(false);
            $targets428 = array_column($afterAdd['scenes']['428']['hotspots'], 'target_id');
            $targets441 = array_column($afterAdd['scenes']['441']['hotspots'], 'target_id');
            $this->assertContains(441, $targets428);
            $this->assertContains(428, $targets441);

            // Remove that link cleanly
            $delResp = $this->actingAs($admin)->deleteJson('/admin/cek-foto/fib-walk/link', [
                'from_id' => 428,
                'to_id'   => 441,
            ]);
            $delResp->assertStatus(200)->assertJsonPath('status', 'success');

            $afterDel = $service->getGraph(false);
            $this->assertNotContains(441, array_column($afterDel['scenes']['428']['hotspots'], 'target_id'));
            $this->assertNotContains(428, array_column($afterDel['scenes']['441']['hotspots'], 'target_id'));
        } finally {
            if ($origRuntime !== null) {
                file_put_contents($runtimePath, $origRuntime, LOCK_EX);
            }
            if ($origSnapshot !== null) {
                file_put_contents($snapshotPath, $origSnapshot, LOCK_EX);
            }
        }
    }

    public function test_legacy_admin_cek_foto_remains_accessible_with_fib_walk_link(): void
    {
        $resp = $this->get('/admin/cek-foto');
        $resp->assertStatus(200)
            ->assertSee('Mode Jalan Virtual 360° FIB (428–495)', false)
            ->assertSee('/admin/cek-foto/fib-walk', false);
    }

    public function test_v2_street_view_panorama_stream_spatial_spread_and_heading_continuity(): void
    {
        // Cached 4K panorama stream endpoint
        $panoResp = $this->get('/virtual-walk/fib/pano/436');
        $panoResp->assertStatus(200);
        $this->assertStringContainsString('image/jpeg', (string) $panoResp->headers->get('Content-Type'));

        $this->get('/virtual-walk/fib/pano/434')->assertStatus(404);
        $this->get('/virtual-walk/fib/pano/999')->assertStatus(404);

        // Public Street View UI contains new controls & spatial chevrons without duplicate bottom buttons
        $viewResp = $this->get('/virtual-walk/fib?scene=436');
        $viewResp->assertStatus(200)
            ->assertSee('fib-autowalk-btn', false)
            ->assertSee('fib-mini-compass', false)
            ->assertSee('fib-ground-chevron', false)
            ->assertDontSee('data-walk-action', false);

        /** @var FibVirtualWalkGraphService $service */
        $service = app(FibVirtualWalkGraphService::class);
        $graph = $service->getGraph(false);
        $this->assertGreaterThanOrEqual(2, $graph['version']);

        $scenes = $graph['scenes'];
        $wrapDiff = static function (float $a, float $b): float {
            $d = fmod(($a - $b) + 540.0, 360.0) - 180.0;
            return abs($d);
        };

        foreach ($scenes as $sc) {
            $this->assertNotEmpty($sc['pano_url']);
            $yaws = [];
            foreach ($sc['hotspots'] as $hs) {
                if ($hs['kind'] === 'walk') {
                    $this->assertGreaterThanOrEqual(-24.0, (float) $hs['pitch']);
                    $this->assertLessThanOrEqual(-18.0, (float) $hs['pitch']);
                }
                foreach ($yaws as $prevYaw) {
                    $this->assertGreaterThanOrEqual(
                        20.0,
                        $wrapDiff((float) $hs['yaw'], (float) $prevYaw),
                        "Hotspot overlap detected on scene #{$sc['id']}"
                    );
                }
                $yaws[] = (float) $hs['yaw'];
            }
        }

        // Verify forward corridor heading continuity along H-corridor & M-corridor
        $corridors = [
            [450, 451, 452, 453, 454, 455, 456, 457],
            [488, 489, 490, 491],
        ];
        foreach ($corridors as $chain) {
            for ($i = 0; $i < count($chain) - 2; $i++) {
                $curr = $chain[$i];
                $next = $chain[$i + 1];
                $after = $chain[$i + 2];

                $stepHs = collect($scenes[(string) $curr]['hotspots'])->firstWhere('target_id', $next);
                $this->assertNotNull($stepHs, "Missing hotspot {$curr} -> {$next}");

                $nextOutHs = collect($scenes[(string) $next]['hotspots'])->firstWhere('target_id', $after);
                $this->assertNotNull($nextOutHs, "Missing next hotspot {$next} -> {$after}");

                $this->assertLessThanOrEqual(
                    35.0,
                    $wrapDiff((float) $stepHs['target_yaw'], (float) $nextOutHs['yaw']),
                    "Heading continuity mismatch on {$curr} -> {$next} -> {$after}"
                );
            }
        }
    }
}
