<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\PhotocopyPlace;
use App\Observers\GitSnapshotObserver;
use App\Services\GitSnapshotSyncService;
use App\Services\Search\QueryIntentParserService;
use Carbon\Carbon;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\BuildingSeeder;
use Database\Seeders\PhotocopySeeder;
use Database\Seeders\RichFotocopyCatalogExpansionSeeder;
use Database\Seeders\ServiceMasterSeeder;
use Database\Seeders\TrialAdminFotocopySeeder;
use Database\Seeders\UsuPressFotocopySeeder;
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
            $this->seed(UsuPressFotocopySeeder::class);
            $this->seed(RichFotocopyCatalogExpansionSeeder::class);
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
        // 1. Pulpen pink kini tersedia sebagai kecocokan eksak di berbagai mitra
        $exactResponse = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
            'q'   => 'cari pulpen pink murah terdekat',
            'lat' => 3.5612,
            'lng' => 98.6565,
        ]));

        $exactResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('relaxed_fallback_used', false);

        $exactData = $exactResponse->json();
        $this->assertGreaterThanOrEqual(1, (int) ($exactData['exact_match_count'] ?? 0));
        $this->assertNotEmpty($exactData['results']);

        $top = $exactData['results'][0];
        $this->assertStringContainsStringIgnoringCase('pulpen', $top['name']);
        $this->assertTrue((bool) ($top['exact_attribute_match'] ?? false));
        $this->assertFalse((bool) ($top['is_bulk'] ?? false));
        $this->assertStringContainsString('/fotocopy/' . $top['store']['id'] . '/order', $top['actions']['order_url']);
        $this->assertStringContainsString('/map?route_to=fotocopy:' . $top['store']['id'], $top['actions']['route_url']);
        $this->assertStringNotContainsString('google.com/maps', json_encode($exactData));

        // 2. Atribut yang benar-benar absen memicu fallback jujur (relaxed_fallback_used = true)
        $relaxedResponse = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
            'q'   => 'cari pulpen emas berlian murah terdekat',
            'lat' => 3.5612,
            'lng' => 98.6565,
        ]));

        $relaxedResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('relaxed_fallback_used', true);

        $relaxedData = $relaxedResponse->json();
        $this->assertNotEmpty($relaxedData['results']);
        $this->assertContains('emas', $relaxedData['missing_attributes'] ?? []);
        $this->assertNotEmpty($relaxedData['advisory_note']);
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
        $this->assertNotEmpty($fcData['cards']);
        $this->assertSame('fotocopy_item', $fcData['cards'][0]['type']);
        $this->assertStringContainsStringIgnoringCase('pink', (string) ($fcData['cards'][0]['data']['name'] ?? ''));
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
            ->assertSee('map-search-input', false)
            ->assertSee('Tanya Ucup', false)
            ->assertSee('ucup-stand.webp', false)
            ->assertSee('ucup-avatar.webp', false)
            ->assertDontSee('Tanya Rexa', false);

        $firstBuildingId = Building::orderBy('id')->value('id');
        $mapFocusResp = $this->get('/map?focus_building=' . $firstBuildingId);
        $mapFocusResp->assertStatus(200);

        $fcResp = $this->get('/fotocopy');
        $fcResp->assertStatus(200)
            ->assertSee('usuFotocopySmartSearch', false)
            ->assertSee('Gunakan Lokasi GPS Saya', false)
            ->assertSee('Tanya Ucup', false)
            ->assertSee('ucup-stand.webp', false)
            ->assertSee('ucup-avatar.webp', false)
            ->assertDontSee('Tanya Rexa', false);

        $fcQueryResp = $this->get('/fotocopy?' . http_build_query(['q' => 'cari pulpen pink murah terdekat']));
        $fcQueryResp->assertStatus(200);
        $fcHtml = $fcQueryResp->getContent();
        $this->assertStringContainsString('/map?route_to=fotocopy:', urldecode($fcHtml));
        $this->assertStringNotContainsString('https://maps.google.com', $fcHtml);
        $this->assertStringNotContainsString('google.com/maps/dir', $fcHtml);
    }

    public function test_unified_fotocopy_search_replaces_hero_search_and_syncs_stores_and_products(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 10, 0, 0, 'Asia/Jakarta'));

        try {
            // 1. Halaman /fotocopy tidak lagi merender form pencarian & tombol pill di hero hijau
            $page = $this->get('/fotocopy');
            $page->assertStatus(200)
                ->assertSee('Integrasi Layanan Mahasiswa USU')
                ->assertSee('Layanan Fotocopy & Percetakan', false)
                ->assertDontSee('Cari fotocopy, fakultas terdekat, jilid skripsi...', false)
                ->assertDontSee('href="' . route('fotocopy.index', ['q' => 'Teknik']) . '"', false)
                ->assertDontSee('href="' . route('fotocopy.index', ['q' => 'FISIP']) . '"', false)
                ->assertDontSee('href="' . route('fotocopy.index', ['q' => 'Kedokteran']) . '"', false)
                ->assertDontSee('href="' . route('fotocopy.index', ['q' => 'Perpustakaan']) . '"', false)
                ->assertDontSee('href="' . route('fotocopy.index', ['q' => 'Skripsi']) . '"', false)
                // 2. Judul terpadu & 8 chip cepat tampil
                ->assertSee('Cari Tempat Fotocopy, Produk ATK, Print & Jilid Terbaik', false)
                ->assertSee('fotocopy buka sekarang', false)
                ->assertSee('fotocopy dekat FT', false)
                ->assertSee('fotocopy dekat Perpus', false)
                ->assertSee('fotocopy dekat FISIP', false)
                ->assertSee('USU Press', false)
                ->assertSee('cari pulpen pink murah terdekat', false)
                ->assertSee('print warna murah', false)
                ->assertSee('jilid hardcover skripsi', false);

            // 3. API Smart Search mengembalikan results + matched_store_ids akurat untuk toko, fakultas, jasa, status & produk
            $cases = [
                ['q' => 'fotocopy dekat FT', 'anchor' => 'landmark', 'topStore' => 'Fotocopy Pak Budi FT', 'exactCount' => null],
                ['q' => 'Dekat FT', 'anchor' => 'landmark', 'topStore' => 'Fotocopy Pak Budi FT', 'exactCount' => null],
                ['q' => 'fotocopy dekat Perpus', 'anchor' => 'landmark', 'topStore' => 'Berkah Abadi Copy & ATK Perpustakaan', 'exactCount' => null],
                ['q' => 'Dekat Perpus', 'anchor' => 'landmark', 'topStore' => 'Berkah Abadi Copy & ATK Perpustakaan', 'exactCount' => null],
                ['q' => 'fotocopy dekat FISIP', 'anchor' => 'landmark', 'topStore' => 'Fotocopy & Print Jaya FISIP', 'exactCount' => null],
                ['q' => 'Dekat FISIP', 'anchor' => 'landmark', 'topStore' => 'Fotocopy & Print Jaya FISIP', 'exactCount' => null],
                ['q' => 'Dekat FK', 'anchor' => 'landmark', 'topStore' => 'Digital Print Center FK & FIB', 'exactCount' => null],
                ['q' => 'Dekat FIB', 'anchor' => 'landmark', 'topStore' => 'Digital Print Center FK & FIB', 'exactCount' => null],
                ['q' => 'Dekat Fasilkom', 'anchor' => 'landmark', 'topStore' => 'Fotocopy Trial Admin', 'exactCount' => null],
                ['q' => 'USU Press', 'anchor' => null, 'topStore' => 'USU Press (USUpress Penerbit & Fotocopy)', 'exactCount' => 1],
                ['q' => 'Fotocopy Pak Budi', 'anchor' => null, 'topStore' => 'Fotocopy Pak Budi FT', 'exactCount' => 1],
                ['q' => 'Berkah Abadi', 'anchor' => null, 'topStore' => 'Berkah Abadi Copy & ATK Perpustakaan', 'exactCount' => 1],
                ['q' => 'Jilid Skripsi', 'anchor' => null, 'topStore' => null, 'exactCount' => null],
                ['q' => 'Print Warna', 'anchor' => null, 'topStore' => null, 'exactCount' => null],
                ['q' => 'fotocopy buka sekarang', 'anchor' => null, 'topStore' => null, 'exactCount' => null],
                ['q' => 'cari pulpen pink murah terdekat', 'anchor' => null, 'topStore' => null, 'exactCount' => null],
            ];

            foreach ($cases as $tc) {
                $resp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
                    'q' => $tc['q'],
                    'limit' => 18,
                ]));
                $resp->assertStatus(200)->assertJsonPath('status', 'success');
                $json = $resp->json();

                $this->assertNotEmpty($json['results'] ?? [], "Results empty for {$tc['q']}");
                $this->assertNotEmpty($json['matched_store_ids'] ?? [], "matched_store_ids empty for {$tc['q']}");

                if ($tc['anchor'] !== null) {
                    $this->assertSame($tc['anchor'], $json['anchor']['source'] ?? null, "Unexpected anchor for {$tc['q']}");
                }
                if ($tc['topStore'] !== null) {
                    $this->assertSame($tc['topStore'], $json['stores'][0]['name'] ?? null, "Unexpected top store for {$tc['q']}");
                }
                if ($tc['exactCount'] !== null) {
                    $this->assertSame($tc['exactCount'], $json['matched_stores_count'] ?? null, "Unexpected matched_stores_count for {$tc['q']}");
                }
            }

            $noneResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => 'xyznotfound123']));
            $noneResp->assertStatus(200)
                ->assertJsonPath('matched_stores_count', 0);

            // 4. SSR /fotocopy?q=... sinkron penuh dengan hasil Smart Search tanpa false-positive
            $ssrUsuPress = $this->get('/fotocopy?' . http_build_query(['q' => 'USU Press']));
            $ssrUsuPress->assertStatus(200)
                ->assertSee('USU Press (USUpress Penerbit &amp; Fotocopy)', false)
                ->assertSee('(1 hasil filter)', false)
                ->assertDontSee('NEXT PERCETAKAN', false);

            $ssrFt = $this->get('/fotocopy?' . http_build_query(['q' => 'fotocopy dekat FT']));
            $ssrFt->assertStatus(200)
                ->assertSee('Fotocopy Pak Budi FT', false)
                ->assertSee('hasil filter', false);
        } finally {
            Carbon::setTestNow();
        }
    }
}
