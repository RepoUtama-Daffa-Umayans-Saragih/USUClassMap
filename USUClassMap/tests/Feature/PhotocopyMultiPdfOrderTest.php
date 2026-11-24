<?php

namespace Tests\Feature;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderFile;
use App\Models\FotocopyStore;
use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use App\Models\User;
use App\Models\WhatsappDispatchLog;
use App\Services\WhatsApp\Drivers\WahaWhatsAppDriver;
use App\Services\WhatsApp\WhatsAppManager;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FotocopySeeder;
use Database\Seeders\TrialAdminFotocopySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PhotocopyMultiPdfOrderTest extends TestCase
{
    use RefreshDatabase;

    protected PhotocopyPlace $trialPlace;
    protected FotocopyStore $trialStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminUserSeeder::class);
        $this->seed(FotocopySeeder::class);
        $this->seed(TrialAdminFotocopySeeder::class);

        Storage::fake('local');
        config([
            'whatsapp.default' => 'sandbox',
            'queue.default' => 'sync',
        ]);

        $this->trialPlace = PhotocopyPlace::where('slug', 'fotocopy-trial-admin')->firstOrFail();
        $this->trialStore = FotocopyStore::where('slug', 'fotocopy-trial-admin')->firstOrFail();
    }

    /**
     * Build minimal syntactically valid PDF bytes containing `$pages` page objects.
     */
    protected function createValidPdfUpload(string $filename, int $pages = 2): UploadedFile
    {
        $pageEntries = '';
        for ($i = 1; $i <= $pages; $i++) {
            $objId = $i + 2;
            $pageEntries .= "{$objId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
        }

        $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Count {$pages} >>\nendobj\n"
            . $pageEntries
            . "%%EOF";

        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    public function test_public_checkout_supports_multi_pdf_per_file_note_mixed_atk_and_anti_spam_guard(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $finishSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Jilid')
            ->firstOrFail();

        $atkItem = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'barang')
            ->where('category', 'ATK')
            ->firstOrFail();

        $pdf1 = $this->createValidPdfUpload('Cover_Skripsi.pdf', 3);
        $pdf2 = $this->createValidPdfUpload('Bab1_5_Skripsi.pdf', 5);

        $pdfConfigs = [
            [
                'page_count' => 1, // Deliberately underestimated on client; server must detect 3
                'copies' => 1,
                'print_service_id' => $printSvc->id,
                'paper_size' => 'A4',
                'finishing_service_id' => null,
                'note' => 'Cover pakai kertas glossy 230gr jangan dipotong margin',
            ],
            [
                'page_count' => 5,
                'copies' => 2,
                'print_service_id' => $printSvc->id,
                'paper_size' => 'A4',
                'finishing_service_id' => $finishSvc->id,
                'note' => 'Halaman 2 cetak warna, sisanya hitam putih saja',
            ],
        ];

        $response = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Daffa Umayans Saragih',
            'customer_whatsapp' => '0852-7000-1122',
            'notes' => 'Diambil jam 13.00 WIB',
            'payment_method' => 'qris',
            'payment_channel' => 'QRIS',
            'items' => json_encode([
                [
                    'service_id' => $atkItem->id,
                    'name' => $atkItem->name,
                    'category' => $atkItem->category,
                    'price' => 1, // Client price tampering attempt; server must use $atkItem->price
                    'quantity' => 2,
                ],
            ]),
            'pdf_configs' => json_encode($pdfConfigs),
            'pdf_files' => [$pdf1, $pdf2],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $orderCode = $response->json('order_code');
        $order = FotocopyOrder::with('files')->where('order_code', $orderCode)->firstOrFail();

        $expectedTotal = (1 * (3 * $printSvc->price))
            + (2 * ((5 * $printSvc->price) + $finishSvc->price))
            + (2 * $atkItem->price);

        $this->assertEquals($expectedTotal, $order->total_price);
        $this->assertEquals('6285270001122', $order->customer_whatsapp);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals('not_ready', $order->wa_dispatch_status);
        $this->assertNotEmpty($order->pickup_code);

        $this->assertCount(2, $order->files);
        $this->assertEquals('Cover_Skripsi.pdf', $order->files[0]->original_name);
        $this->assertEquals(3, $order->files[0]->page_count);
        $this->assertEquals('Cover pakai kertas glossy 230gr jangan dipotong margin', $order->files[0]->note);
        $this->assertEquals('local', $order->files[0]->storage_disk);
        Storage::disk('local')->assertExists($order->files[0]->stored_path);

        $this->assertEquals('Bab1_5_Skripsi.pdf', $order->files[1]->original_name);
        $this->assertEquals(5, $order->files[1]->page_count);
        $this->assertEquals(2, $order->files[1]->copies);
        $this->assertEquals('Halaman 2 cetak warna, sisanya hitam putih saja', $order->files[1]->note);
        Storage::disk('local')->assertExists($order->files[1]->stored_path);

        // ANTI-SPAM UNPAID GUARD: ZERO WhatsApp dispatches while order is pending
        $this->assertEquals(0, WhatsappDispatchLog::where('fotocopy_order_id', $order->id)->count());
    }

    public function test_paid_order_generates_invoice_pdf_and_dispatches_ordered_whatsapp_bundle_to_trial_admin(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $storeResp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Mahasiswa Trial Lunas',
            'customer_whatsapp' => '085270001122',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([
                [
                    'page_count' => 2,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'note' => 'NOTE PDF SATU COVER KHUSUS',
                ],
                [
                    'page_count' => 4,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'note' => 'NOTE PDF DUA ISI SKRIPSI',
                ],
            ]),
            'pdf_files' => [
                $this->createValidPdfUpload('FileSatu.pdf', 2),
                $this->createValidPdfUpload('FileDua.pdf', 4),
            ],
        ], ['Accept' => 'application/json']);

        $storeResp->assertStatus(200);
        $orderCode = $storeResp->json('order_code');

        // Confirm zero WA dispatches before payment
        $this->assertEquals(0, WhatsappDispatchLog::count());

        $payResp = $this->postJson(route('fotocopy.orders.simulate-payment', $orderCode));
        $payResp->assertStatus(200);
        $payResp->assertJson([
            'success' => true,
            'payment_status' => 'paid',
        ]);

        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        $this->assertStringStartsWith('INV-', (string) $order->invoice_number);
        $this->assertNotEmpty($order->invoice_pdf_path);
        Storage::disk('local')->assertExists($order->invoice_pdf_path);

        $invoiceBytes = Storage::disk('local')->get($order->invoice_pdf_path);
        $this->assertGreaterThan(2048, strlen($invoiceBytes));
        $this->assertSame('%PDF-', substr($invoiceBytes, 0, 5));

        $logs = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->orderBy('id')
            ->get();

        // Store receives: summary + 2 pdf_attachment + 1 invoice = 4
        // Customer receives: 1 invoice copy = 1 => Total 5 logs
        $this->assertCount(5, $logs);

        $storeLogs = $logs->where('recipient_role', 'store')->values();
        $this->assertCount(4, $storeLogs);
        $this->assertTrue($storeLogs->every(fn ($l) => $l->recipient_phone === '6285769737410' && $l->status === 'sent'));

        $this->assertEquals('summary', $storeLogs[0]->message_type);
        $this->assertStringContainsString($order->order_code, $storeLogs[0]->payload_json['text']);

        $this->assertEquals('pdf_attachment', $storeLogs[1]->message_type);
        $this->assertStringContainsString('NOTE PDF SATU COVER KHUSUS', $storeLogs[1]->payload_json['caption']);

        $this->assertEquals('pdf_attachment', $storeLogs[2]->message_type);
        $this->assertStringContainsString('NOTE PDF DUA ISI SKRIPSI', $storeLogs[2]->payload_json['caption']);

        $this->assertEquals('invoice', $storeLogs[3]->message_type);
        $this->assertEquals($order->invoice_pdf_path, $storeLogs[3]->attachment_path);

        $custLogs = $logs->where('recipient_role', 'customer')->values();
        $this->assertCount(1, $custLogs);
        $this->assertEquals('6285270001122', $custLogs[0]->recipient_phone);
        $this->assertEquals('invoice', $custLogs[0]->message_type);
    }

    public function test_cashier_ready_notification_zip_download_and_admin_wa_tools(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $storeResp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Pelanggan Kasir Uji',
            'customer_whatsapp' => '085270001122',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([
                [
                    'page_count' => 2,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'note' => 'Tolong jilid mika merah',
                ],
            ]),
            'pdf_files' => [
                $this->createValidPdfUpload('Laporan_Akhir.pdf', 2),
            ],
        ], ['Accept' => 'application/json']);

        $order = FotocopyOrder::where('order_code', $storeResp->json('order_code'))->firstOrFail();
        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        $order->refresh();

        $adminFotokopi = User::where('email', 'adminfotokopi@usu.ac.id')->firstOrFail();

        // 1. Mark order Selesai -> customer receives 'status_update' notification
        $this->actingAs($adminFotokopi)
            ->patch(route('cashier-fotokopi.orders.status', $order), ['order_status' => 'Selesai'])
            ->assertRedirect();

        $readyLog = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->where('recipient_role', 'customer')
            ->where('message_type', 'status_update')
            ->first();
        $this->assertNotNull($readyLog);
        $this->assertStringContainsString('SIAP DIAMBIL', $readyLog->payload_json['text']);

        // 2. Download all PDFs + CATATAN_CETAK.txt as ZIP
        $zipResp = $this->actingAs($adminFotokopi)->get(route('cashier-fotokopi.orders.download-zip', $order));
        $zipResp->assertStatus(200);

        $tmpZip = $zipResp->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmpZip) === true);
        $this->assertNotFalse($zip->locateName('01_Laporan_Akhir.pdf'));
        $this->assertNotFalse($zip->locateName('CATATAN_CETAK.txt'));
        $notesTxt = $zip->getFromName('CATATAN_CETAK.txt');
        $this->assertStringContainsString('Tolong jilid mika merah', (string) $notesTxt);
        $zip->close();
        @unlink($tmpZip);

        // 3. Resend WA endpoint
        $beforeCount = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)->count();
        $this->actingAs($adminFotokopi)
            ->post(route('cashier-fotokopi.orders.resend-wa', $order))
            ->assertSessionHas('success');
        $this->assertGreaterThan($beforeCount, WhatsappDispatchLog::where('fotocopy_order_id', $order->id)->count());

        // 4. Admin store test WA & sandbox viewer JSON
        $this->actingAs($adminFotokopi)
            ->post(route('admin-fotokopi.stores.test-whatsapp', $this->trialStore))
            ->assertSessionHas('success');

        $this->actingAs($adminFotokopi)
            ->getJson(route('admin-fotokopi.stores.whatsapp-sandbox'))
            ->assertStatus(200)
            ->assertJsonStructure(['logs']);
    }

    public function test_security_rejects_spoofed_non_pdf_protects_private_urls_and_cleans_up_expired_files(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        // 1. Spoofed non-PDF file masquerading as .pdf MUST be rejected (422)
        $spoofed = UploadedFile::fake()->createWithContent('malware.pdf', '<?php echo "not a pdf"; ?>');
        $badResp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Penyerang Uji',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([
                ['page_count' => 1, 'copies' => 1, 'print_service_id' => $printSvc->id],
            ]),
            'pdf_files' => [$spoofed],
        ], ['Accept' => 'application/json']);

        $badResp->assertStatus(422);
        $this->assertEquals(0, FotocopyOrder::where('customer_name', 'Penyerang Uji')->count());

        // 2. Create valid paid order with private PDF
        $okResp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Pemilik Sah Dokumen',
            'customer_whatsapp' => '085270001122',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([
                ['page_count' => 2, 'copies' => 1, 'print_service_id' => $printSvc->id, 'note' => 'Privasi terjaga'],
            ]),
            'pdf_files' => [$this->createValidPdfUpload('Rahasia_Skripsi.pdf', 2)],
        ], ['Accept' => 'application/json'])->assertStatus(200);

        $order = FotocopyOrder::with('files')->where('order_code', $okResp->json('order_code'))->firstOrFail();
        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        $order->refresh();
        $file = $order->files->firstOrFail();

        // Anonymous unsigned access MUST be rejected (403) & cashier route redirects to login (302)
        $this->get(route('fotocopy.orders.files.download', ['orderCode' => $order->order_code, 'file' => $file->id]))
            ->assertStatus(403);
        $this->get(route('fotocopy.orders.invoice', ['orderCode' => $order->order_code]))
            ->assertStatus(403);
        $this->get(route('cashier-fotokopi.orders.files.download', ['order' => $order, 'file' => $file]))
            ->assertRedirect('/login');

        // Valid Signed URL MUST succeed (200)
        $this->get($file->signedDownloadUrl(15))->assertStatus(200);
        $this->get($order->invoiceUrl(15))->assertStatus(200);

        // 3. Auto-cleanup removes expired physical PDF while preserving transaction & invoice metadata
        $order->update(['order_status' => 'Selesai']);
        DB::table('fotocopy_orders')->where('id', $order->id)->update([
            'updated_at' => now()->subDays(20)->format('Y-m-d H:i:s'),
        ]);

        $this->artisan('fotocopy:cleanup-files')->assertExitCode(0);

        Storage::disk('local')->assertMissing($file->stored_path);
        $this->assertNotNull($file->fresh()->deleted_at);
        $this->assertDatabaseHas('fotocopy_orders', [
            'id' => $order->id,
            'invoice_number' => $order->invoice_number,
        ]);
    }

    public function test_order_page_exposes_catalog_first_contextual_ux_and_4_behavior_classifications(): void
    {
        $response = $this->get(route('fotocopy.order', $this->trialPlace->id));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Upload global di bawah katalog sudah dihapus total (100% Cart-Bound)
        $response->assertDontSee('id="contextual-pdf-section"', false);

        $catalogPos = strpos($html, 'Pilih Layanan Cetak');
        $cartPanelPos = strpos($html, 'id="cart-panel"');
        $this->assertNotFalse($catalogPos);
        $this->assertNotFalse($cartPanelPos);
        $this->assertLessThan($cartPanelPos, $catalogPos, 'Katalog harus tampil lebih dulu sebelum panel keranjang.');

        $response->assertSee('Keranjang Pesanan', false);
        $response->assertSee('x-data="usuFotocopyOrderPage()"', false);
        $response->assertSee('printCartItems:', false);
        $response->assertSee('addOrIncrementPrintMenu(', false);
        $response->assertSee('isCheckoutDisabled()', false);

        foreach (['digital_print', 'hybrid_finishing', 'physical_only', 'physical_goods'] as $behavior) {
            $this->assertStringContainsString($behavior, $html);
        }

        $this->assertSame(1, preg_match('/window\.usuFotocopyCatalog\s*=\s*(\[.*?\]);/s', $html, $matches));
        $catalog = json_decode($matches[1], true);
        $this->assertIsArray($catalog);
        $ctas = array_unique(array_column($catalog, 'behavior_cta'));
        foreach (['Pilih & Unggah PDF', 'Pilih Opsi', 'Pesan Offline', 'Tambah'] as $cta) {
            $this->assertContains($cta, $ctas);
        }
    }

    public function test_bundle_print_plus_laminating_calculates_authoritative_combined_price_and_formats_wa(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $laminatingSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('name', 'like', '%Laminating%')
            ->firstOrFail();

        $jilidSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Jilid')
            ->firstOrFail();

        $response = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Rizky Bundel Print',
            'customer_whatsapp' => '085270003344',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([
                [
                    'page_count' => 2,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'finishing_service_id' => $laminatingSvc->id,
                    'note' => 'Laminating glossy sudut tumpul',
                ],
                [
                    'page_count' => 2,
                    'copies' => 2,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'finishing_service_id' => $jilidSvc->id,
                    'note' => 'Jilid rapi untuk asistensi',
                ],
            ]),
            'pdf_files' => [
                $this->createValidPdfUpload('Sertifikat_Juara.pdf', 2),
                $this->createValidPdfUpload('Draft_Laporan.pdf', 2),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $order = FotocopyOrder::with(['files', 'items'])
            ->where('order_code', $response->json('order_code'))
            ->firstOrFail();

        // Laminating (per lembar): 1 copy * (2 * print + 2 * laminating)
        // Jilid (per rangkap/buku): 2 copies * ((2 * print) + jilid)
        $expectedLaminatingSub = (2 * $printSvc->price) + (2 * $laminatingSvc->price);
        $expectedJilidSub = 2 * ((2 * $printSvc->price) + $jilidSvc->price);
        $this->assertEquals($expectedLaminatingSub + $expectedJilidSub, $order->total_price);

        $this->assertTrue(
            $order->items->contains(fn ($i) => str_contains($i->service_name, 'Bundel Cetak PDF + ' . $laminatingSvc->name))
        );

        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        $order->refresh();

        Storage::disk('local')->assertExists($order->invoice_pdf_path);
        $invoiceBytes = Storage::disk('local')->get($order->invoice_pdf_path);
        $this->assertSame('%PDF-', substr($invoiceBytes, 0, 5));

        $storeLogs = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->where('recipient_role', 'store')
            ->orderBy('id')
            ->get();

        $summaryLog = $storeLogs->firstWhere('message_type', 'summary');
        $this->assertNotNull($summaryLog);
        $this->assertStringContainsString('+ BUNDEL FINISHING: ' . $laminatingSvc->name, $summaryLog->payload_json['text']);

        $pdfLogs = $storeLogs->where('message_type', 'pdf_attachment')->values();
        $this->assertCount(2, $pdfLogs);
        $this->assertStringContainsString('*BUNDEL FINISHING:* Cetak file PDF ini terlebih dahulu, lalu langsung proses *' . $laminatingSvc->name . '*', $pdfLogs[0]->payload_json['caption']);
    }

    public function test_offline_laminating_and_physical_only_fotokopi_work_without_pdf_upload_and_flag_offline_in_wa(): void
    {
        $laminatingSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('name', 'like', '%Laminating%')
            ->firstOrFail();

        $fotokopiSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Fotocopy')
            ->firstOrFail();

        $atkItem = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'barang')
            ->firstOrFail();

        $response = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Aulia Berkas Fisik',
            'customer_whatsapp' => '085270005566',
            'notes' => 'Saya bawa KTP dan berkas asli ke toko jam 14.00',
            'payment_method' => 'qris',
            'items' => json_encode([
                [
                    'service_id' => $laminatingSvc->id,
                    'name' => $laminatingSvc->name,
                    'category' => $laminatingSvc->category,
                    'price' => 1,
                    'quantity' => 1,
                    'fulfillment_mode' => 'offline_physical',
                ],
                [
                    'service_id' => $fotokopiSvc->id,
                    'name' => $fotokopiSvc->name,
                    'category' => $fotokopiSvc->category,
                    'price' => 1,
                    'quantity' => 10,
                ],
                [
                    'service_id' => $atkItem->id,
                    'name' => $atkItem->name,
                    'category' => $atkItem->category,
                    'price' => 1,
                    'quantity' => 2,
                ],
            ]),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $order = FotocopyOrder::with(['files', 'items'])
            ->where('order_code', $response->json('order_code'))
            ->firstOrFail();

        $this->assertCount(0, $order->files);
        $expectedTotal = $laminatingSvc->price + (10 * $fotokopiSvc->price) + (2 * $atkItem->price);
        $this->assertEquals($expectedTotal, $order->total_price);

        $laminatingRow = $order->items->firstWhere('service_name', $laminatingSvc->name);
        $fotokopiRow = $order->items->firstWhere('service_name', $fotokopiSvc->name);
        $atkRow = $order->items->firstWhere('service_name', $atkItem->name);

        $this->assertStringContainsString('Offline Fisik', (string) $laminatingRow->category);
        $this->assertStringContainsString('Offline Fisik', (string) $fotokopiRow->category);
        $this->assertStringNotContainsString('Offline Fisik', (string) $atkRow->category);

        // Payment instructions page must surface the offline physical badge
        $payPage = $this->get(route('fotocopy.orders.payment', $order->order_code));
        $payPage->assertStatus(200);
        $payPage->assertSee('Wajib Bawa Berkas Fisik (Offline)', false);

        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        $order->refresh();

        Storage::disk('local')->assertExists($order->invoice_pdf_path);
        $invoiceBytes = Storage::disk('local')->get($order->invoice_pdf_path);
        $this->assertSame('%PDF-', substr($invoiceBytes, 0, 5));

        $summaryLog = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->where('recipient_role', 'store')
            ->where('message_type', 'summary')
            ->firstOrFail();

        $text = $summaryLog->payload_json['text'];
        $this->assertStringContainsString('Layanan Berkas Fisik (OFFLINE', $text);
        $this->assertStringContainsString('[Bawa Fisik ke Toko]', $text);
        $this->assertStringContainsString('Barang ATK & Perlengkapan Tambahan', $text);
    }

    public function test_order_and_payment_pages_do_not_leak_alpine_javascript_into_dom(): void
    {
        $orderResp = $this->get(route('fotocopy.order', $this->trialPlace->id));
        $orderResp->assertStatus(200);
        $orderHtml = $orderResp->getContent();

        $this->assertStringContainsString('x-data="usuFotocopyOrderPage()"', $orderHtml);
        $this->assertStringContainsString('window.usuFotocopyOrderPage = function', $orderHtml);

        $orderCheck = $this->inspectHtmlDomXDataAndVisibleText($orderHtml);
        $this->assertSame(1, $orderCheck['xpath']->query('//*[@x-data="usuFotocopyOrderPage()"]')->length);
        foreach ($orderCheck['xDataValues'] as $attr) {
            $this->assertLessThanOrEqual(120, strlen($attr));
            $this->assertStringNotContainsString("\n", $attr);
            $this->assertStringNotContainsString('this.', $attr);
        }

        foreach ([
            'this.offlineCart[key].qty',
            'selectHybridOffline',
            'handleCatalogItemClick',
            'printCartItems',
            'addOrIncrementPrintMenu',
            'decrementPrintMenuQuota',
            'handleSlotFileChange',
            'isCheckoutDisabled',
            'getSlotBreakdown',
            'submitOrder()',
            'window.usuFotocopyOrderPage',
        ] as $leakedJs) {
            $this->assertStringNotContainsString($leakedJs, $orderCheck['visibleText']);
        }

        // Also verify payment instructions page uses usuFotocopyPaymentPage() with zero leaked JS
        $atkItem = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'barang')
            ->firstOrFail();

        $storeResp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Uji DOM Payment',
            'payment_method' => 'qris',
            'items' => json_encode([[
                'service_id' => $atkItem->id,
                'name' => $atkItem->name,
                'category' => $atkItem->category,
                'price' => $atkItem->price,
                'quantity' => 1,
            ]]),
        ], ['Accept' => 'application/json']);

        $storeResp->assertStatus(200);
        $payResp = $this->get(route('fotocopy.orders.payment', $storeResp->json('order_code')));
        $payResp->assertStatus(200);
        $payHtml = $payResp->getContent();

        $this->assertStringContainsString('x-data="usuFotocopyPaymentPage()"', $payHtml);
        $this->assertStringContainsString('window.usuFotocopyPaymentPage = function', $payHtml);

        $payCheck = $this->inspectHtmlDomXDataAndVisibleText($payHtml);
        $this->assertSame(1, $payCheck['xpath']->query('//*[@x-data="usuFotocopyPaymentPage()"]')->length);
        foreach ($payCheck['xDataValues'] as $attr) {
            $this->assertLessThanOrEqual(120, strlen($attr));
            $this->assertStringNotContainsString("\n", $attr);
            $this->assertStringNotContainsString('this.', $attr);
        }

        foreach (['simulatePayment()', 'copyToClipboard'] as $leakedPayJs) {
            $this->assertStringNotContainsString($leakedPayJs, $payCheck['visibleText']);
        }
    }

    public function test_store_order_rejects_mismatched_pdf_quota_vs_uploaded_files(): void
    {
        $initialOrderCount = FotocopyOrder::count();

        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        // Case 1: 2 slot configs provided, but only 1 physical PDF file uploaded => 422
        $respConfigMismatch = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Uji Kuota Kurang 1',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([
                [
                    'page_count' => 2,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'note' => 'Slot 1 terisi',
                ],
                [
                    'page_count' => 1,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'note' => 'Slot 2 kosong tanpa file',
                ],
            ]),
            'pdf_files' => [
                $this->createValidPdfUpload('SatuFileSaja.pdf', 2),
            ],
        ], ['Accept' => 'application/json']);

        $respConfigMismatch->assertStatus(422);
        $this->assertSame($initialOrderCount, FotocopyOrder::count());

        // Case 2: expected_pdf_count = 2 while only 1 file + 1 config uploaded => 422
        $respExpectedMismatch = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Uji Kuota Kurang 2',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'expected_pdf_count' => 2,
            'pdf_configs' => json_encode([
                [
                    'page_count' => 2,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'note' => 'Hanya kirim satu',
                ],
            ]),
            'pdf_files' => [
                $this->createValidPdfUpload('HanyaSatu.pdf', 2),
            ],
        ], ['Accept' => 'application/json']);

        $respExpectedMismatch->assertStatus(422);
        $this->assertSame($initialOrderCount, FotocopyOrder::count());
    }

    public function test_cart_bound_multi_menu_flattened_submission_with_different_quotas_and_hybrid_bundle(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $laminatingSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('name', 'like', '%Laminating%')
            ->firstOrFail();

        $atkItem = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'barang')
            ->firstOrFail();

        // Simulate Menu A (Print, quota=2 -> 2 files) + Menu B (Hybrid Print+Laminating, quota=1 -> 1 file) + ATK (qty=2)
        $response = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Nadia Cart Bound',
            'customer_whatsapp' => '085270009988',
            'payment_method' => 'qris',
            'expected_pdf_count' => 3,
            'items' => json_encode([
                [
                    'service_id' => $atkItem->id,
                    'name' => $atkItem->name,
                    'category' => $atkItem->category,
                    'price' => 1, // tampered price; server resolves $atkItem->price
                    'quantity' => 2,
                    'fulfillment_mode' => 'standard',
                ],
            ]),
            'pdf_configs' => json_encode([
                [
                    'page_count' => 2,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'finishing_service_id' => null,
                    'note' => 'Menu A Slot 1: cetak bolak-balik',
                ],
                [
                    'page_count' => 3,
                    'copies' => 2,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'F4',
                    'finishing_service_id' => null,
                    'note' => 'Menu A Slot 2: kertas F4 80gr',
                ],
                [
                    'page_count' => 1,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'finishing_service_id' => $laminatingSvc->id,
                    'note' => 'Menu B Bundel: laminating sudut rapi',
                ],
            ]),
            'pdf_files' => [
                $this->createValidPdfUpload('MenuA_Slot1.pdf', 2),
                $this->createValidPdfUpload('MenuA_Slot2.pdf', 3),
                $this->createValidPdfUpload('MenuB_BundelLam.pdf', 1),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $order = FotocopyOrder::with(['files', 'items'])
            ->where('order_code', $response->json('order_code'))
            ->firstOrFail();

        $this->assertCount(3, $order->files);

        $expectedTotal = (1 * (2 * $printSvc->price))
            + (2 * (3 * $printSvc->price))
            + (1 * ($printSvc->price + $laminatingSvc->price))
            + (2 * $atkItem->price);

        $this->assertEquals($expectedTotal, $order->total_price);

        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        $order->refresh();

        Storage::disk('local')->assertExists($order->invoice_pdf_path);
        $this->assertSame('%PDF-', substr(Storage::disk('local')->get($order->invoice_pdf_path), 0, 5));

        $storeLogs = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->where('recipient_role', 'store')
            ->orderBy('id')
            ->get();

        $pdfLogs = $storeLogs->where('message_type', 'pdf_attachment')->values();
        $this->assertCount(3, $pdfLogs);
        $this->assertStringContainsString('Menu A Slot 1: cetak bolak-balik', $pdfLogs[0]->payload_json['caption']);
        $this->assertStringContainsString('Menu A Slot 2: kertas F4 80gr', $pdfLogs[1]->payload_json['caption']);
        $this->assertStringContainsString('Menu B Bundel: laminating sudut rapi', $pdfLogs[2]->payload_json['caption']);
        $this->assertStringContainsString('*BUNDEL FINISHING:*', $pdfLogs[2]->payload_json['caption']);
    }

    public function test_signed_pdf_and_invoice_preview_headers_and_unsigned_rejection(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $resp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Rania Uji Preview',
            'customer_whatsapp' => '085270002211',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([[
                'page_count' => 2,
                'copies' => 1,
                'print_service_id' => $printSvc->id,
                'paper_size' => 'A4',
                'note' => 'Preview inline check',
            ]]),
            'pdf_files' => [$this->createValidPdfUpload('Materi_Ujian.pdf', 2)],
        ], ['Accept' => 'application/json'])->assertStatus(200);

        $order = FotocopyOrder::with('files')->where('order_code', $resp->json('order_code'))->firstOrFail();
        $file = $order->files->firstOrFail();

        // Unsigned preview / zip requests MUST return 403
        $this->get(route('fotocopy.orders.files.preview', ['orderCode' => $order->order_code, 'file' => $file->id]))
            ->assertStatus(403);
        $this->get(route('fotocopy.orders.download-zip', ['orderCode' => $order->order_code]))
            ->assertStatus(403);

        // Signed PDF preview MUST return 200 with inline disposition
        $previewResp = $this->get($file->signedPreviewUrl(15));
        $previewResp->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $previewResp->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $previewResp->headers->get('Content-Disposition'));

        // Pay order to generate Invoice PDF
        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        $order->refresh();

        $this->get(route('fotocopy.orders.invoice.preview', ['orderCode' => $order->order_code]))
            ->assertStatus(403);

        $invPreviewResp = $this->get($order->invoicePreviewUrl(15));
        $invPreviewResp->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $invPreviewResp->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $invPreviewResp->headers->get('Content-Disposition'));

        // Signed ZIP archive download MUST contain PDF + Invoice + CATATAN_CETAK.txt
        $zipResp = $this->get($order->signedZipUrl(15));
        $zipResp->assertStatus(200);
        $this->assertStringContainsString('zip', strtolower((string) $zipResp->headers->get('Content-Type')));
        $zipPath = $zipResp->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($zipPath);
        $this->assertContains('CATATAN_CETAK.txt', $entries);
        $this->assertContains('01_Materi_Ujian.pdf', $entries);
        $this->assertTrue(collect($entries)->contains(fn ($e) => str_starts_with($e, 'INV-') && str_ends_with($e, '.pdf')));
    }

    public function test_public_dispatch_whatsapp_guards_unpaid_and_dispatches_full_bundle_when_paid(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $resp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Farhan Uji Dispatch WA',
            'customer_whatsapp' => '085270004455',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([
                [
                    'page_count' => 2,
                    'copies' => 1,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'A4',
                    'note' => 'Note file satu WA',
                ],
                [
                    'page_count' => 3,
                    'copies' => 2,
                    'print_service_id' => $printSvc->id,
                    'paper_size' => 'F4',
                    'note' => 'Note file dua WA',
                ],
            ]),
            'pdf_files' => [
                $this->createValidPdfUpload('Berkas_Satu.pdf', 2),
                $this->createValidPdfUpload('Berkas_Dua.pdf', 3),
            ],
        ], ['Accept' => 'application/json'])->assertStatus(200);

        $order = FotocopyOrder::where('order_code', $resp->json('order_code'))->firstOrFail();

        // Pending order MUST be rejected (422) with ZERO WA logs
        $this->postJson(route('fotocopy.orders.dispatchWhatsapp', $order->order_code))
            ->assertStatus(422);
        $this->assertSame(0, WhatsappDispatchLog::where('fotocopy_order_id', $order->id)->count());

        // Mark paid (triggers initial dispatch), clear logs, then trigger explicit resend via public endpoint
        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        WhatsappDispatchLog::where('fotocopy_order_id', $order->id)->delete();

        $dispatchResp = $this->postJson(route('fotocopy.orders.dispatchWhatsapp', $order->order_code));
        $dispatchResp->assertStatus(200);
        $dispatchResp->assertJson([
            'success' => true,
            'sent_summary' => true,
            'sent_pdfs' => 2,
            'total_pdfs' => 2,
            'sent_invoice' => true,
        ]);

        $storeLogs = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->where('recipient_role', 'store')
            ->get();
        $this->assertSame(1, $storeLogs->where('message_type', 'summary')->count());
        $this->assertSame(2, $storeLogs->where('message_type', 'pdf_attachment')->count());
        $this->assertSame(1, $storeLogs->where('message_type', 'invoice')->count());
    }

    public function test_payment_page_shows_pdf_preview_buttons_rich_wa_link_and_no_alpine_leak(): void
    {
        $printSvc = PhotocopyItem::where('photocopy_place_id', $this->trialPlace->id)
            ->where('item_type', 'jasa')
            ->where('category', 'Print')
            ->firstOrFail();

        $resp = $this->post(route('fotocopy.order.store', $this->trialPlace->id), [
            'customer_name' => 'Salma Payment Preview',
            'customer_whatsapp' => '085270006677',
            'payment_method' => 'qris',
            'items' => json_encode([]),
            'pdf_configs' => json_encode([[
                'page_count' => 2,
                'copies' => 1,
                'print_service_id' => $printSvc->id,
                'paper_size' => 'A4',
                'note' => 'Tolong cetak kertas 80gr jangan blur',
            ]]),
            'pdf_files' => [$this->createValidPdfUpload('Laporan_Bab2.pdf', 2)],
        ], ['Accept' => 'application/json'])->assertStatus(200);

        $order = FotocopyOrder::where('order_code', $resp->json('order_code'))->firstOrFail();
        $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code))->assertStatus(200);
        $order->refresh();

        $payResp = $this->get(route('fotocopy.orders.payment', $order->order_code));
        $payResp->assertStatus(200);
        $html = $payResp->getContent();

        $payResp->assertSee('Preview PDF', false);
        $payResp->assertSee('Preview Invoice', false);
        $payResp->assertSee('Kirim Bukti & File PDF ke WA Toko', false);
        $payResp->assertSee('Buka Chat WA Manual', false);
        $payResp->assertSee('Unduh Semua PDF + Invoice (.zip)', false);

        // Verify serialized usuFotocopyPaymentConfig embeds rich wa.me message with PDF spec, note & Invoice link
        $this->assertSame(1, preg_match('/window\.usuFotocopyPaymentConfig\s*=\s*(\{.*?\});/s', $html, $matches));
        $cfg = json_decode($matches[1], true);
        $this->assertIsArray($cfg);
        $this->assertNotEmpty($cfg['waManualUrl']);

        $parsedQuery = [];
        parse_str((string) parse_url($cfg['waManualUrl'], PHP_URL_QUERY), $parsedQuery);
        $waText = $parsedQuery['text'] ?? '';
        $this->assertStringContainsString($order->invoice_number, $waText);
        $this->assertStringContainsString($order->pickup_code, $waText);
        $this->assertStringContainsString('Laporan_Bab2.pdf', $waText);
        $this->assertStringContainsString('Tolong cetak kertas 80gr jangan blur', $waText);
        $this->assertStringContainsString('/preview', $waText);
        $this->assertStringContainsString('/invoice', $waText);

        // DOM anti-JS leak check
        $check = $this->inspectHtmlDomXDataAndVisibleText($html);
        $this->assertSame(1, $check['xpath']->query('//*[@x-data="usuFotocopyPaymentPage()"]')->length);
        foreach (['openDocPreview', 'dispatchWhatsApp', 'waStatusBadgeText', 'simulatePayment()'] as $leaked) {
            $this->assertStringNotContainsString($leaked, $check['visibleText']);
        }
    }

    public function test_whatsapp_sender_phone_config_and_waha_driver_http_dispatch(): void
    {
        config([
            'whatsapp.default' => 'waha',
            'whatsapp.sender_phone' => '6281283575656',
            'whatsapp.drivers.waha.base_url' => 'http://localhost:3000',
            'whatsapp.drivers.waha.session' => 'default',
            'whatsapp.drivers.waha.api_key' => 'usuclassmap-waha-2026',
        ]);

        $sessionState = 'WORKING';
        Http::fake(function ($request) use (&$sessionState) {
            $url = $request->url();
            if (str_ends_with($url, '/api/sessions/default')) {
                if ($sessionState === 'WORKING') {
                    return Http::response([
                        'name' => 'default',
                        'status' => 'WORKING',
                        'me' => ['id' => '6281283575656@c.us'],
                    ], 200);
                }

                return Http::response([
                    'name' => 'default',
                    'status' => 'SCAN_QR_CODE',
                ], 200);
            }

            if (str_ends_with($url, '/api/sendText')) {
                if ($sessionState !== 'WORKING') {
                    return Http::response(['error' => 'Session status is SCAN_QR_CODE'], 422);
                }
                return Http::response(['id' => 'waha-msg-1'], 201);
            }

            if (str_ends_with($url, '/api/sendFile')) {
                if ($sessionState !== 'WORKING') {
                    return Http::response(['error' => 'Session status is SCAN_QR_CODE'], 422);
                }
                return Http::response(['id' => 'waha-file-1'], 201);
            }

            return Http::response([], 404);
        });

        $summary = app(WhatsAppManager::class)->statusSummary();
        $this->assertSame('waha', $summary['driver']);
        $this->assertTrue($summary['healthy']);
        $this->assertSame('WORKING', $summary['session_status']);
        $this->assertSame('6281283575656', $summary['sender_phone']);
        $this->assertSame('+62 812-8357-5656', $summary['sender_phone_display']);
        $this->assertSame('+62 812-8357-5656', $summary['connected_phone']);
        $this->assertSame('http://localhost:3000/dashboard', $summary['dashboard_url']);

        $driver = new WahaWhatsAppDriver();
        $this->assertTrue($driver->isHealthy());

        $textRes = $driver->sendText('6285769737410', 'Tes ping dari laptop bot');
        $this->assertTrue($textRes['success']);
        $this->assertSame('waha-msg-1', $textRes['external_message_id']);

        $tmpPdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'waha_doc_test_' . uniqid() . '.pdf';
        file_put_contents($tmpPdf, "%PDF-1.4\n%%EOF");
        try {
            $docRes = $driver->sendDocument('6285769737410', $tmpPdf, 'INV-TEST.pdf', 'Lampiran Invoice');
            $this->assertTrue($docRes['success']);
            $this->assertSame('waha-file-1', $docRes['external_message_id']);
        } finally {
            @unlink($tmpPdf);
        }

        // Flip simulated session state to SCAN_QR_CODE -> graceful degradation without throwing
        $sessionState = 'SCAN_QR_CODE';
        $this->assertFalse($driver->isHealthy());

        $qrSummary = app(WhatsAppManager::class)->statusSummary();
        $this->assertFalse($qrSummary['healthy']);
        $this->assertSame('SCAN_QR_CODE', $qrSummary['session_status']);

        $qrTextRes = $driver->sendText('6285769737410', 'Tes saat SCAN_QR_CODE');
        $this->assertFalse($qrTextRes['success']);
        $this->assertStringContainsString('http://localhost:3000/dashboard', (string) $qrTextRes['error']);
        $this->assertStringContainsString('+62 812-8357-5656', (string) $qrTextRes['error']);
    }

    public function test_admin_stores_page_displays_laptop_sender_phone_and_has_no_inline_xdata_leak(): void
    {
        config([
            'whatsapp.default' => 'sandbox',
            'whatsapp.sender_phone' => '6281283575656',
        ]);

        Http::fake([
            'http://localhost:3000/*' => Http::response(['status' => 'SCAN_QR_CODE'], 200),
        ]);

        $adminFotokopi = User::where('email', 'adminfotokopi@usu.ac.id')->firstOrFail();

        $resp = $this->actingAs($adminFotokopi)->get(route('admin-fotokopi.stores.index'));
        $resp->assertStatus(200);
        $html = $resp->getContent();

        $resp->assertSee('+62 812-8357-5656', false);
        $resp->assertSee('x-data="usuAdminStoresPage()"', false);
        $resp->assertSee('Buka Dashboard WAHA (Scan QR)', false);

        $check = $this->inspectHtmlDomXDataAndVisibleText($html);
        $this->assertSame(1, $check['xpath']->query('//*[@x-data="usuAdminStoresPage()"]')->length);
        foreach (['openSandboxViewer', 'sandboxLogs', 'usuAdminStoresPage'] as $leaked) {
            $this->assertStringNotContainsString($leaked, $check['visibleText']);
        }
    }

    /**
     * Parse rendered HTML into DOMDocument/DOMXPath and gather all x-data attributes + non-script visible text.
     *
     * @return array{xpath: \DOMXPath, xDataValues: array<int, string>, visibleText: string}
     */
    protected function inspectHtmlDomXDataAndVisibleText(string $html): array
    {
        $prevUseErrors = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            $xpath = new \DOMXPath($dom);

            $xDataValues = [];
            foreach ($xpath->query('//*[@x-data]') as $node) {
                if ($node instanceof \DOMElement) {
                    $xDataValues[] = $node->getAttribute('x-data');
                }
            }

            $textParts = [];
            foreach ($xpath->query('//body//text()[not(ancestor::script) and not(ancestor::style)]') as $textNode) {
                $textParts[] = $textNode->nodeValue;
            }

            return [
                'xpath' => $xpath,
                'xDataValues' => $xDataValues,
                'visibleText' => implode(' ', $textParts),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prevUseErrors);
        }
    }
}
