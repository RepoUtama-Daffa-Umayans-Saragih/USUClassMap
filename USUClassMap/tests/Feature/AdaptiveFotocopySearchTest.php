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

class AdaptiveFotocopySearchTest extends TestCase
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

    public function test_store_mode_classification_and_rich_store_cards_payload(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 10, 0, 0, 'Asia/Jakarta'));

        try {
            $activeCount = PhotocopyPlace::where('is_active', true)->count();

            // 1. Empty query -> store mode returning all active stores (not truncated at 18)
            $emptyResp = $this->getJson('/api/fotocopy/smart-search?q=');
            $emptyResp->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('search_mode', 'store')
                ->assertJsonPath('intent_summary.mode_label', 'Tempat Fotokopi');
            $emptyJson = $emptyResp->json();
            $this->assertCount($activeCount, $emptyJson['stores'] ?? []);
            $this->assertSame([], $emptyJson['mixed_groups'] ?? null);

            // 2. Location-only query ("deket teknik") -> store mode with Pak Budi FT #1 & rich store schema
            $ftResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => 'deket teknik']));
            $ftResp->assertStatus(200)
                ->assertJsonPath('search_mode', 'store')
                ->assertJsonPath('anchor.source', 'landmark');
            $ftStores = $ftResp->json('stores') ?? [];
            $this->assertNotEmpty($ftStores);
            $this->assertSame('Fotocopy Pak Budi FT', $ftStores[0]['name'] ?? null);
            foreach (['id', 'name', 'slug', 'address', 'photo_url', 'rating', 'distance_m', 'walking_minutes', 'anchor_label', 'is_open_now', 'status_label', 'hours_label', 'starting_price', 'service_tags', 'matching_items_count', 'order_url', 'detail_url', 'map_route_url'] as $key) {
                $this->assertArrayHasKey($key, $ftStores[0], "Missing store card key: {$key}");
            }
            $this->assertLessThanOrEqual(4, count($ftStores[0]['service_tags'] ?? []));

            // 3. Specific store query ("USU Press") -> store mode with 1 store & highlight terms
            $usuResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => 'USU Press']));
            $usuResp->assertStatus(200)
                ->assertJsonPath('search_mode', 'store')
                ->assertJsonPath('matched_stores_count', 1);
            $this->assertStringContainsString('USU Press', (string) ($usuResp->json('stores.0.name') ?? ''));
            $this->assertNotEmpty($usuResp->json('intent_summary.highlight_terms'));

            // 4. Generic open-now store query ("fotocopy buka sekarang") -> store mode, only open stores
            $openResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => 'fotocopy buka sekarang']));
            $openResp->assertStatus(200)
                ->assertJsonPath('search_mode', 'store');
            $openStores = $openResp->json('stores') ?? [];
            $this->assertNotEmpty($openStores);
            foreach ($openStores as $st) {
                $this->assertTrue((bool) ($st['is_open_now'] ?? false));
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_product_mode_classification_highlight_terms_and_badges(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 10, 0, 0, 'Asia/Jakarta'));

        try {
            // 1. "pulpen pink murah terdekat" -> product mode, highlight_terms & distinct highlight_badges
            $pinkResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
                'q' => 'pulpen pink murah terdekat',
                'lat' => 3.5625,
                'lng' => 98.6590,
            ]));
            $pinkResp->assertStatus(200)
                ->assertJsonPath('search_mode', 'product')
                ->assertJsonPath('intent_summary.mode_label', 'Produk & Jasa');
            $pinkJson = $pinkResp->json();
            $this->assertNotEmpty($pinkJson['items'] ?? []);
            $this->assertSame([], $pinkJson['mixed_groups'] ?? null);

            $hlTerms = $pinkJson['intent_summary']['highlight_terms'] ?? [];
            $this->assertContains('pulpen', $hlTerms);
            $this->assertContains('pink', $hlTerms);

            $badges = array_values(array_filter(array_map(
                fn (array $it) => $it['highlight_badge'] ?? null,
                $pinkJson['items']
            )));
            $this->assertNotEmpty($badges);
            foreach ($badges as $b) {
                $this->assertContains($b, ['Termurah', 'Terdekat', 'Paling Cocok']);
            }

            // 2. "tipec" -> product mode with normalized correction/tipe-x highlights
            $tipecResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => 'tipec']));
            $tipecResp->assertStatus(200)
                ->assertJsonPath('search_mode', 'product');
            $this->assertNotEmpty($tipecResp->json('items'));
            $tipecHl = $tipecResp->json('intent_summary.highlight_terms') ?? [];
            $this->assertTrue(
                in_array('tipe-x', $tipecHl, true) || in_array('correction', $tipecHl, true),
                'Expected tipe-x or correction in highlight_terms'
            );

            // 3. Other product queries
            foreach (['print warna murah', 'kertas hvs a4', 'jilid hardcover'] as $q) {
                $resp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => $q]));
                $resp->assertStatus(200)->assertJsonPath('search_mode', 'product');
                $this->assertNotEmpty($resp->json('items'), "Expected non-empty items for {$q}");
            }

            // 4. Max price auto-switches empty query from store -> product mode
            $maxResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
                'q' => '',
                'max_price' => 5000,
            ]));
            $maxResp->assertStatus(200)
                ->assertJsonPath('search_mode', 'product');
            $maxItems = $maxResp->json('items') ?? [];
            $this->assertNotEmpty($maxItems);
            foreach ($maxItems as $it) {
                $this->assertLessThanOrEqual(5000, (int) $it['price']);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_mixed_mode_classification_and_store_grouped_combo_cards(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 10, 0, 0, 'Asia/Jakarta'));

        try {
            // 1. Product + Faculty Location ("pulpen pink deket teknik")
            $mixFt = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
                'q' => 'pulpen pink deket teknik',
            ]));
            $mixFt->assertStatus(200)
                ->assertJsonPath('search_mode', 'mixed')
                ->assertJsonPath('intent_summary.mode_label', 'Produk + Lokasi')
                ->assertJsonPath('anchor.source', 'landmark');

            $ftGroups = $mixFt->json('mixed_groups') ?? [];
            $this->assertNotEmpty($ftGroups);
            $firstGrp = $ftGroups[0];
            $this->assertArrayHasKey('store', $firstGrp);
            $this->assertArrayHasKey('matched_items', $firstGrp);
            $this->assertArrayHasKey('total_matched_items', $firstGrp);
            $this->assertArrayHasKey('lowest_matched_price', $firstGrp);
            $this->assertNotEmpty($firstGrp['matched_items']);
            $this->assertArrayHasKey('is_exact_match', $firstGrp['matched_items'][0]);

            // 2. Product + Specific Store ("print warna di USU Press")
            $mixUsu = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
                'q' => 'print warna di USU Press',
                'lat' => 3.5612,
                'lng' => 98.6565,
            ]));
            $mixUsu->assertStatus(200)
                ->assertJsonPath('search_mode', 'mixed');
            $usuGroups = $mixUsu->json('mixed_groups') ?? [];
            $this->assertCount(1, $usuGroups);
            $this->assertStringContainsString('USU Press', (string) ($usuGroups[0]['store']['name'] ?? ''));
            $this->assertGreaterThan(0, (int) ($usuGroups[0]['store']['distance_m'] ?? 0));
            $this->assertNotEmpty($usuGroups[0]['matched_items'] ?? []);

            // 3. Additional mixed queries ("jilid hardcover di USU Press", "print warna dekat FIB", "print warna di Pak Budi")
            foreach ([
                'jilid hardcover di USU Press' => 'USU Press',
                'print warna di Pak Budi' => 'Fotocopy Pak Budi FT',
            ] as $q => $expectedStoreName) {
                $r = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => $q]));
                $r->assertStatus(200)->assertJsonPath('search_mode', 'mixed');
                $groups = $r->json('mixed_groups') ?? [];
                $this->assertCount(1, $groups, "Expected single store group for {$q}");
                $this->assertStringContainsString($expectedStoreName, (string) ($groups[0]['store']['name'] ?? ''));
                $this->assertNotEmpty($groups[0]['matched_items'] ?? []);
            }

            $mixFib = $this->getJson('/api/fotocopy/smart-search?' . http_build_query(['q' => 'print warna dekat FIB']));
            $mixFib->assertStatus(200)
                ->assertJsonPath('search_mode', 'mixed')
                ->assertJsonPath('anchor.source', 'landmark');
            $this->assertNotEmpty($mixFib->json('mixed_groups'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_fotocopy_page_renders_single_adaptive_stage_without_duplicate_directory(): void
    {
        $page = $this->get('/fotocopy');
        $page->assertStatus(200)
            ->assertSee('fotocopyUnifiedSearch()', false)
            ->assertSee('smart-intent-bar', false)
            ->assertSee('adaptive-mode-tabs', false)
            ->assertSee('adaptive-store-grid', false)
            ->assertSee('adaptive-product-grid', false)
            ->assertSee('adaptive-mixed-grid', false)
            ->assertDontSee('Semua Tempat Fotocopy Kampus USU', false)
            ->assertDontSee('openScrollContainer', false)
            ->assertDontSee('Cari fotocopy, fakultas terdekat, jilid skripsi...', false);
    }
}
