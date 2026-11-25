<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminFotokopi\ServiceController;
use App\Models\Building;
use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderFile;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use App\Models\User;
use App\Models\WhatsappDispatchLog;
use App\Observers\GitSnapshotObserver;
use App\Services\GitSnapshotSyncService;
use App\Services\Search\QueryIntentParserService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\BuildingSeeder;
use Database\Seeders\PhotocopySeeder;
use Database\Seeders\RichFotocopyCatalogExpansionSeeder;
use Database\Seeders\RichFotocopyOrdersDummySeeder;
use Database\Seeders\ServiceMasterSeeder;
use Database\Seeders\TrialAdminFotocopySeeder;
use Database\Seeders\UsuPressFotocopySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MitraImageAndRichDataTest extends TestCase
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

    public function test_store_crud_uploads_replaces_and_removes_image_synced_to_map(): void
    {
        Storage::fake('public');
        $superadmin = User::where('email', 'superadmin@usu.ac.id')->firstOrFail();

        // 1. Form create & edit harus memiliki multipart/form-data + input file + preview container
        $this->actingAs($superadmin)
            ->get(route('admin-fotokopi.stores.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('id="store-image-preview"', false)
            ->assertSee('name="image"', false);

        // 2. Upload foto baru saat CREATE mitra toko
        $upload1 = UploadedFile::fake()->image('gerai-qa-1.jpg', 400, 300);
        $this->actingAs($superadmin)->post(route('admin-fotokopi.stores.store'), [
            'name' => 'Mitra Foto Upload QA',
            'location_name' => 'Dekat Pintu 1 USU',
            'latitude' => 3.5621,
            'longitude' => 98.6562,
            'opening_hours' => '08:00 - 20:00',
            'operating_days' => 'Senin - Sabtu',
            'whatsapp' => '081277778888',
            'starting_price' => 300,
            'rating' => 4.9,
            'is_open' => 1,
            'description' => 'Toko QA verifikasi upload foto.',
            'image' => $upload1,
        ])->assertRedirect(route('admin-fotokopi.stores.index'));

        $store = FotocopyStore::where('name', 'Mitra Foto Upload QA')->firstOrFail();
        $place = PhotocopyPlace::where('slug', $store->slug)->firstOrFail();

        $this->assertNotEmpty($store->image_url);
        $this->assertStringStartsWith('/storage/stores/', $store->image_url);
        $this->assertSame($store->image_url, $place->image_url);
        Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $store->image_url), '/'));

        $copyShopsResp = $this->getJson('/api/copy-shops?per_page=200')->assertOk()->json();
        $apiPlace = collect($copyShopsResp['data'])->firstWhere('slug', $store->slug);
        $this->assertSame($store->image_url, $apiPlace['image_url'] ?? null);

        // 3. Edit form memuat pratinjau + opsi hapus gambar
        $this->actingAs($superadmin)
            ->get(route('admin-fotokopi.stores.edit', $store))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('id="store-image-preview"', false)
            ->assertSee('name="remove_image"', false);

        // 4. Ganti gambar saat UPDATE mitra toko
        $upload2 = UploadedFile::fake()->image('gerai-qa-2.webp', 500, 350);
        $this->actingAs($superadmin)->put(route('admin-fotokopi.stores.update', $store), [
            'name' => 'Mitra Foto Upload QA',
            'location_name' => 'Dekat Pintu 1 USU',
            'latitude' => 3.5621,
            'longitude' => 98.6562,
            'opening_hours' => '08:00 - 20:00',
            'operating_days' => 'Senin - Sabtu',
            'whatsapp' => '081277778888',
            'starting_price' => 300,
            'rating' => 4.9,
            'is_open' => 1,
            'description' => 'Foto diperbarui.',
            'image' => $upload2,
        ])->assertRedirect(route('admin-fotokopi.stores.index'));

        $store->refresh();
        $place->refresh();
        $this->assertSame($store->image_url, $place->image_url);
        Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $store->image_url), '/'));

        // 5. Hapus gambar via remove_image=1 juga membersihkan photocopy_places
        $this->actingAs($superadmin)->put(route('admin-fotokopi.stores.update', $store), [
            'name' => 'Mitra Foto Upload QA',
            'location_name' => 'Dekat Pintu 1 USU',
            'latitude' => 3.5621,
            'longitude' => 98.6562,
            'opening_hours' => '08:00 - 20:00',
            'operating_days' => 'Senin - Sabtu',
            'whatsapp' => '081277778888',
            'starting_price' => 300,
            'rating' => 4.9,
            'is_open' => 1,
            'remove_image' => 1,
        ])->assertRedirect(route('admin-fotokopi.stores.index'));

        $store->refresh();
        $place->refresh();
        $this->assertNull($store->image_url);
        $this->assertNull($place->image_url);
    }

    public function test_service_crud_uploads_and_fallbacks_sync_to_photocopy_items(): void
    {
        Storage::fake('public');
        $superadmin = User::where('email', 'superadmin@usu.ac.id')->firstOrFail();
        $store = FotocopyStore::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();
        $place = PhotocopyPlace::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();

        // 1. Form create & edit katalog memiliki preview + preset + fallback
        $this->actingAs($superadmin)
            ->get(route('admin-fotokopi.services.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('id="service-image-preview"', false)
            ->assertSee('name="image_preset"', false);

        // 2. Upload foto kustom saat CREATE katalog
        $prodUpload = UploadedFile::fake()->image('pulpen-qa.png', 300, 300);
        $this->actingAs($superadmin)->post(route('admin-fotokopi.services.store'), [
            'store_id' => $store->id,
            'name' => 'Pulpen Pink QA Custom Foto',
            'category' => 'ATK',
            'item_type' => 'barang',
            'sub_category' => 'Pulpen Pink',
            'brand' => 'Joyko',
            'stock' => 25,
            'price' => 4000,
            'unit' => 'pcs',
            'description' => 'Produk QA upload gambar katalog.',
            'is_available' => 1,
            'image' => $prodUpload,
        ])->assertRedirect(route('admin-fotokopi.services.index'));

        $srv1 = FotocopyService::where('store_id', $store->id)->where('name', 'Pulpen Pink QA Custom Foto')->firstOrFail();
        $itm1 = PhotocopyItem::where('photocopy_place_id', $place->id)->where('name', 'Pulpen Pink QA Custom Foto')->firstOrFail();

        $this->assertNotEmpty($srv1->image_path);
        $this->assertSame($srv1->image_path, $itm1->image_path);
        Storage::disk('public')->assertExists($srv1->image_path);

        // 3. Edit & ganti gambar kustom
        $prodUpload2 = UploadedFile::fake()->image('pulpen-qa-v2.jpg', 320, 320);
        $this->actingAs($superadmin)->put(route('admin-fotokopi.services.update', $srv1), [
            'store_id' => $store->id,
            'name' => 'Pulpen Pink QA Custom Foto',
            'category' => 'ATK',
            'item_type' => 'barang',
            'sub_category' => 'Pulpen Pink',
            'brand' => 'Joyko',
            'stock' => 25,
            'price' => 4500,
            'unit' => 'pcs',
            'is_available' => 1,
            'image' => $prodUpload2,
        ])->assertRedirect(route('admin-fotokopi.services.index'));

        $srv1->refresh();
        $itm1->refresh();
        $this->assertSame($srv1->image_path, $itm1->image_path);
        $this->assertSame(4500, (int) $itm1->price);
        Storage::disk('public')->assertExists($srv1->image_path);

        // 4. Create tanpa file -> fallback otomatis ke .webp kategori valid di disk
        $this->actingAs($superadmin)->post(route('admin-fotokopi.services.store'), [
            'store_id' => $store->id,
            'name' => 'Stabilo QA Default Kategori',
            'category' => 'ATK',
            'item_type' => 'barang',
            'stock' => 10,
            'price' => 6500,
            'unit' => 'pcs',
            'is_available' => 1,
        ])->assertRedirect(route('admin-fotokopi.services.index'));

        $srv2 = FotocopyService::where('store_id', $store->id)->where('name', 'Stabilo QA Default Kategori')->firstOrFail();
        $itm2 = PhotocopyItem::where('photocopy_place_id', $place->id)->where('name', 'Stabilo QA Default Kategori')->firstOrFail();
        $expectedFallback = ServiceController::defaultCategoryImagePath('ATK');

        $this->assertSame($expectedFallback, $srv2->image_path);
        $this->assertSame($expectedFallback, $itm2->image_path);
        $this->assertTrue(is_file(public_path('storage/' . $expectedFallback)));
    }

    public function test_smart_search_finds_exact_pulpen_pink_and_tipex_variants(): void
    {
        // 1. Pulpen Pink eksak (bukan fallback) lintas beberapa mitra
        $pinkResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
            'q' => 'cari pulpen pink murah terdekat',
            'lat' => 3.5625,
            'lng' => 98.6590,
        ]))->assertOk()->json();

        $this->assertFalse((bool) ($pinkResp['relaxed_fallback_used'] ?? true));
        $this->assertGreaterThanOrEqual(3, count($pinkResp['stores'] ?? []));
        $this->assertTrue((bool) ($pinkResp['results'][0]['exact_attribute_match'] ?? false));
        $this->assertStringContainsStringIgnoringCase('pink', $pinkResp['results'][0]['name'] ?? '');

        // 2. Pencarian variasi ejaan Tipe-X / Correction Tape ("tipec murah terdekat" & "tipe-x terdekat")
        foreach (['tipec murah terdekat', 'tipe-x terdekat'] as $q) {
            $txResp = $this->getJson('/api/fotocopy/smart-search?' . http_build_query([
                'q' => $q,
                'lat' => 3.5625,
                'lng' => 98.6590,
            ]))->assertOk()->json();

            $this->assertNotEmpty($txResp['results'], "Hasil kosong untuk kueri: {$q}");
            $topName = mb_strtolower((string) ($txResp['results'][0]['name'] ?? ''), 'UTF-8');
            $this->assertTrue(
                str_contains($topName, 'tipe-x') || str_contains($topName, 'correction'),
                "Item teratas tidak relevan untuk '{$q}': {$topName}"
            );
        }
    }

    public function test_rich_dummy_orders_seeder_is_idempotent_populates_reports_and_serves_valid_pdfs(): void
    {
        $this->seed(RichFotocopyOrdersDummySeeder::class);
        $countAfterFirst = FotocopyOrder::where('order_code', 'like', 'ORD-DEMO-%')->count();
        $this->assertGreaterThanOrEqual(50, $countAfterFirst);

        // Idempotensi pada run kedua
        $this->seed(RichFotocopyOrdersDummySeeder::class);
        $this->assertSame($countAfterFirst, FotocopyOrder::where('order_code', 'like', 'ORD-DEMO-%')->count());
        $this->assertGreaterThan(0, WhatsappDispatchLog::count());

        // Cek file PDF fisik tersedia di disk 'local' dan preview endpoint merespons 200 application/pdf
        $sampleFile = FotocopyOrderFile::whereHas('order', fn ($q) => $q->where('order_code', 'like', 'ORD-DEMO-%'))
            ->with('order')
            ->firstOrFail();
        $this->assertTrue($sampleFile->isPhysicallyAvailable());

        $superadmin = User::where('email', 'superadmin@usu.ac.id')->firstOrFail();
        $previewResp = $this->actingAs($superadmin)->get(route('cashier-fotokopi.orders.files.download', [
            'order' => $sampleFile->order,
            'file' => $sampleFile,
            'inline' => 1,
        ]));
        $previewResp->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $previewResp->headers->get('Content-Type'));

        // Halaman kasir & laporan merender tabel transaksi + tren grafik
        $this->actingAs($superadmin)
            ->get(route('cashier-fotokopi.orders.index'))
            ->assertOk()
            ->assertSee('ORD-DEMO-');

        $this->actingAs($superadmin)
            ->get(route('admin-fotokopi.reports.index'))
            ->assertOk()
            ->assertSee('Grafik Tren Transaksi 14 Hari Terakhir')
            ->assertSee('Ringkasan Metode Pembayaran');
    }

    public function test_all_stores_and_catalog_items_maintain_1_to_1_parity_and_zero_broken_local_images(): void
    {
        $this->assertSame(
            FotocopyStore::count(),
            PhotocopyPlace::where('is_active', true)->count()
        );
        $this->assertSame(
            FotocopyService::count(),
            PhotocopyItem::count()
        );

        $uniquePaths = PhotocopyItem::query()
            ->whereNotNull('image_path')
            ->distinct()
            ->pluck('image_path');

        $this->assertNotEmpty($uniquePaths);
        foreach ($uniquePaths as $path) {
            $clean = ltrim((string) $path, '/');
            $this->assertTrue(
                is_file(public_path('storage/' . $clean)),
                "Gambar katalog tidak ditemukan di disk: {$clean}"
            );
        }

        // Pastikan seluruh image_url toko yang memakai path lokal (/images/... atau /storage/...) benar-benar ada di disk
        $localStoreImgs = PhotocopyPlace::whereNotNull('image_url')
            ->pluck('image_url')
            ->filter(fn ($url) => str_starts_with((string) $url, '/'));

        foreach ($localStoreImgs as $relUrl) {
            $this->assertTrue(
                is_file(public_path(ltrim((string) $relUrl, '/'))),
                "Foto toko lokal tidak ditemukan di disk: {$relUrl}"
            );
        }
    }

    public function test_reports_page_aggregates_full_filtered_dataset_and_renders_14_day_trend(): void
    {
        $this->seed(RichFotocopyOrdersDummySeeder::class);

        $superadmin = User::where('email', 'superadmin@usu.ac.id')->firstOrFail();
        $resp = $this->actingAs($superadmin)
            ->get(route('admin-fotokopi.reports.index'))
            ->assertOk()
            ->assertDontSee('grid-cols-14')
            ->assertSee('Grafik Tren Transaksi 14 Hari Terakhir')
            ->assertSee('Ringkasan Metode Pembayaran');

        $expectedTotalOrders = FotocopyOrder::count();
        $expectedPaidCount = FotocopyOrder::where('payment_status', 'paid')->count();
        $expectedPaidSum = (int) round((float) FotocopyOrder::where('payment_status', 'paid')->sum('total_price'));

        $totalOrdersCount = $resp->viewData('totalOrdersCount');
        $totalRevenueSum = $resp->viewData('totalRevenueSum');
        $paymentBreakdown = $resp->viewData('paymentBreakdown');
        $dailyTrend = $resp->viewData('dailyTrend');

        $this->assertGreaterThan(15, $expectedTotalOrders);
        $this->assertSame($expectedTotalOrders, $totalOrdersCount);
        $this->assertSame($expectedPaidSum, $totalRevenueSum);
        $this->assertSame($expectedPaidSum, (int) array_sum(array_column($paymentBreakdown, 'revenue')));
        $this->assertSame($expectedPaidCount, (int) array_sum(array_column($paymentBreakdown, 'count')));

        $this->assertCount(14, $dailyTrend);
        $this->assertSame(now('Asia/Jakarta')->format('Y-m-d'), $dailyTrend[13]['date']);

        $prevDate = null;
        foreach ($dailyTrend as $point) {
            $this->assertArrayHasKey('date_label', $point);
            $this->assertArrayHasKey('orders_count', $point);
            $this->assertArrayHasKey('paid_revenue', $point);
            $this->assertArrayHasKey('height_pct', $point);
            $this->assertArrayHasKey('bar_px', $point);

            if ($prevDate !== null) {
                $this->assertSame(
                    1,
                    (int) \Illuminate\Support\Carbon::parse($prevDate)->diffInDays(\Illuminate\Support\Carbon::parse($point['date']))
                );
            }
            $prevDate = $point['date'];

            if ($point['orders_count'] > 0) {
                $this->assertGreaterThanOrEqual(14, $point['bar_px']);
            }
        }

        // Scope tenant admin_fotokopi (USU Press): total metode pembayaran harus tetap sama persis dengan total lunas toko
        $tenantAdmin = User::where('email', UsuPressFotocopySeeder::ADMIN_EMAIL)->firstOrFail();
        $tenantResp = $this->actingAs($tenantAdmin)
            ->get(route('admin-fotokopi.reports.index'))
            ->assertOk()
            ->assertDontSee('grid-cols-14');

        $tenantExpectedPaidSum = (int) round((float) FotocopyOrder::where('store_id', $tenantAdmin->store_id)
            ->where('payment_status', 'paid')
            ->sum('total_price'));

        $tenantPaidSum = $tenantResp->viewData('totalRevenueSum');
        $tenantBreakdown = $tenantResp->viewData('paymentBreakdown');

        $this->assertSame($tenantExpectedPaidSum, $tenantPaidSum);
        $this->assertSame($tenantExpectedPaidSum, (int) array_sum(array_column($tenantBreakdown, 'revenue')));
    }
}
