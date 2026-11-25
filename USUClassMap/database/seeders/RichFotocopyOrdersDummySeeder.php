<?php

namespace Database\Seeders;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderFile;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\User;
use App\Models\WhatsappDispatchLog;
use App\Observers\GitSnapshotObserver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RichFotocopyOrdersDummySeeder extends Seeder
{
    /**
     * Seeder idempoten yang menambahkan 56 transaksi demo realistis (`ORD-DEMO-001`..`ORD-DEMO-056`)
     * tanpa menghapus order yang sudah ada, lengkap dengan rincian item cetak & ATK,
     * file PDF valid di disk `local`, nomor invoice, kode pengambilan, dan log WA.
     */
    public function run(): void
    {
        $pdfCatalog = $this->ensureDemoPdfFiles();

        GitSnapshotObserver::withoutSync(function () use ($pdfCatalog): void {
            $stores = FotocopyStore::where('is_open', true)->orderBy('id')->get()->keyBy('slug');
            if ($stores->isEmpty()) {
                return;
            }

            $studentUser = User::where('role', 'user')->first();
            $customerNames = [
                'Nadia Putri Siregar', 'Rizky Ramadhan Lubis', 'Siti Aisyah Nasution', 'Bima Pratama Harahap',
                'Dinda Maharani Simanjuntak', 'Fajar Nugroho Batubara', 'Alya Khairunnisa', 'Farhan Alfarizi',
                'Tiara Anggraini', 'Bagas Saputra', 'Putri Salsabila', 'Ilham Maulana Pane',
                'Zahra Aulia', 'M. Rifqi Hakim', 'Khairunisa Tanjung', 'Reza Pahlevi Dalimunthe',
                'Salma Azzahra', 'Gilang Ramadhan', 'Anisa Fitria Matondang', 'Hendra Wijaya',
                'Tasya Sitorus', 'Iqbal Fauzi', 'Vina Oktaviani', 'Aditya Permana Pasaribu',
                'Naufal Azhar', 'Rina Yulianti Hutapea', 'Bayu Setiawan', 'Intan Permata Sari',
            ];

            $targetPlan = $this->buildTargetStoreSlots($stores);
            $createdCount = 0;
            $pdfAttachedCount = 0;
            $waLoggedCount = 0;
            $nowWib = Carbon::now('Asia/Jakarta');

            DB::transaction(function () use (
                $targetPlan,
                $customerNames,
                $pdfCatalog,
                $studentUser,
                $nowWib,
                &$createdCount,
                &$pdfAttachedCount,
                &$waLoggedCount
            ): void {
                foreach ($targetPlan as $idx => $store) {
                    $seq = $idx + 1;
                    $orderCode = sprintf('ORD-DEMO-%03d', $seq);

                    if (FotocopyOrder::where('order_code', $orderCode)->exists()) {
                        continue;
                    }

                    $services = FotocopyService::where('store_id', $store->id)
                        ->where('is_available', true)
                        ->get();
                    if ($services->isEmpty()) {
                        continue;
                    }

                    // Distribusi status realistis (~50% Selesai, ~25% Diproses, ~20% Menunggu, ~5% Dibatalkan)
                    $mod20 = $idx % 20;
                    if ($mod20 < 10) {
                        $orderStatus = 'Selesai';
                        $paymentStatus = 'paid';
                    } elseif ($mod20 < 15) {
                        $orderStatus = 'Diproses';
                        $paymentStatus = 'paid';
                    } elseif ($mod20 < 19) {
                        $orderStatus = 'Menunggu';
                        $paymentStatus = ($idx % 3 === 0) ? 'pending' : 'paid';
                    } else {
                        $orderStatus = 'Dibatalkan';
                        $paymentStatus = 'pending';
                    }

                    // Distribusi tanggal 0..27 hari terakhir (pastikan transaksi hari ini & kemarin tetap ramai)
                    $daysAgo = match (true) {
                        $idx < 6 => 0,
                        $idx < 12 => 1,
                        $idx < 20 => 2 + ($idx % 3),
                        default => 4 + (($idx * 3) % 24),
                    };
                    $hour = 8 + (($idx * 2) % 11);
                    $minute = ($idx * 13) % 60;
                    $createdAt = $nowWib->copy()->subDays($daysAgo)->setTime($hour, $minute, 0);
                    $updatedAt = $createdAt->copy()->addMinutes(12 + ($idx % 45));
                    $paidAt = $paymentStatus === 'paid' ? $createdAt->copy()->addMinutes(3) : null;

                    // Campuran metode pembayaran: QRIS, Transfer Bank, Tunai
                    $methodMod = $idx % 10;
                    if ($methodMod < 5) {
                        $paymentMethod = 'qris';
                        $paymentChannel = 'QRIS';
                    } elseif ($methodMod < 8) {
                        $paymentMethod = 'bank_transfer';
                        $channels = ['BCA', 'BNI', 'Mandiri', 'BRI'];
                        $paymentChannel = $channels[$idx % count($channels)];
                    } else {
                        $paymentMethod = 'cash';
                        $paymentChannel = 'TUNAI';
                    }

                    // Skenario item (0: Multi-PDF, 1: Print+Jilid/Finishing, 2: PDF+ATK, 3: ATK Murni)
                    $scenario = $idx % 4;
                    $chosenItems = $this->pickItemsForScenario($services, $scenario, $idx);
                    if (empty($chosenItems)) {
                        continue;
                    }

                    $totalPrice = array_sum(array_column($chosenItems, 'subtotal'));
                    $customerName = $customerNames[$idx % count($customerNames)];
                    $phoneSuffix = str_pad((string) (1100 + $idx * 17), 4, '0', STR_PAD_LEFT);
                    $waDigits = '62812650' . $phoneSuffix;
                    $waDisplay = '0812-650-' . $phoneSuffix;

                    $isPaid = ($paymentStatus === 'paid');
                    $waStatus = 'not_ready';
                    $waDispatchedAt = null;
                    if ($isPaid) {
                        $waStatus = ($idx % 4 === 0) ? 'sandbox' : 'sent';
                        $waDispatchedAt = $paidAt?->copy()->addSeconds(15);
                    }

                    $scenarioNote = match ($scenario) {
                        0 => 'Tolong urutkan halaman sesuai nomor file, jilid rapi.',
                        1 => 'Print bolak-balik khusus bab isi, cover art carton tebal.',
                        2 => 'Sekalian siapkan ATK dalam 1 kantong saat diambil.',
                        default => 'Ambil langsung di meja kasir hari ini.',
                    };
                    if ($orderStatus === 'Selesai' && $idx % 3 === 0) {
                        $scenarioNote .= ' (Sudah Diambil Pemesan)';
                    }

                    $firstPdf = in_array($scenario, [0, 1, 2], true) ? $pdfCatalog[$idx % count($pdfCatalog)] : null;

                    $order = new FotocopyOrder([
                        'order_code' => $orderCode,
                        'invoice_number' => $isPaid ? sprintf('INV-DEMO-%s-%03d', $createdAt->format('Ymd'), $seq) : null,
                        'pickup_code' => sprintf('USU-%02d%02d', ($store->id % 90) + 10, $seq),
                        'store_id' => $store->id,
                        'user_id' => ($idx % 5 === 0 && $studentUser) ? $studentUser->id : null,
                        'customer_name' => $customerName,
                        'customer_phone' => $waDisplay,
                        'customer_whatsapp' => $waDigits,
                        'notes' => $scenarioNote,
                        'file_path' => $firstPdf['stored_path'] ?? null,
                        'file_name' => $firstPdf['original_name'] ?? null,
                        'file_size' => $firstPdf['formatted_size'] ?? null,
                        'file_type' => $firstPdf ? 'pdf' : null,
                        'payment_method' => $paymentMethod,
                        'payment_channel' => $paymentChannel,
                        'payment_status' => $paymentStatus,
                        'payment_payload' => [
                            'seeded_demo' => true,
                            'scenario' => $scenario,
                            'channel' => $paymentChannel,
                        ],
                        'order_status' => $orderStatus,
                        'pickup_estimate' => ($idx % 3 === 0) ? '15 - 25 Menit' : '30 - 45 Menit',
                        'wa_dispatch_status' => $waStatus,
                        'wa_dispatched_at' => $waDispatchedAt,
                        'total_price' => $totalPrice,
                        'paid_at' => $paidAt,
                    ]);
                    $order->created_at = $createdAt;
                    $order->updated_at = $updatedAt;
                    $order->save();

                    foreach ($chosenItems as $ci) {
                        $itemModel = new FotocopyOrderItem([
                            'order_id' => $order->id,
                            'service_id' => $ci['service_id'],
                            'service_name' => $ci['service_name'],
                            'category' => $ci['category'],
                            'price' => $ci['price'],
                            'quantity' => $ci['quantity'],
                            'subtotal' => $ci['subtotal'],
                        ]);
                        $itemModel->created_at = $createdAt;
                        $itemModel->updated_at = $updatedAt;
                        $itemModel->save();
                    }

                    // Lampirkan 1-2 file PDF valid untuk skenario dokumen (0, 1, 2)
                    if (in_array($scenario, [0, 1, 2], true)) {
                        $fileSlots = ($scenario === 0) ? 2 : 1;
                        $fileNotes = [
                            'Cetak HVS 80gsm, mulai halaman 1 tanpa halaman kosong.',
                            'Mohon diperbesar margin kiri 4cm untuk jilid spiral.',
                            'Print warna khusus halaman grafik & tabel saja.',
                        ];

                        for ($f = 0; $f < $fileSlots; $f++) {
                            $pdf = $pdfCatalog[($idx + $f) % count($pdfCatalog)];
                            $orderFile = new FotocopyOrderFile([
                                'fotocopy_order_id' => $order->id,
                                'original_name' => $pdf['original_name'],
                                'stored_path' => $pdf['stored_path'],
                                'storage_disk' => 'local',
                                'mime_type' => 'application/pdf',
                                'file_size' => $pdf['file_size'],
                                'page_count' => $pdf['page_count'],
                                'copies' => ($f === 0) ? 1 : 2,
                                'print_mode' => ($idx % 2 === 0) ? 'Print Hitam Putih A4' : 'Print Warna A4',
                                'paper_size' => 'A4',
                                'finishing_option' => ($scenario === 1) ? 'Jilid Lakban + Mika' : null,
                                'note' => $fileNotes[($idx + $f) % count($fileNotes)],
                                'subtotal_estimated' => (int) round($totalPrice / $fileSlots),
                                'sort_order' => $f + 1,
                                'is_encrypted' => false,
                            ]);
                            $orderFile->created_at = $createdAt;
                            $orderFile->updated_at = $updatedAt;
                            $orderFile->save();
                            $pdfAttachedCount++;
                        }
                    }

                    // Catat log pengiriman WhatsApp realistis untuk pesanan yang sudah dibayar
                    if ($isPaid) {
                        $storePhone = preg_replace('/[^0-9]/', '', (string) ($store->whatsapp ?: $store->phone ?: '6285769737410'));
                        $waDriver = ($waStatus === 'sandbox') ? 'sandbox' : 'waha';
                        $storeLog = new WhatsappDispatchLog([
                            'fotocopy_order_id' => $order->id,
                            'recipient_phone' => $storePhone ?: '6285769737410',
                            'recipient_role' => 'store',
                            'message_type' => $firstPdf ? 'order_document' : 'order_summary',
                            'payload_json' => [
                                'text' => "*PESANAN BARU #{$orderCode}*\nToko: {$store->name}\nPemesan: {$customerName}\nTotal: Rp " . number_format($totalPrice, 0, ',', '.'),
                                'file_name' => $firstPdf['original_name'] ?? null,
                            ],
                            'attachment_path' => $firstPdf['stored_path'] ?? null,
                            'driver' => $waDriver,
                            'external_message_id' => 'demo_' . strtolower($orderCode) . '_store',
                            'status' => $waStatus === 'sandbox' ? 'simulated' : 'sent',
                            'attempts' => 1,
                            'sent_at' => $waDispatchedAt,
                        ]);
                        $storeLog->created_at = $createdAt;
                        $storeLog->updated_at = $updatedAt;
                        $storeLog->save();
                        $waLoggedCount++;

                        if ($orderStatus === 'Selesai') {
                            $custLog = new WhatsappDispatchLog([
                                'fotocopy_order_id' => $order->id,
                                'recipient_phone' => $waDigits,
                                'recipient_role' => 'customer',
                                'message_type' => 'ready_notification',
                                'payload_json' => [
                                    'text' => "Halo {$customerName}, pesanan #{$orderCode} di {$store->name} telah SELESAI dicetak. Kode Ambil: {$order->pickup_code}.",
                                ],
                                'driver' => $waDriver,
                                'external_message_id' => 'demo_' . strtolower($orderCode) . '_cust',
                                'status' => $waStatus === 'sandbox' ? 'simulated' : 'sent',
                                'attempts' => 1,
                                'sent_at' => $updatedAt,
                            ]);
                            $custLog->created_at = $updatedAt;
                            $custLog->updated_at = $updatedAt;
                            $custLog->save();
                            $waLoggedCount++;
                        }
                    }

                    $createdCount++;
                }
            });

            if ($this->command) {
                $this->command->table(
                    ['Metrik Seeder Transaksi Demo', 'Nilai'],
                    [
                        ['Pesanan Demo Baru Ditambahkan', (string) $createdCount],
                        ['Lampiran File PDF Terdaftar', (string) $pdfAttachedCount],
                        ['Log WhatsApp Tercatat', (string) $waLoggedCount],
                        ['Total Seluruh Order di Database', (string) FotocopyOrder::count()],
                    ]
                );
            }
        });
    }

    /**
     * Susun 56 slot transaksi lintas toko utama (Pak Budi FT, Berkah Abadi, Trial Admin, USU Press, & mitra fakultas lain).
     *
     * @param  \Illuminate\Support\Collection<string, FotocopyStore>  $stores
     * @return array<int, FotocopyStore>
     */
    protected function buildTargetStoreSlots($stores): array
    {
        $slots = [];
        $quotas = [
            'fotocopy-pak-budi-ft' => 12,
            'berkah-abadi-copy-atk-perpustakaan' => 12,
            'fotocopy-trial-admin' => 10,
            'usu-press-penerbit-fotocopy' => 10,
        ];

        foreach ($quotas as $slug => $qty) {
            $store = $stores->get($slug) ?? $stores->first();
            for ($i = 0; $i < $qty; $i++) {
                $slots[] = $store;
            }
        }

        $remainingStores = $stores->reject(fn ($s, $slug) => array_key_exists((string) $slug, $quotas))->values();
        if ($remainingStores->isEmpty()) {
            $remainingStores = $stores->values();
        }

        for ($i = 0; $i < 12; $i++) {
            $slots[] = $remainingStores->get($i % $remainingStores->count());
        }

        return $slots;
    }

    /**
     * Pilih 1-3 item realistis per skenario transaksi.
     *
     * @param  \Illuminate\Support\Collection<int, FotocopyService>  $services
     * @return array<int, array<string, mixed>>
     */
    protected function pickItemsForScenario($services, int $scenario, int $seed): array
    {
        $prints = $services->filter(fn (FotocopyService $s) => in_array($s->category, ['Print', 'Fotocopy'], true))->values();
        $jilids = $services->filter(fn (FotocopyService $s) => in_array($s->category, ['Jilid', 'Finishing', 'Digital'], true))->values();
        $atks = $services->filter(fn (FotocopyService $s) => $s->category === 'ATK')->values();
        $pinkOrTipex = $atks->filter(fn (FotocopyService $s) => preg_match('/pink|tipe-x|correction|stabilo|map|materai/i', $s->name))->values();
        if ($pinkOrTipex->isEmpty()) {
            $pinkOrTipex = $atks;
        }

        $chosen = [];
        $addRow = static function (FotocopyService $srv, int $qty) use (&$chosen): void {
            $price = max(100, (int) $srv->price);
            $chosen[] = [
                'service_id' => $srv->id,
                'service_name' => $srv->name,
                'category' => $srv->category,
                'price' => $price,
                'quantity' => $qty,
                'subtotal' => $price * $qty,
            ];
        };

        if ($scenario === 0 && $prints->isNotEmpty()) {
            $p = $prints->get($seed % $prints->count());
            $qty = ($p->price <= 1000) ? (12 + ($seed % 20)) : (2 + ($seed % 3));
            $addRow($p, $qty);
        } elseif ($scenario === 1 && $prints->isNotEmpty()) {
            $p = $prints->get($seed % $prints->count());
            $qty = ($p->price <= 1000) ? (16 + ($seed % 18)) : 2;
            $addRow($p, $qty);
            if ($jilids->isNotEmpty()) {
                $j = $jilids->get($seed % $jilids->count());
                $addRow($j, 1 + ($seed % 2));
            }
        } elseif ($scenario === 2 && ($prints->isNotEmpty() || $pinkOrTipex->isNotEmpty())) {
            if ($prints->isNotEmpty()) {
                $p = $prints->get($seed % $prints->count());
                $addRow($p, ($p->price <= 1000) ? (8 + ($seed % 12)) : 1);
            }
            if ($pinkOrTipex->isNotEmpty()) {
                $a = $pinkOrTipex->get($seed % $pinkOrTipex->count());
                $addRow($a, 1 + ($seed % 3));
            }
        } elseif ($pinkOrTipex->isNotEmpty()) {
            $a1 = $pinkOrTipex->get($seed % $pinkOrTipex->count());
            $addRow($a1, 1 + ($seed % 3));
            if ($pinkOrTipex->count() > 1) {
                $a2 = $pinkOrTipex->get(($seed + 1) % $pinkOrTipex->count());
                if ($a2->id !== $a1->id) {
                    $addRow($a2, 1);
                }
            }
        } else {
            $fallback = $services->first();
            $addRow($fallback, 2);
        }

        return $chosen;
    }

    /**
     * Buat 3 file PDF 1.4 multi-halaman valid di disk `local` (`storage/app/private/fotocopy_orders/demo/`).
     *
     * @return array<int, array{original_name: string, stored_path: string, file_size: int, formatted_size: string, page_count: int}>
     */
    protected function ensureDemoPdfFiles(): array
    {
        $specs = [
            [
                'original_name' => 'Makalah_Web_Semantik_USU.pdf',
                'stored_path' => 'fotocopy_orders/demo/makalah_web_semantik_usu.pdf',
                'title' => 'Makalah Web Semantik & Graph SPARQL USU',
                'pages' => 8,
            ],
            [
                'original_name' => 'Bab1_Skripsi_Fasilkom_TI.pdf',
                'stored_path' => 'fotocopy_orders/demo/bab1_skripsi_fasilkom_ti.pdf',
                'title' => 'Bab 1 Proposal Skripsi Fasilkom-TI USU',
                'pages' => 14,
            ],
            [
                'original_name' => 'Modul_Praktikum_Basis_Data.pdf',
                'stored_path' => 'fotocopy_orders/demo/modul_praktikum_basis_data.pdf',
                'title' => 'Modul Praktikum Basis Data Relasional USU',
                'pages' => 12,
            ],
        ];

        $disk = Storage::disk('local');
        $out = [];

        foreach ($specs as $spec) {
            if (!$disk->exists($spec['stored_path'])) {
                $pdfBytes = $this->generateValidPdfBytes($spec['title'], $spec['pages']);
                $disk->put($spec['stored_path'], $pdfBytes);
            }
            $size = (int) $disk->size($spec['stored_path']);
            $out[] = [
                'original_name' => $spec['original_name'],
                'stored_path' => $spec['stored_path'],
                'file_size' => $size,
                'formatted_size' => max(1, (int) round($size / 1024)) . ' KB',
                'page_count' => $spec['pages'],
            ];
        }

        return $out;
    }

    /**
     * Bangun struktur binary PDF 1.4 valid dengan xref presisi agar selalu terbuka di preview browser & PdfMetadataInspectorService.
     */
    protected function generateValidPdfBytes(string $title, int $pageCount): string
    {
        $pageCount = max(1, $pageCount);
        $objects = [];

        // Obj 1: Catalog, Obj 2: Pages, Obj 3: Helvetica Font
        $kidsRefs = [];
        $nextObj = 4;
        $pageObjects = [];

        for ($p = 1; $p <= $pageCount; $p++) {
            $pageObjNum = $nextObj++;
            $contentObjNum = $nextObj++;
            $kidsRefs[] = "{$pageObjNum} 0 R";

            $safeTitle = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);
            $stream = "BT /F1 16 Tf 56 760 Td ({$safeTitle}) Tj 0 -28 Td /F1 11 Tf (Halaman {$p} dari {$pageCount} - Dokumen Demo Siap Cetak USUClassMap) Tj ET\n";
            $len = strlen($stream);

            $pageObjects[] = [$pageObjNum, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObjNum} 0 R >>"];
            $pageObjects[] = [$contentObjNum, "<< /Length {$len} >>\nstream\n{$stream}endstream"];
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kidsRefs) . "] /Count {$pageCount} >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($pageObjects as [$num, $body]) {
            $objects[$num] = $body;
        }
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjs = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjs}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
