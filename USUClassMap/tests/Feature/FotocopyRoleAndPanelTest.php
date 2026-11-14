<?php

namespace Tests\Feature;

use App\Models\FotocopyOrder;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FotocopySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotocopyRoleAndPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminUserSeeder::class);
        $this->seed(FotocopySeeder::class);
    }

    public function test_guest_is_redirected_from_admin_fotokopi_and_cashier_routes(): void
    {
        $response1 = $this->get('/admin-fotokopi/dashboard');
        $response1->assertRedirect('/login');

        $response2 = $this->get('/cashier-fotokopi/orders');
        $response2->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin_fotokopi_or_cashier(): void
    {
        $regularUser = User::where('role', 'user')->first();

        $response1 = $this->actingAs($regularUser)->get('/admin-fotokopi/dashboard');
        $response1->assertRedirect('/login');
        $response1->assertSessionHas('error');

        $response2 = $this->actingAs($regularUser)->get('/cashier-fotokopi/orders');
        $response2->assertRedirect('/login');
        $response2->assertSessionHas('error');
    }

    public function test_admin_fotokopi_can_login_and_access_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'adminfotokopi@usu.ac.id',
            'password' => 'adminusu123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin-fotokopi.dashboard', absolute: false));

        $dashResponse = $this->get('/admin-fotokopi/dashboard');
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Admin Fotokopi');
        $dashResponse->assertSee('Total Mitra Toko');
    }

    public function test_cashier_fotokopi_can_login_and_access_orders_panel(): void
    {
        $response = $this->post('/login', [
            'email' => 'cashierfotokopi@usu.ac.id',
            'password' => 'adminusu123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('cashier-fotokopi.orders.index', absolute: false));

        $ordersResponse = $this->get('/cashier-fotokopi/orders');
        $ordersResponse->assertStatus(200);
        $ordersResponse->assertSee('Kasir Fotokopi');
        $ordersResponse->assertSee('Antrean Pesanan Masuk');
    }

    public function test_admin_fotokopi_can_create_new_store(): void
    {
        $admin = User::where('role', 'admin_fotokopi')->first();

        $response = $this->actingAs($admin)->post(route('admin-fotokopi.stores.store'), [
            'name' => 'Fotocopy Fasilkom Baru',
            'location_name' => 'Lantai 1 Gedung C Fasilkom-TI',
            'opening_hours' => '08:00 - 17:00',
            'operating_days' => 'Senin - Jumat',
            'whatsapp' => '081233445566',
            'starting_price' => 200,
            'rating' => 4.9,
            'is_open' => 1,
            'description' => 'Fotocopy dekat lab komputer Fasilkom TI.',
        ]);

        $response->assertRedirect(route('admin-fotokopi.stores.index'));
        $this->assertDatabaseHas('fotocopy_stores', [
            'name' => 'Fotocopy Fasilkom Baru',
            'location_name' => 'Lantai 1 Gedung C Fasilkom-TI',
        ]);
    }

    public function test_admin_fotokopi_can_create_catalog_service(): void
    {
        $admin = User::where('role', 'admin_fotokopi')->first();
        $store = FotocopyStore::first();

        $response = $this->actingAs($admin)->post(route('admin-fotokopi.services.store'), [
            'store_id' => $store->id,
            'name' => 'Cetak Stiker Glossy A4',
            'category' => 'Print',
            'price' => 5000,
            'unit' => 'lembar',
            'description' => 'Waterproof sticker paper',
            'is_available' => 1,
        ]);

        $response->assertRedirect(route('admin-fotokopi.services.index'));
        $this->assertDatabaseHas('fotocopy_services', [
            'store_id' => $store->id,
            'name' => 'Cetak Stiker Glossy A4',
            'price' => 5000,
        ]);
    }

    public function test_cashier_can_update_order_status(): void
    {
        $cashier = User::where('role', 'cashier_fotokopi')->first();
        $order = FotocopyOrder::where('order_status', 'Menunggu')->first();

        $response = $this->actingAs($cashier)->patch(route('cashier-fotokopi.orders.status', $order), [
            'order_status' => 'Diproses',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('Diproses', $order->fresh()->order_status);

        // Update to Selesai
        $response2 = $this->actingAs($cashier)->patch(route('cashier-fotokopi.orders.status', $order), [
            'order_status' => 'Selesai',
        ]);

        $response2->assertSessionHas('success');
        $this->assertEquals('Selesai', $order->fresh()->order_status);
        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    public function test_public_fotocopy_pages_render_successfully(): void
    {
        $responseIndex = $this->get('/fotocopy');
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Layanan Fotocopy');
        $responseIndex->assertSee('Berkah Abadi Copy');

        $store = FotocopyStore::where('name', 'like', '%Berkah Abadi%')->first();
        $responseShow = $this->get("/fotocopy/{$store->id}");
        $responseShow->assertStatus(200);
        $responseShow->assertSee($store->name);
        $responseShow->assertSee('Katalog Layanan');
        $responseShow->assertSee('Daftar Harga');

        $responseOrder = $this->get("/fotocopy/{$store->id}/order");
        $responseOrder->assertStatus(200);
        $responseOrder->assertSee('Keranjang Pesanan');
    }

    public function test_superadmin_can_login_with_username_and_has_universal_access(): void
    {
        // 1. Login with username 'superadmin' and password 'superadmin123'
        $loginResponse = $this->post('/login', [
            'email' => 'superadmin',
            'password' => 'superadmin123',
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(route('admin.dashboard', absolute: false));

        // 2. Can access Academic Admin panel
        $adminResp = $this->get('/admin/dashboard');
        $adminResp->assertStatus(200);
        $adminResp->assertSee('Akses Penuh Super Admin');
        $adminResp->assertSee('Modul Akademik');
        $adminResp->assertSee('Modul Admin Fotokopi');
        $adminResp->assertSee('Modul Kasir');

        // 3. Can access Admin Fotokopi panel without relogging
        $fotokopiResp = $this->get('/admin-fotokopi/dashboard');
        $fotokopiResp->assertStatus(200);
        $fotokopiResp->assertSee('Admin Fotokopi');
        $fotokopiResp->assertSee('Akses Multi-Panel');

        // 4. Can access Cashier Fotokopi panel without relogging
        $cashierResp = $this->get('/cashier-fotokopi/orders');
        $cashierResp->assertStatus(200);
        $cashierResp->assertSee('Antrean Pesanan Masuk');
        $cashierResp->assertSee('Akses Multi-Panel');
    }

    public function test_superadmin_can_login_with_email_address(): void
    {
        $loginResponse = $this->post('/login', [
            'email' => 'superadmin@usu.ac.id',
            'password' => 'superadmin123',
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_cashier_can_view_file_preview_and_direct_print_button(): void
    {
        $cashier = User::where('role', 'cashier_fotokopi')->first();

        $response = $this->actingAs($cashier)->get('/cashier-fotokopi/orders');
        $response->assertStatus(200);
        $response->assertSee('Preview');
        $response->assertSee('Print Now');
        $response->assertSee('Cetak Dokumen');
        $response->assertSee('Makalah_Studi_Kasus_MKWU_Kelompok4.pdf');
    }

    public function test_cashier_can_create_pos_order_with_file_upload(): void
    {
        Storage::fake('public');
        $cashier = User::where('role', 'cashier_fotokopi')->first();
        $service = FotocopyService::first();

        $file = UploadedFile::fake()->create('Tugas_Praktikum_Daffa.pdf', 800, 'application/pdf');

        $response = $this->actingAs($cashier)->post(route('cashier-fotokopi.orders.pos.store'), [
            'customer_name' => 'Daffa Saragih',
            'customer_phone' => '081234567890',
            'notes' => 'Cetak warna A4 rangkap 3',
            'payment_method' => 'Tunai',
            'order_status' => 'Diproses',
            'file' => $file,
            'items' => [
                [
                    'service_id' => $service->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        $response->assertRedirect(route('cashier-fotokopi.orders.index'));

        $this->assertDatabaseHas('fotocopy_orders', [
            'customer_name' => 'Daffa Saragih',
            'file_name' => 'Tugas_Praktikum_Daffa.pdf',
            'file_type' => 'pdf',
        ]);
    }

    public function test_cashier_can_access_print_job_ticket(): void
    {
        $cashier = User::where('role', 'cashier_fotokopi')->first();
        $order = FotocopyOrder::first();

        $response = $this->actingAs($cashier)->get(route('cashier-fotokopi.orders.print', $order));
        $response->assertStatus(200);
        $response->assertSee('USUClassMap Copy Center');
        $response->assertSee('Lembar Kerja Cetak');
        $response->assertSee('Cetak Dokumen Sekarang (Print)');
    }
}
