<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\PhotocopyPlace;
use App\Observers\GitSnapshotObserver;
use App\Services\GitSnapshotSyncService;
use App\Services\Search\QueryIntentParserService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\BuildingSeeder;
use Database\Seeders\PhotocopySeeder;
use Database\Seeders\ServiceMasterSeeder;
use Database\Seeders\TrialAdminFotocopySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartSearchAndAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QueryIntentParserService::flushCache();

        GitSnapshotObserver::withoutSync(function (): void {
            $this->seed(AdminUserSeeder::class);
            $this->seed(ServiceMasterSeeder::class);
            app(GitSnapshotSyncService::class)->importAll(true);

            if (Building::count() === 0) {
                $this->seed(BuildingSeeder::class);
            }
            if (PhotocopyPlace::count() === 0) {
                $this->seed(PhotocopySeeder::class);
                $this->seed(TrialAdminFotocopySeeder::class);
            }
        });
    }

    public function test_query_intent_parser_normalizes_typos_synonyms_and_extracts_modifiers(): void
    {
        /** @var QueryIntentParserService $parser */
        $parser = app(QueryIntentParserService::class);

        // 1. Typo & synonym normalization + max_price parsing ("cari bolpen murah d bawah 2rb")
        $p1 = $parser->parse('cari bolpen murah d bawah 2rb');
        $this->assertSame('smart_fotocopy_search', $p1['primary_intent']);
        $this->assertContains('pulpen', $p1['item_terms']);
        $this->assertSame('cheapest', $p1['price_preference']);
        $this->assertSame(2000, $p1['max_price']);

        // 2. Multi-attribute & proximity ("cari pulpen pink murah terdekat")
        $p2 = $parser->parse('cari pulpen pink murah terdekat');
        $this->assertSame('smart_fotocopy_search', $p2['primary_intent']);
        $this->assertContains('pulpen', $p2['item_terms']);
        $this->assertContains('pink', $p2['attributes']);
        $this->assertSame('cheapest', $p2['price_preference']);
        $this->assertSame('nearest', $p2['proximity_preference']);

        // 3. Spatial multi-constraint ("saya ingatnya kelas saya di depan FIB di samping FMIPA")
        $p3 = $parser->parse('saya ingatnya kelas saya di depan FIB di samping FMIPA');
        $this->assertSame('spatial_building_finder', $p3['primary_intent']);
        $this->assertCount(2, $p3['spatial_constraints']);
        $this->assertSame('front', $p3['spatial_constraints'][0]['relation']);
        $this->assertSame('side', $p3['spatial_constraints'][1]['relation']);

        // 4. Ambiguous wing disambiguation ("saya ingatnya kelas saya di depan FIB di samping fakultas B")
        $p4 = $parser->parse('saya ingatnya kelas saya di depan FIB di samping fakultas B');
        $this->assertSame('spatial_building_finder', $p4['primary_intent']);
        $this->assertCount(2, $p4['spatial_constraints']);
        $wingBuildings = $p4['spatial_constraints'][1]['buildings'] ?? [];
        $this->assertNotEmpty($wingBuildings);
        $this->assertSame('FIB-B', $wingBuildings[0]['code']);
    }

    public function test_smart_photocopy_search_handles_relaxed_attribute_fallback_honestly(): void
    {
        $response = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
            'q'   => 'cari pulpen pink murah terdekat',
            'lat' => 3.5612,
            'lng' => 98.6565,
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('relaxed_fallback_used', true);

        $data = $response->json();
        $this->assertNotEmpty($data['results']);
        $this->assertContains('pink', $data['missing_attributes'] ?? []);
        $this->assertNotEmpty($data['advisory_note']);

        // First result should be eceran pulpen (not 1 box grosir) with valid in-app actions & zero Google Maps URLs
        $top = $data['results'][0];
        $this->assertStringContainsStringIgnoringCase('pulpen', $top['name']);
        $this->assertFalse((bool) ($top['is_bulk'] ?? false));
        $this->assertStringContainsString('/fotocopy/' . $top['store']['id'] . '/order', $top['actions']['order_url']);
        $this->assertStringContainsString('/map?route_to=fotocopy:' . $top['store']['id'], $top['actions']['route_url']);
        $this->assertStringNotContainsString('google.com/maps', json_encode($data));
    }

    public function test_smart_photocopy_search_filters_by_max_price_and_sorts_by_nearest(): void
    {
        $priceResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
            'q'         => 'pulpen',
            'max_price' => 3000,
            'sort'      => 'cheapest',
        ]));

        $priceResp->assertStatus(200);
        $priceResults = $priceResp->json('results') ?? [];
        $this->assertNotEmpty($priceResults);
        foreach ($priceResults as $row) {
            $this->assertLessThanOrEqual(3000, (int) $row['price']);
        }

        $nearResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
            'q'    => 'pulpen',
            'lat'  => 3.5625,
            'lng'  => 98.6590,
            'sort' => 'nearest',
        ]));

        $nearResp->assertStatus(200);
        $nearResults = $nearResp->json('results') ?? [];
        $this->assertGreaterThanOrEqual(2, count($nearResults));
        for ($i = 1; $i < count($nearResults); $i++) {
            $this->assertGreaterThanOrEqual(
                (int) $nearResults[$i - 1]['store']['distance_meters'],
                (int) $nearResults[$i]['store']['distance_meters']
            );
        }
    }

    public function test_spatial_landmark_resolver_finds_candidates_for_multi_constraint_query(): void
    {
        // Case A: "saya ingatnya kelas saya di depan FIB di samping FMIPA"
        $respA = $this->getJson('/api/smart-search?' . http_build_query([
            'q' => 'saya ingatnya kelas saya di depan FIB di samping FMIPA',
        ]));

        $respA->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('intent', 'spatial_building_finder');

        $candidatesA = $respA->json('spatial.candidates') ?? [];
        $this->assertGreaterThanOrEqual(3, count($candidatesA));
        $this->assertLessThanOrEqual(5, count($candidatesA));

        $fibId = Building::where('code', 'FIB')->value('id');
        $fmipaIds = Building::whereIn('code', ['FMIPA-U4', 'FMIPA-U5', 'PERPUS-MIPA'])->pluck('id')->all();

        foreach ($candidatesA as $cand) {
            $this->assertNotEquals($fibId, $cand['building_id']);
            $this->assertNotContains($cand['building_id'], $fmipaIds);
            $this->assertGreaterThanOrEqual(1, (int) $cand['confidence_percent']);
            $this->assertLessThanOrEqual(100, (int) $cand['confidence_percent']);
            $this->assertNotEmpty($cand['spatial_reason']);
            $this->assertStringContainsString('/map?focus_building=' . $cand['building_id'], $cand['actions']['focus_url']);
            $this->assertStringContainsString('/map?route_to=building:' . $cand['building_id'], $cand['actions']['route_url']);
        }

        // Case B: "saya ingatnya kelas saya di depan FIB di samping fakultas B"
        $respB = $this->getJson('/api/smart-search?' . http_build_query([
            'q' => 'saya ingatnya kelas saya di depan FIB di samping fakultas B',
        ]));

        $respB->assertStatus(200)
            ->assertJsonPath('intent', 'spatial_building_finder');
        $candidatesB = $respB->json('spatial.candidates') ?? [];
        $this->assertGreaterThanOrEqual(3, count($candidatesB));
        $this->assertNotEmpty($respB->json('spatial.advisory_note'));
    }

    public function test_copilot_chat_returns_grounded_cards_for_fotocopy_and_spatial_queries(): void
    {
        // Force empty CLASSAI_API_KEY to verify 100% offline local grounded orchestration (HTTP 200)
        config(['copilot.api_key' => '']);

        // 1. Smart Fotocopy & ATK Query
        $chatFc = $this->postJson('/copilot/chat', [
            'message'      => 'cari pulpen pink murah terdekat',
            'history'      => [],
            'user_lat'     => 3.5612,
            'user_lng'     => 98.6565,
            'current_page' => '/map',
        ]);

        $chatFc->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('intent', 'smart_fotocopy_search');

        $fcData = $chatFc->json();
        $this->assertNotEmpty($fcData['reply']);
        $this->assertNotEmpty($fcData['advisory_note']);
        $this->assertNotEmpty($fcData['cards']);
        $this->assertSame('fotocopy_item', $fcData['cards'][0]['type']);
        $this->assertNotEmpty($fcData['actions']);

        // 2. Spatial Landmark Query
        $chatSp = $this->postJson('/copilot/chat', [
            'message'      => 'saya ingatnya kelas saya di depan FIB di samping FMIPA',
            'history'      => [],
            'current_page' => '/map',
        ]);

        $chatSp->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('intent', 'spatial_building_finder');

        $spData = $chatSp->json();
        $this->assertNotEmpty($spData['reply']);
        $this->assertGreaterThanOrEqual(3, count($spData['cards'] ?? []));
        $this->assertSame('spatial_building', $spData['cards'][0]['type']);
        $this->assertNotEmpty($spData['mapAction']['building_id']);
    }

    public function test_public_map_and_fotocopy_pages_render_without_js_syntax_errors(): void
    {
        $mapResp = $this->get('/map');
        $mapResp->assertStatus(200)
            ->assertSee('map-search-input', false);

        $firstBuildingId = Building::orderBy('id')->value('id');
        $mapFocusResp = $this->get('/map?focus_building=' . $firstBuildingId);
        $mapFocusResp->assertStatus(200);

        $fcResp = $this->get('/fotocopy');
        $fcResp->assertStatus(200)
            ->assertSee('usuFotocopySmartSearch', false)
            ->assertSee('Gunakan Lokasi GPS Saya', false);

        $fcQueryResp = $this->get('/fotocopy?' . http_build_query(['q' => 'cari pulpen pink murah terdekat']));
        $fcQueryResp->assertStatus(200);
        $fcHtml = $fcQueryResp->getContent();
        $this->assertStringContainsString('/map?route_to=fotocopy:', urldecode($fcHtml));
        $this->assertStringNotContainsString('https://maps.google.com', $fcHtml);
        $this->assertStringNotContainsString('google.com/maps/dir', $fcHtml);
    }
}
