<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\FotocopyOrder;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use App\Models\User;
use App\Observers\GitSnapshotObserver;
use App\Services\GitSnapshotSyncService;
use App\Services\Search\QueryIntentParserService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\BuildingSeeder;
use Database\Seeders\PhotocopySeeder;
use Database\Seeders\ServiceMasterSeeder;
use Database\Seeders\TrialAdminFotocopySeeder;
use Database\Seeders\UsuPressFotocopySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class StoreMapSyncAndUsuPressTest extends TestCase
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
        });
    }

    public function test_admin_store_crud_immediately_syncs_to_photocopy_places_and_copy_shops_api(): void
    {
        $superadmin = User::where('email', 'superadmin@usu.ac.id')->firstOrFail();

        $createResp = $this->actingAs($superadmin)->post(route('admin-fotokopi.stores.store'), [
            'name' => 'Mitra QA Sync Campus Print',
            'location_name' => 'Jl. Dr. Mansyur No. 18, Kampus USU',
            'latitude' => 3.56185,
            'longitude' => 98.65795,
            'opening_hours' => '08:30 - 21:00',
            'operating_days' => 'Senin - Sabtu',
            'whatsapp' => '081234567890',
            'starting_price' => 500,
            'rating' => 4.8,
            'is_open' => 1,
            'description' => 'Toko QA verifikasi sinkronisasi real-time admin ke peta USU.',
        ]);

        $createResp->assertRedirect(route('admin-fotokopi.stores.index'));

        $store = FotocopyStore::where('name', 'Mitra QA Sync Campus Print')->firstOrFail();
        $place = PhotocopyPlace::where('slug', $store->slug)->firstOrFail();

        $this->assertEqualsWithDelta(3.56185, (float) $place->latitude, 0.00001);
        $this->assertEqualsWithDelta(98.65795, (float) $place->longitude, 0.00001);
        $this->assertEquals('08:30', $place->open_time);
        $this->assertEquals('21:00', $place->close_time);
        $this->assertEquals('6281234567890', $place->whatsapp);

        // Update koordinat >= 20m & nama -> entrance_lat/lng ikut diperbarui dan API ?per_page=200 mencerminkannya
        $updateResp = $this->actingAs($superadmin)->put(route('admin-fotokopi.stores.update', $store), [
            'name' => 'Mitra QA Sync Updated',
            'location_name' => 'Dekat Pintu 2 USU, Padang Bulan',
            'latitude' => 3.56320,
            'longitude' => 98.65980,
            'opening_hours' => '07:00 - 20:00',
            'operating_days' => 'Senin - Minggu',
            'whatsapp' => '081299988877',
            'starting_price' => 350,
            'rating' => 4.9,
            'is_open' => 1,
            'description' => 'Toko diperbarui dari panel admin.',
        ]);

        $updateResp->assertRedirect(route('admin-fotokopi.stores.index'));

        $place->refresh();
        $this->assertEquals('Mitra QA Sync Updated', $place->name);
        $this->assertEqualsWithDelta(3.56320, (float) $place->latitude, 0.00001);
        $this->assertEqualsWithDelta(98.65980, (float) $place->longitude, 0.00001);
        $this->assertEqualsWithDelta(3.56320, (float) $place->entrance_lat, 0.00001);
        $this->assertEqualsWithDelta(98.65980, (float) $place->entrance_lng, 0.00001);
        $this->assertEquals('6281299988877', $place->whatsapp);

        $apiJson = $this->getJson('/api/copy-shops?per_page=200')->assertOk()->json();
        $foundInApi = collect($apiJson['data'])->firstWhere('slug', $store->slug);
        $this->assertNotNull($foundInApi);
        $this->assertEquals('Mitra QA Sync Updated', $foundInApi['name']);

        // Delete toko -> PhotocopyPlace ikut terhapus
        $deleteResp = $this->actingAs($superadmin)->delete(route('admin-fotokopi.stores.destroy', $store));
        $deleteResp->assertRedirect(route('admin-fotokopi.stores.index'));

        $this->assertDatabaseMissing('fotocopy_stores', ['id' => $store->id]);
        $this->assertDatabaseMissing('photocopy_places', ['id' => $place->id]);
    }

    public function test_admin_service_crud_immediately_syncs_to_photocopy_items(): void
    {
        $superadmin = User::where('email', 'superadmin@usu.ac.id')->firstOrFail();
        $usuPressStore = FotocopyStore::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();
        $usuPressPlace = PhotocopyPlace::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();

        $this->actingAs($superadmin)->post(route('admin-fotokopi.services.store'), [
            'store_id' => $usuPressStore->id,
            'name' => 'Cetak Monograf QA Custom ISBN',
            'category' => 'Print',
            'item_type' => 'jasa',
            'sub_category' => 'Penerbitan Buku',
            'brand' => 'USU Press',
            'price' => 42000,
            'unit' => 'eksemplar',
            'description' => 'Layanan pengujian sinkronisasi layanan ke katalog publik.',
            'is_available' => 1,
        ])->assertRedirect(route('admin-fotokopi.services.index'));

        $service = FotocopyService::where('store_id', $usuPressStore->id)
            ->where('name', 'Cetak Monograf QA Custom ISBN')
            ->firstOrFail();

        $item = PhotocopyItem::where('photocopy_place_id', $usuPressPlace->id)
            ->where('name', 'Cetak Monograf QA Custom ISBN')
            ->firstOrFail();
        $this->assertEquals(42000, (int) $item->price);

        $this->actingAs($superadmin)->put(route('admin-fotokopi.services.update', $service), [
            'store_id' => $usuPressStore->id,
            'name' => 'Cetak Monograf QA Revisi',
            'category' => 'Print',
            'item_type' => 'jasa',
            'sub_category' => 'Penerbitan Buku',
            'brand' => 'USU Press',
            'price' => 48500,
            'unit' => 'eksemplar',
            'description' => 'Harga diperbarui dari panel admin.',
            'is_available' => 1,
        ])->assertRedirect(route('admin-fotokopi.services.index'));

        $reloadedItem = PhotocopyItem::where('photocopy_place_id', $usuPressPlace->id)
            ->where('name', 'Cetak Monograf QA Revisi')
            ->first();
        $this->assertNotNull($reloadedItem);
        $this->assertEquals(48500, (int) $reloadedItem->price);
        $this->assertDatabaseMissing('photocopy_items', [
            'photocopy_place_id' => $usuPressPlace->id,
            'name' => 'Cetak Monograf QA Custom ISBN',
        ]);

        $this->actingAs($superadmin)->delete(route('admin-fotokopi.services.destroy', $service))
            ->assertRedirect(route('admin-fotokopi.services.index'));

        $this->assertDatabaseMissing('fotocopy_services', ['id' => $service->id]);
        $this->assertDatabaseMissing('photocopy_items', [
            'photocopy_place_id' => $usuPressPlace->id,
            'name' => 'Cetak Monograf QA Revisi',
        ]);
    }

    public function test_fotocopy_sync_map_command_reconciles_deleted_place_and_coordinate_drift(): void
    {
        $usuPressStore = FotocopyStore::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();
        $usuPressPlace = PhotocopyPlace::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();

        // Hapus paksa 1 place & ubah koordinat place lain untuk mensimulasikan drift
        $usuPressPlace->delete();

        $trialPlace = PhotocopyPlace::where('slug', TrialAdminFotocopySeeder::TRIAL_SLUG)->firstOrFail();
        $trialPlace->update(['latitude' => 3.5500, 'longitude' => 98.6500]);

        $this->artisan('fotocopy:sync-map')->assertExitCode(0);

        $this->assertEquals(
            FotocopyStore::count(),
            PhotocopyPlace::where('is_active', true)->count()
        );

        $restoredPlace = PhotocopyPlace::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();
        $this->assertEqualsWithDelta((float) $usuPressStore->latitude, (float) $restoredPlace->latitude, 0.00001);
        $this->assertEqualsWithDelta((float) $usuPressStore->longitude, (float) $restoredPlace->longitude, 0.00001);
        $this->assertEquals(
            FotocopyService::where('store_id', $usuPressStore->id)->count(),
            PhotocopyItem::where('photocopy_place_id', $restoredPlace->id)->count()
        );

        $trialStore = FotocopyStore::where('slug', TrialAdminFotocopySeeder::TRIAL_SLUG)->firstOrFail();
        $trialPlace->refresh();
        $this->assertEqualsWithDelta((float) $trialStore->latitude, (float) $trialPlace->latitude, 0.00001);
        $this->assertEqualsWithDelta((float) $trialStore->longitude, (float) $trialPlace->longitude, 0.00001);
    }

    public function test_usupress_seeder_idempotency_image_integrity_and_tenant_isolation(): void
    {
        // Jalankan ulang seeder untuk membuktikan 100% idempoten
        $this->seed(UsuPressFotocopySeeder::class);

        $this->assertEquals(1, FotocopyStore::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->count());
        $this->assertEquals(1, PhotocopyPlace::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->count());

        $usuStore = FotocopyStore::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();
        $usuPlace = PhotocopyPlace::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();
        $fasilkom = Building::where('code', 'FASILKOM-TI')->first();

        $this->assertEqualsWithDelta(3.5622266, (float) $usuPlace->latitude, 0.001);
        $this->assertEqualsWithDelta(98.6589566, (float) $usuPlace->longitude, 0.001);

        if ($fasilkom) {
            $distLat = abs((float) $usuPlace->latitude - (float) $fasilkom->latitude);
            $distLng = abs((float) $usuPlace->longitude - (float) $fasilkom->longitude);
            $this->assertLessThan(0.003, $distLat + $distLng);
        }

        // Pastikan seluruh path gambar item katalog USU Press ada secara fisik di disk
        $items = PhotocopyItem::where('photocopy_place_id', $usuPlace->id)->get();
        $this->assertGreaterThanOrEqual(60, $items->count());
        foreach ($items as $item) {
            $this->assertNotEmpty($item->image_path);
            $this->assertTrue(
                is_file(public_path('storage/' . ltrim((string) $item->image_path, '/'))),
                "Gambar tidak ditemukan di disk: {$item->image_path}"
            );
        }

        // Autentikasi kredensial Admin & Kasir USU Press
        $this->assertTrue(Auth::attempt([
            'email' => UsuPressFotocopySeeder::ADMIN_EMAIL,
            'password' => UsuPressFotocopySeeder::DEFAULT_PASSWORD,
        ]));
        Auth::logout();

        $this->assertTrue(Auth::attempt([
            'email' => UsuPressFotocopySeeder::CASHIER_EMAIL,
            'password' => UsuPressFotocopySeeder::DEFAULT_PASSWORD,
        ]));
        Auth::logout();

        $adminUsuPress = User::where('email', UsuPressFotocopySeeder::ADMIN_EMAIL)->firstOrFail();
        $kasirUsuPress = User::where('email', UsuPressFotocopySeeder::CASHIER_EMAIL)->firstOrFail();
        $otherStore = FotocopyStore::where('id', '!=', $usuStore->id)->firstOrFail();

        // Isolasi Admin Toko USU Press
        $storesPage = $this->actingAs($adminUsuPress)->get(route('admin-fotokopi.stores.index'))->assertOk();
        $storesPage->assertSee('USU Press (USUpress Penerbit & Fotocopy)');
        $storesPage->assertDontSee($otherStore->name);

        $this->actingAs($adminUsuPress)->get(route('admin-fotokopi.stores.edit', $usuStore))->assertOk();
        $this->actingAs($adminUsuPress)->get(route('admin-fotokopi.stores.edit', $otherStore))->assertForbidden();
        $this->actingAs($adminUsuPress)->post(route('admin-fotokopi.stores.store'), ['name' => 'Blocked'])->assertForbidden();

        // Isolasi Kasir USU Press
        $usuOrder = FotocopyOrder::create([
            'order_code' => 'UPR-QA-001',
            'store_id' => $usuStore->id,
            'customer_name' => 'Mahasiswa Fasilkom QA',
            'payment_method' => 'QRIS',
            'payment_status' => 'paid',
            'order_status' => 'Menunggu',
            'total_price' => 35000,
        ]);

        $otherOrder = FotocopyOrder::create([
            'order_code' => 'OTH-QA-999',
            'store_id' => $otherStore->id,
            'customer_name' => 'Mahasiswa Toko Lain QA',
            'payment_method' => 'Tunai',
            'payment_status' => 'paid',
            'order_status' => 'Menunggu',
            'total_price' => 15000,
        ]);

        $kasirOrders = $this->actingAs($kasirUsuPress)->get(route('cashier-fotokopi.orders.index'))->assertOk();
        $kasirOrders->assertSee('UPR-QA-001');
        $kasirOrders->assertDontSee('OTH-QA-999');

        $this->actingAs($kasirUsuPress)->patch(route('cashier-fotokopi.orders.status', $otherOrder), [
            'order_status' => 'Diproses',
        ])->assertForbidden();

        $this->actingAs($kasirUsuPress)->patch(route('cashier-fotokopi.orders.status', $usuOrder), [
            'order_status' => 'Diproses',
        ])->assertRedirect();

        $this->assertEquals('Diproses', $usuOrder->fresh()->order_status);
    }

    public function test_flexible_store_search_and_zero_pagination_truncation(): void
    {
        $expectedActiveCount = PhotocopyPlace::where('is_active', true)->count();
        $usuPlace = PhotocopyPlace::where('slug', UsuPressFotocopySeeder::USUPRESS_SLUG)->firstOrFail();

        // /fotocopy?q=... variasi penulisan nama toko
        foreach (['USUpress Penerbit', 'USU Press', 'usupress'] as $q) {
            $resp = $this->get(route('fotocopy.index', ['q' => $q]))->assertOk();
            $resp->assertSee('USU Press (USUpress Penerbit & Fotocopy)');
            $resp->assertDontSee('Tidak ada toko ditemukan');
        }

        // Smart Search API untuk nama toko spesifik & kedekatan ke Fasilkom
        foreach (['USUpress Penerbit', 'USU Press'] as $q) {
            $smart = $this->getJson('/api/fotocopy/smart-search?q=' . urlencode($q))
                ->assertOk()
                ->json();

            $this->assertGreaterThanOrEqual(4, count($smart['results'] ?? []));
            $this->assertEquals($usuPlace->id, $smart['stores'][0]['id'] ?? null);
        }

        $nearFasilkom = $this->getJson('/api/fotocopy/smart-search?q=' . urlencode('fotocopy dekat fasilkom'))
            ->assertOk()
            ->json();

        $nearFasilkomIds = collect($nearFasilkom['stores'] ?? [])->pluck('id')->all();
        $this->assertContains($usuPlace->id, $nearFasilkomIds);

        // API copy-shops tanpa terpotong pagination default 15
        $allCopyShops = $this->getJson('/api/copy-shops?per_page=200')->assertOk()->json();
        $this->assertCount($expectedActiveCount, $allCopyShops['data'] ?? []);
        $this->assertTrue(collect($allCopyShops['data'] ?? [])->contains('id', $usuPlace->id));

        $filteredCopyShops = $this->getJson('/api/copy-shops?q=' . urlencode('USUpress'))->assertOk()->json();
        $this->assertTrue(collect($filteredCopyShops['data'] ?? [])->contains('id', $usuPlace->id));

        // Chatbot Tanya Ucup mengenali nama toko USUpress Penerbit
        $chat = $this->postJson(route('copilot.chat'), [
            'message' => 'USUpress Penerbit',
            'user_lat' => 3.56268,
            'user_lng' => 98.65912,
        ])->assertOk()->json();

        $this->assertEquals('smart_fotocopy_search', $chat['intent'] ?? null);
        $this->assertNotEmpty($chat['cards'] ?? []);
        $this->assertEquals(
            $usuPlace->id,
            $chat['cards'][0]['data']['store']['id'] ?? null
        );
    }
}
