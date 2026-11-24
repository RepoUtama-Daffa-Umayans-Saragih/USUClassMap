<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\CampusRoutingOverride;
use App\Models\FotocopyOrder;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\User;
use App\Observers\GitSnapshotObserver;
use App\Services\GitSnapshotSyncService;
use App\Services\Routing\CampusRoutingService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PhotocopySeeder;
use Database\Seeders\ServiceMasterSeeder;
use Database\Seeders\TrialAdminFotocopySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampusRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected CampusRoutingService $routingService;

    protected function setUp(): void
    {
        parent::setUp();

        GitSnapshotObserver::withoutSync(function () {
            $this->seed(AdminUserSeeder::class);
            $this->seed(ServiceMasterSeeder::class);
            app(GitSnapshotSyncService::class)->importAll(true);

            if (Building::count() === 0) {
                $this->seed(\Database\Seeders\BuildingSeeder::class);
            }
            if (PhotocopyPlace::count() === 0) {
                $this->seed(PhotocopySeeder::class);
                $this->seed(TrialAdminFotocopySeeder::class);
            }
        });

        $this->routingService = app(CampusRoutingService::class);
        $this->routingService->invalidateCache();
    }

    public function test_real_campus_pedestrian_routes_pass_zero_obstacle_geometry_audit(): void
    {
        $ftMesin = Building::where('code', 'FT-MESIN')->firstOrFail();
        $fmipaU4 = Building::where('code', 'FMIPA-U4')->firstOrFail();
        $gkb = Building::where('code', 'GKB')->firstOrFail();
        $trialFotocopy = PhotocopyPlace::where('slug', 'fotocopy-trial-admin')->firstOrFail();

        $pairs = [
            ['origin' => "building:{$ftMesin->id}", 'destination' => "building:{$fmipaU4->id}"],
            ['origin' => "building:{$ftMesin->id}", 'destination' => "fotocopy:{$trialFotocopy->id}"],
            ['origin' => "building:{$gkb->id}", 'destination' => "building:{$fmipaU4->id}"],
        ];

        foreach ($pairs as $pair) {
            $resp = $this->getJson('/api/route?' . http_build_query([
                'origin' => $pair['origin'],
                'destination' => $pair['destination'],
                'mode' => 'walking',
            ]));

            $resp->assertStatus(200)
                ->assertJsonPath('status', 'ok')
                ->assertJsonPath('mode', 'walking')
                ->assertJsonPath('geometry_audit.building_intersections', 0)
                ->assertJsonPath('geometry_audit.barrier_intersections', 0)
                ->assertJsonPath('geometry_audit.verified', true);

            $data = $resp->json();
            $this->assertGreaterThan(0, (int) ($data['distance_meters'] ?? 0));
            $this->assertGreaterThanOrEqual(2, count($data['steps'] ?? []));
            $this->assertNotEmpty($data['main_route_geojson']['geometry']['coordinates'] ?? []);
        }
    }

    public function test_custom_barrier_forces_detour_and_bridge_allows_legal_crossing(): void
    {
        $syntheticGraph = [
            'nodes' => [
                'N1' => ['id' => 'N1', 'lat' => 3.5620, 'lng' => 98.6560],
                'N2' => ['id' => 'N2', 'lat' => 3.5620, 'lng' => 98.6565],
                'S1' => ['id' => 'S1', 'lat' => 3.5600, 'lng' => 98.6560],
                'S2' => ['id' => 'S2', 'lat' => 3.5600, 'lng' => 98.6565],
            ],
            'edges' => [
                [
                    'id' => 'e_direct',
                    'u' => 'N1',
                    'v' => 'N2',
                    'distance_m' => 55.5,
                    'walkable' => true,
                    'drivable' => false,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'footway',
                    'name' => 'Jalur Utara',
                ],
                [
                    'id' => 'e_west',
                    'u' => 'N1',
                    'v' => 'S1',
                    'distance_m' => 222.0,
                    'walkable' => true,
                    'drivable' => false,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'footway',
                    'name' => 'Trotoar Barat',
                ],
                [
                    'id' => 'e_south',
                    'u' => 'S1',
                    'v' => 'S2',
                    'distance_m' => 55.5,
                    'walkable' => true,
                    'drivable' => false,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'footway',
                    'name' => 'Selasar Selatan',
                ],
                [
                    'id' => 'e_east',
                    'u' => 'S2',
                    'v' => 'N2',
                    'distance_m' => 222.0,
                    'walkable' => true,
                    'drivable' => false,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'footway',
                    'name' => 'Trotoar Timur',
                ],
            ],
            'obstacles' => [
                'buildings' => [],
                'barriers' => [],
            ],
            'stats' => [],
        ];

        $params = [
            'origin_type' => 'point',
            'origin_lat' => 3.5620,
            'origin_lng' => 98.6560,
            'dest_type' => 'point',
            'dest_lat' => 3.5620,
            'dest_lng' => 98.6565,
            'mode' => 'walking',
        ];

        // 1. Baseline takes short direct edge (~56 m)
        $baseline = $this->routingService->computeRoute($params, $syntheticGraph);
        $this->assertLessThan(90, $baseline['distance_meters']);

        // 2. Adding perpendicular canal barrier severs e_direct -> must detour via S1 -> S2 (> 300 m)
        GitSnapshotObserver::withoutSync(function () {
            CampusRoutingOverride::create([
                'type' => 'barrier',
                'name' => 'Parit Kampus Tes',
                'coordinates_json' => [
                    [98.65625, 3.5645],
                    [98.65625, 3.5604],
                ],
                'is_active' => true,
            ]);
        });
        $this->routingService->invalidateCache();

        $detoured = $this->routingService->computeRoute($params, $syntheticGraph);
        $this->assertGreaterThan(300, $detoured['distance_meters']);
        $this->assertSame(0, $detoured['geometry_audit']['barrier_intersections']);
        $this->assertTrue($detoured['geometry_audit']['verified']);

        // 3. Adding legal bridge crosses barrier cleanly (< 120 m) with verified geometry
        GitSnapshotObserver::withoutSync(function () {
            CampusRoutingOverride::create([
                'type' => 'bridge',
                'name' => 'Jembatan Penyeberangan Parit',
                'coordinates_json' => [
                    [98.65615, 3.5620],
                    [98.65635, 3.5620],
                ],
                'is_active' => true,
            ]);
        });
        $this->routingService->invalidateCache();

        $bridged = $this->routingService->computeRoute($params, $syntheticGraph);
        $this->assertLessThan(120, $bridged['distance_meters']);
        $this->assertTrue($bridged['geometry_audit']['verified']);
        $this->assertTrue(
            collect($bridged['steps'])->contains(static fn ($s) => ($s['maneuver'] ?? '') === 'bridge')
        );
    }

    public function test_driving_mode_and_routing_destinations_endpoint(): void
    {
        $destResp = $this->getJson('/api/routing/destinations');
        $destResp->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $destJson = $destResp->json();
        $this->assertGreaterThan(0, (int) ($destJson['buildings_count'] ?? 0));
        $this->assertGreaterThan(0, (int) ($destJson['fotocopy_count'] ?? 0));

        $ftMesin = Building::where('code', 'FT-MESIN')->firstOrFail();
        $gkb = Building::where('code', 'GKB')->firstOrFail();

        $drivingResp = $this->getJson('/api/route?' . http_build_query([
            'origin' => "building:{$ftMesin->id}",
            'destination' => "building:{$gkb->id}",
            'mode' => 'driving',
        ]));

        $drivingResp->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('mode', 'driving');

        $this->assertGreaterThan(0, (int) $drivingResp->json('distance_meters'));
    }

    public function test_admin_routing_editor_crud_is_rbac_protected(): void
    {
        $student = User::where('role', 'user')->firstOrFail();
        $superadmin = User::where('role', 'superadmin')->firstOrFail();

        // Guest blocked (401 JSON / 302 redirect web)
        $this->postJson('/admin-sementara-/map/routing-overrides', [])->assertStatus(401);
        $this->getJson('/admin-sementara-/map/routing-data')->assertStatus(401);
        $this->get('/admin-sementara-/map/routing-data')->assertStatus(302);

        // Non-admin user blocked
        $this->actingAs($student)
            ->postJson('/admin-sementara-/map/routing-overrides', [])
            ->assertStatus(403);

        GitSnapshotObserver::withoutSync(function () use ($superadmin) {
            // Superadmin loads routing data
            $this->actingAs($superadmin)
                ->getJson('/admin-sementara-/map/routing-data')
                ->assertStatus(200)
                ->assertJsonPath('success', true);

            // Superadmin creates walkway override
            $createResp = $this->actingAs($superadmin)
                ->postJson('/admin-sementara-/map/routing-overrides', [
                    'type' => 'walkway',
                    'name' => 'Selasar Pintas Uji',
                    'coordinates' => [
                        [98.6550, 3.5620],
                        [98.6554, 3.5621],
                    ],
                ]);
            $createResp->assertStatus(201)
                ->assertJsonPath('success', true);

            $overrideId = $createResp->json('override.id');
            $this->assertNotNull($overrideId);

            // Superadmin saves & resets entrance coordinate
            $building = Building::firstOrFail();
            $this->actingAs($superadmin)
                ->postJson('/admin-sementara-/map/routing-entrance', [
                    'entity_type' => 'building',
                    'entity_id' => $building->id,
                    'entrance_lat' => (float) $building->latitude + 0.0001,
                    'entrance_lng' => (float) $building->longitude + 0.0001,
                ])
                ->assertStatus(200)
                ->assertJsonPath('success', true);

            // Superadmin deletes override
            $this->actingAs($superadmin)
                ->deleteJson("/admin-sementara-/map/routing-overrides/{$overrideId}")
                ->assertStatus(200)
                ->assertJsonPath('success', true);

            $this->assertDatabaseMissing('campus_routing_overrides', ['id' => $overrideId]);
        });
    }

    public function test_public_pages_contain_zero_external_google_maps_navigation_redirects(): void
    {
        $trialFotocopy = PhotocopyPlace::where('slug', 'fotocopy-trial-admin')->firstOrFail();
        $trialStore = FotocopyStore::where('slug', 'fotocopy-trial-admin')->firstOrFail();
        $order = FotocopyOrder::where('store_id', $trialStore->id)->first();

        if (!$order) {
            GitSnapshotObserver::withoutSync(function () use ($trialStore, &$order) {
                $order = FotocopyOrder::create([
                    'order_code' => 'ORD-ROUTE-TEST-01',
                    'invoice_number' => 'INV-ROUTE-TEST-01',
                    'pickup_code' => 'PK-991',
                    'store_id' => $trialStore->id,
                    'customer_name' => 'Mahasiswa Uji Rute',
                    'customer_phone' => '628123456789',
                    'customer_whatsapp' => '628123456789',
                    'payment_method' => 'cash',
                    'payment_channel' => 'cash_at_store',
                    'payment_status' => 'unpaid',
                    'order_status' => 'pending',
                    'total_price' => 1000,
                ]);
            });
        }

        $urls = [
            '/map',
            '/fotocopy',
            "/fotocopy/{$trialFotocopy->id}",
            "/fotocopy/{$trialFotocopy->id}/order",
            "/fotocopy/orders/{$order->order_code}/payment",
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
            $html = $response->getContent();
            $this->assertDoesNotMatchRegularExpression('/https?:\/\/(www\.)?(google\.com\/maps|maps\.google\.com)/i', $html);
        }
    }

    public function test_fasilkom_to_fib_walking_and_driving_have_distinct_engines_and_clean_geometry(): void
    {
        $fib = Building::where('code', 'FIB')->firstOrFail();

        $walkResp = $this->getJson('/api/route?' . http_build_query([
            'origin' => '3.5625,98.6596',
            'destination' => "building:{$fib->id}",
            'mode' => 'walking',
        ]));

        $walkResp->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('mode', 'walking')
            ->assertJsonPath('engine_source', 'Graf Pejalan Kaki USU')
            ->assertJsonPath('geometry_audit.building_intersections', 0)
            ->assertJsonPath('geometry_audit.barrier_intersections', 0)
            ->assertJsonPath('geometry_audit.verified', true);

        $walkData = $walkResp->json();
        $this->assertGreaterThan(100, (int) $walkData['distance_meters']);
        $this->assertGreaterThanOrEqual(2, count($walkData['steps'] ?? []));
        foreach ($walkData['steps'] ?? [] as $step) {
            $this->assertStringNotContainsString('sejauh 2 m', strtolower((string) ($step['instruction'] ?? '')));
        }

        $driveResp = $this->getJson('/api/route?' . http_build_query([
            'origin' => '3.5625,98.6596',
            'destination' => "building:{$fib->id}",
            'mode' => 'driving',
        ]));

        $driveResp->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('mode', 'driving')
            ->assertJsonPath('engine_source', 'Graf Jalan Kendaraan USU')
            ->assertJsonPath('geometry_audit.building_intersections', 0)
            ->assertJsonPath('geometry_audit.barrier_intersections', 0)
            ->assertJsonPath('geometry_audit.verified', true);

        $driveData = $driveResp->json();
        $this->assertGreaterThan(100, (int) $driveData['distance_meters']);
    }

    public function test_off_campus_medan_origin_uses_hybrid_road_routing_and_osrm_fallback_never_straight_line(): void
    {
        $fib = Building::where('code', 'FIB')->firstOrFail();
        config(['services.mapbox.token' => 'test-mapbox-routing-token']);

        $mockRoadCoords = [
            [98.6435, 3.5685],
            [98.6462, 3.5678],
            [98.6490, 3.5664],
            [98.6512, 3.5648],
            [98.6531, 3.5629],
        ];
        $mockSteps = [
            [
                'distance' => 450,
                'name' => 'Jl. Dr. Mansyur',
                'maneuver' => [
                    'instruction' => 'Berjalan ke timur di Jl. Dr. Mansyur',
                    'type' => 'depart',
                    'modifier' => 'right',
                    'location' => [98.6435, 3.5685],
                ],
            ],
            [
                'distance' => 620,
                'name' => 'Jl. Almamater',
                'maneuver' => [
                    'instruction' => 'Belok kanan ke Jl. Almamater',
                    'type' => 'turn',
                    'modifier' => 'right',
                    'location' => [98.6490, 3.5664],
                ],
            ],
        ];

        // Sub-case 1: Mapbox Directions succeeds -> hybrid Medan + USU route
        Http::swap(new HttpFactory());
        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [[
                    'distance' => 1150,
                    'geometry' => ['type' => 'LineString', 'coordinates' => $mockRoadCoords],
                    'legs' => [['steps' => $mockSteps]],
                ]],
            ], 200),
            'router.project-osrm.org/*' => Http::response([], 500),
        ]);

        $mapboxResp = $this->getJson('/api/route?' . http_build_query([
            'origin' => '3.5685,98.6435',
            'destination' => "building:{$fib->id}",
            'mode' => 'walking',
        ]));

        $mapboxResp->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('engine_source', 'Rute Jalan Medan + Graf Kampus USU');
        $this->assertGreaterThan(500, (int) $mapboxResp->json('distance_meters'));
        $this->assertGreaterThanOrEqual(5, count($mapboxResp->json('main_route_geojson.geometry.coordinates') ?? []));

        // Sub-case 2: Mapbox fails (500) -> OSRM public road fallback succeeds
        Http::swap(new HttpFactory());
        Http::fake([
            'api.mapbox.com/*' => Http::response(['message' => 'rate limited'], 500),
            'router.project-osrm.org/*' => Http::response([
                'routes' => [[
                    'distance' => 1180,
                    'geometry' => ['type' => 'LineString', 'coordinates' => $mockRoadCoords],
                    'legs' => [['steps' => $mockSteps]],
                ]],
            ], 200),
        ]);

        $osrmResp = $this->getJson('/api/route?' . http_build_query([
            'origin' => '3.5685,98.6435',
            'destination' => "building:{$fib->id}",
            'mode' => 'walking',
        ]));

        $osrmResp->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('engine_source', 'Rute Jalan Medan + Graf Kampus USU');
        $this->assertGreaterThan(500, (int) $osrmResp->json('distance_meters'));

        // Sub-case 3: Both Mapbox & OSRM fail -> HTTP 503 external_routing_unavailable (NEVER straight line)
        Http::swap(new HttpFactory());
        Http::fake([
            'api.mapbox.com/*' => Http::response([], 500),
            'router.project-osrm.org/*' => Http::response([], 500),
        ]);

        $downResp = $this->getJson('/api/route?' . http_build_query([
            'origin' => '3.5685,98.6435',
            'destination' => "building:{$fib->id}",
            'mode' => 'walking',
        ]));

        $downResp->assertStatus(503)
            ->assertJsonPath('status', 'external_routing_unavailable');
    }

    public function test_invalid_empty_zero_and_same_endpoints_are_rejected_and_nearby_gps_reports_arrival(): void
    {
        $fib = Building::where('code', 'FIB')->firstOrFail();

        // Empty origin
        $this->getJson('/api/route?destination=building:' . $fib->id)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        // Null-island (0,0) origin
        $this->getJson('/api/route?' . http_build_query([
            'origin_lat' => 0,
            'origin_lng' => 0,
            'destination' => "building:{$fib->id}",
        ]))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        // Same building as origin & destination
        $this->getJson('/api/route?' . http_build_query([
            'origin' => "building:{$fib->id}",
            'destination' => "building:{$fib->id}",
        ]))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        // Genuinely within < 20m of FIB -> clean arrival message instead of fake 2m driving/walking steps
        $nearResp = $this->getJson('/api/route?' . http_build_query([
            'origin_type' => 'point',
            'origin_lat' => (float) $fib->latitude + 0.00004, // ~4.4m north of FIB
            'origin_lng' => (float) $fib->longitude,
            'destination' => "building:{$fib->id}",
            'mode' => 'driving',
        ]));

        $nearResp->assertStatus(200)
            ->assertJsonPath('status', 'already_at_destination')
            ->assertJsonPath('already_at_destination', true);
    }

    public function test_driving_mode_never_uses_pedestrian_only_footway_or_steps(): void
    {
        $syntheticGraph = [
            'nodes' => [
                'A' => ['id' => 'A', 'lat' => 3.5620, 'lng' => 98.6560],
                'B' => ['id' => 'B', 'lat' => 3.5620, 'lng' => 98.6565],
                'R1' => ['id' => 'R1', 'lat' => 3.5605, 'lng' => 98.6560],
                'R2' => ['id' => 'R2', 'lat' => 3.5605, 'lng' => 98.6565],
            ],
            'edges' => [
                [
                    'id' => 'e_ped_shortcut',
                    'u' => 'A',
                    'v' => 'B',
                    'distance_m' => 55.5,
                    'walkable' => true,
                    'drivable' => false,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'footway',
                    'name' => 'Selasar Khusus Pejalan Kaki',
                ],
                [
                    'id' => 'e_ped_steps_corrupt',
                    // Even if mistakenly marked drivable=true in raw data, steps/footway MUST be blocked in driving mode
                    'u' => 'A',
                    'v' => 'B',
                    'distance_m' => 56.0,
                    'walkable' => true,
                    'drivable' => true,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'steps',
                    'name' => 'Tangga Koridor',
                ],
                [
                    'id' => 'e_road_w',
                    'u' => 'A',
                    'v' => 'R1',
                    'distance_m' => 167.0,
                    'walkable' => true,
                    'drivable' => true,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'residential',
                    'name' => 'Jl. Kampus Barat',
                ],
                [
                    'id' => 'e_road_s',
                    'u' => 'R1',
                    'v' => 'R2',
                    'distance_m' => 55.5,
                    'walkable' => true,
                    'drivable' => true,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'residential',
                    'name' => 'Jl. Kampus Selatan',
                ],
                [
                    'id' => 'e_road_e',
                    'u' => 'R2',
                    'v' => 'B',
                    'distance_m' => 167.0,
                    'walkable' => true,
                    'drivable' => true,
                    'oneway_vehicle' => 0,
                    'is_bridge' => false,
                    'road_class' => 'residential',
                    'name' => 'Jl. Kampus Timur',
                ],
            ],
            'obstacles' => [
                'buildings' => [],
                'barriers' => [],
            ],
            'stats' => [],
        ];

        $walk = $this->routingService->computeRoute([
            'origin_type' => 'point',
            'origin_lat' => 3.5620,
            'origin_lng' => 98.6560,
            'dest_type' => 'point',
            'dest_lat' => 3.5620,
            'dest_lng' => 98.6565,
            'mode' => 'walking',
        ], $syntheticGraph);

        $this->assertLessThan(90, $walk['distance_meters']);
        $this->assertSame('Graf Pejalan Kaki USU', $walk['engine_source']);

        $drive = $this->routingService->computeRoute([
            'origin_type' => 'point',
            'origin_lat' => 3.5620,
            'origin_lng' => 98.6560,
            'dest_type' => 'point',
            'dest_lat' => 3.5620,
            'dest_lng' => 98.6565,
            'mode' => 'driving',
        ], $syntheticGraph);

        $this->assertGreaterThan(300, $drive['distance_meters']);
        $this->assertSame('Graf Jalan Kendaraan USU', $drive['engine_source']);
        foreach ($drive['steps'] ?? [] as $step) {
            $instr = strtolower((string) ($step['instruction'] ?? ''));
            $this->assertStringNotContainsString('tangga', $instr);
            $this->assertStringNotContainsString('selasar khusus pejalan kaki', $instr);
        }
    }
}
