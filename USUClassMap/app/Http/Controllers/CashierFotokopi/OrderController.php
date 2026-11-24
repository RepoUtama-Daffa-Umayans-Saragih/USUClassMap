<?php

namespace App\Http\Controllers\CashierFotokopi;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchOrderToWhatsAppJob;
use App\Jobs\SendCustomerReadyNotificationJob;
use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderFile;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Services\Fotocopy\OrderPaymentFulfillmentService;
use App\Services\Invoice\OrderInvoiceService;
use App\Services\Pdf\PdfMetadataInspectorService;
use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class OrderController extends Controller
{
    /**
     * Tampilan Antarmuka Kasir: Antrean Pesanan Masuk & Operasional Harian.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Cari toko yang dikelola kasir ini, atau ambil toko default pertama jika belum di-assign
        $myStore = FotocopyStore::where('user_id', $user->id)->first() ?? FotocopyStore::first();

        $query = FotocopyOrder::with(['items', 'store', 'files', 'whatsappLogs']);

        if ($myStore && !$user->isAdmin()) {
            $query->where('store_id', $myStore->id);
        }

        // Filter status pesanan
        if ($request->filled('status') && $request->input('status') !== 'semua') {
            $query->where('order_status', $request->input('status'));
        }

        // Pencarian cepat
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sq) use ($q) {
                $sq->where('order_code', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        // Hitung badge status
        $baseStoreQuery = FotocopyOrder::query();
        if ($myStore && !$user->isAdmin()) {
            $baseStoreQuery->where('store_id', $myStore->id);
        }

        $countMenunggu = (clone $baseStoreQuery)->where('order_status', 'Menunggu')->count();
        $countDiproses = (clone $baseStoreQuery)->where('order_status', 'Diproses')->count();
        $countSelesai = (clone $baseStoreQuery)->where('order_status', 'Selesai')->count();
        $totalOrdersCount = (clone $baseStoreQuery)->count();

        $orders = $query->latest()->paginate(12)->withQueryString();

        return view('features.fotocopy.cashier.orders.index', compact(
            'orders',
            'myStore',
            'countMenunggu',
            'countDiproses',
            'countSelesai',
            'totalOrdersCount'
        ));
    }

    /**
     * Update status pesanan oleh kasir (Menunggu -> Diproses -> Selesai / Dibatalkan).
     */
    public function updateStatus(Request $request, FotocopyOrder $order)
    {
        $validated = $request->validate([
            'order_status' => 'required|in:Menunggu,Diproses,Selesai,Dibatalkan',
            'payment_status' => 'nullable|in:pending,paid',
        ]);

        $wasPaid = $order->isPaid();
        $oldStatus = $order->order_status;
        $updateData = ['order_status' => $validated['order_status']];

        // Jika selesai, otomatis tandai lunas (paid) jika belum
        if ($validated['order_status'] === 'Selesai') {
            $updateData['payment_status'] = 'paid';
            if (!$order->paid_at) {
                $updateData['paid_at'] = now();
            }
        } elseif (isset($validated['payment_status'])) {
            $updateData['payment_status'] = $validated['payment_status'];
            if ($validated['payment_status'] === 'paid' && !$order->paid_at) {
                $updateData['paid_at'] = now();
            }
        }

        $order->update($updateData);
        $order->refresh();

        if (!$wasPaid && $order->isPaid()) {
            app(OrderPaymentFulfillmentService::class)->handlePaidOrder($order, true);
        }

        if ($oldStatus !== 'Selesai' && $order->order_status === 'Selesai' && class_exists(SendCustomerReadyNotificationJob::class)) {
            SendCustomerReadyNotificationJob::dispatch($order->id);
        }

        return redirect()->back()->with('success', "Status pesanan #{$order->order_code} berhasil diperbarui menjadi '{$validated['order_status']}'!");
    }

    /**
     * Form Point-of-Sale (POS) Kasir untuk Pesanan Walk-in / Langsung di Counter.
     */
    public function createPos()
    {
        $user = auth()->user();
        $myStore = FotocopyStore::where('user_id', $user->id)->first() ?? FotocopyStore::first();
        $services = FotocopyService::where('store_id', $myStore->id)
            ->where('is_available', true)
            ->orderByRaw("CASE WHEN item_type = 'jasa' THEN 0 ELSE 1 END")
            ->orderBy('category')
            ->orderBy('sub_category')
            ->orderBy('name')
            ->get();

        $catalog = $services->map->toCatalogArray()->values()->all();

        return view('features.fotocopy.cashier.orders.pos', compact('myStore', 'services', 'catalog'));
    }

    /**
     * Simpan pesanan walk-in dari POS Kasir (mendukung multi-file + catatan per file).
     */
    public function storePos(Request $request, PdfMetadataInspectorService $pdfInspector)
    {
        $user = auth()->user();
        $myStore = FotocopyStore::where('user_id', $user->id)->first() ?? FotocopyStore::first();

        $validated = $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
            'file' => 'nullable|file|max:25600',
            'pos_files' => 'nullable|array|max:10',
            'pos_files.*' => 'file|max:25600',
            'pos_file_notes' => 'nullable|array',
            'pos_file_notes.*' => 'nullable|string|max:500',
            'pos_file_pages' => 'nullable|array',
            'pos_file_pages.*' => 'nullable|integer|min:1|max:5000',
            'pos_file_copies' => 'nullable|array',
            'pos_file_copies.*' => 'nullable|integer|min:1|max:500',
            'payment_method' => 'required|in:QRIS,Tunai,Transfer',
            'order_status' => 'required|in:Diproses,Selesai',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|exists:fotocopy_services,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $orderCode = 'POS-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        $uploadedList = [];

        $multiFiles = $request->file('pos_files', []);
        if (is_array($multiFiles)) {
            foreach ($multiFiles as $idx => $upFile) {
                if ($upFile && $upFile->isValid()) {
                    $uploadedList[] = [
                        'file' => $upFile,
                        'note' => $validated['pos_file_notes'][$idx] ?? null,
                        'pages' => (int) ($validated['pos_file_pages'][$idx] ?? 1),
                        'copies' => (int) ($validated['pos_file_copies'][$idx] ?? 1),
                    ];
                }
            }
        }

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $uploadedList[] = [
                'file' => $request->file('file'),
                'note' => null,
                'pages' => 1,
                'copies' => 1,
            ];
        }

        $totalPrice = 0;
        $orderItemsData = [];

        foreach ($validated['items'] as $item) {
            $service = FotocopyService::find($item['service_id']);
            if ($service) {
                $subtotal = $service->price * $item['quantity'];
                $totalPrice += $subtotal;
                $orderItemsData[] = [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'category' => $service->category,
                    'price' => $service->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }
        }

        $normalizedWa = WhatsAppPhoneNormalizer::normalize($validated['customer_phone'] ?? null);
        $pickupCode = $pdfInspector->generatePickupCode();
        $pickupEstimate = $pdfInspector->estimatePickupTime(max(1, array_sum(array_column($uploadedList, 'pages'))));

        $order = FotocopyOrder::create([
            'order_code' => $orderCode,
            'pickup_code' => $pickupCode,
            'store_id' => $myStore->id,
            'user_id' => $user->id,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_whatsapp' => $normalizedWa,
            'notes' => $validated['notes'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'paid',
            'paid_at' => now(),
            'order_status' => $validated['order_status'],
            'pickup_estimate' => $pickupEstimate,
            'wa_dispatch_status' => 'not_ready',
            'total_price' => $totalPrice,
        ]);

        foreach ($orderItemsData as $itemData) {
            $order->items()->create($itemData);
        }

        $firstFileMeta = null;
        foreach ($uploadedList as $idx => $entry) {
            /** @var \Illuminate\Http\UploadedFile $uploaded */
            $uploaded = $entry['file'];
            $origName = $uploaded->getClientOriginalName();
            $ext = strtolower($uploaded->getClientOriginalExtension() ?: 'pdf');
            $bytes = (int) $uploaded->getSize();
            $storedPath = $pdfInspector->storePrivateFile($uploaded, $order->order_code, $idx + 1, $ext);

            $pageCount = max(1, (int) $entry['pages']);
            if ($ext === 'pdf' && $pdfInspector->hasValidPdfMagicBytes($uploaded)) {
                $raw = @file_get_contents($uploaded->getRealPath());
                if ($raw !== false) {
                    $detected = $pdfInspector->estimatePdfPageCountFromContent($raw);
                    if ($detected && $detected > 0) {
                        $pageCount = $detected;
                    }
                }
            }

            $fileRecord = FotocopyOrderFile::create([
                'fotocopy_order_id' => $order->id,
                'original_name' => $origName,
                'stored_path' => $storedPath,
                'storage_disk' => 'local',
                'mime_type' => $uploaded->getMimeType() ?: ($ext === 'pdf' ? 'application/pdf' : 'application/octet-stream'),
                'file_size' => $bytes,
                'page_count' => $pageCount,
                'copies' => max(1, (int) $entry['copies']),
                'print_mode' => $orderItemsData[0]['service_name'] ?? 'Walk-in POS',
                'paper_size' => 'A4',
                'note' => !empty($entry['note']) ? trim((string) $entry['note']) : null,
                'subtotal_estimated' => 0,
                'sort_order' => $idx + 1,
                'is_encrypted' => false,
            ]);

            if ($firstFileMeta === null) {
                $firstFileMeta = [
                    'file_path' => $storedPath,
                    'file_name' => $origName,
                    'file_size' => PdfMetadataInspectorService::formatBytes($bytes),
                    'file_type' => $ext,
                ];
            }
        }

        if ($firstFileMeta !== null) {
            $order->update($firstFileMeta);
        }

        app(OrderPaymentFulfillmentService::class)->handlePaidOrder($order->fresh(), false);

        return redirect()->route('cashier-fotokopi.orders.index')
            ->with('success', "Pesanan kasir #{$order->order_code} berhasil dibuat dan tercatat!");
    }

    /**
     * Download atau unduh file lampiran tunggal dokumen pesanan (prioritas disk privat 'local').
     */
    public function downloadFile(Request $request, FotocopyOrder $order)
    {
        $this->authorizeStoreAccess($order);

        if ($order->file_path) {
            if (Storage::disk('local')->exists($order->file_path)) {
                if ($request->boolean('inline')) {
                    return response()->file(Storage::disk('local')->path($order->file_path), [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . ($order->file_name ?? 'dokumen.pdf') . '"',
                    ]);
                }
                return Storage::disk('local')->download($order->file_path, $order->file_name ?? 'dokumen.pdf');
            }

            if (Storage::disk('public')->exists($order->file_path)) {
                return Storage::disk('public')->download($order->file_path, $order->file_name ?? 'dokumen.pdf');
            }
        }

        return redirect()->back()->with('error', 'File fisik dokumen belum tersedia di server storage.');
    }

    /**
     * Pratinjau atau unduh satuan salah satu file dari relasi multi-PDF (`fotocopy_order_files`).
     */
    public function downloadOrderFile(Request $request, FotocopyOrder $order, FotocopyOrderFile $file)
    {
        $this->authorizeStoreAccess($order);

        if ((int) $file->fotocopy_order_id !== (int) $order->id) {
            abort(404);
        }

        if (!$file->isPhysicallyAvailable()) {
            return redirect()->back()->with('error', "File '{$file->original_name}' sudah tidak tersedia atau telah dibersihkan.");
        }

        $disk = $file->storage_disk ?: 'local';
        if ($request->boolean('inline')) {
            return response()->file(Storage::disk($disk)->path($file->stored_path), [
                'Content-Type' => $file->mime_type ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $file->original_name . '"',
            ]);
        }

        return Storage::disk($disk)->download($file->stored_path, $file->original_name, [
            'Content-Type' => $file->mime_type ?: 'application/pdf',
        ]);
    }

    /**
     * Unduh Bukti Invoice PDF Resmi dari panel kasir.
     */
    public function downloadInvoice(FotocopyOrder $order, OrderInvoiceService $invoiceService)
    {
        $this->authorizeStoreAccess($order);

        if (!$order->isPaid()) {
            abort(403, 'Invoice resmi hanya dapat diunduh setelah pesanan Lunas.');
        }

        if (empty($order->invoice_pdf_path) || !Storage::disk('local')->exists($order->invoice_pdf_path)) {
            $invoiceService->generateForOrder($order);
            $order->refresh();
        }

        return Storage::disk('local')->download(
            $order->invoice_pdf_path,
            ($order->invoice_number ?: $order->order_code) . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Kemas seluruh PDF pesanan + file CATATAN_CETAK.txt (+ Invoice jika ada) menjadi 1 arsip .zip.
     */
    public function downloadAllZip(FotocopyOrder $order)
    {
        $this->authorizeStoreAccess($order);
        $order->load(['items', 'files', 'store']);

        if (!class_exists(ZipArchive::class)) {
            return redirect()->back()->with('error', 'Ekstensi PHP ZipArchive tidak aktif di server.');
        }

        $tempDir = storage_path('app/private/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'order-' . $order->order_code . '-' . Str::lower(Str::random(6)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'Gagal membuat arsip ZIP dokumen.');
        }

        $txtLines = [
            "=== SURAT PERINTAH KERJA (SPK) CETAK - USUSTARTERMAP ===",
            "Kode Order    : #{$order->order_code}",
            'No. Invoice   : ' . ($order->invoice_number ?: '-'),
            'Kode Ambil    : ' . ($order->pickup_code ?: '-'),
            "Nama Pemesan  : {$order->customer_name}",
            'No. WA / HP   : ' . WhatsAppPhoneNormalizer::formatDisplay($order->customer_whatsapp ?: $order->customer_phone),
            "Status Bayar  : {$order->payment_status} ({$order->getPaymentMethodLabel()})",
            "Total Tagihan : {$order->getFormattedTotal()}",
            'Catatan Umum  : ' . ($order->notes ?: '-'),
            '',
            '--- DAFTAR FILE DOKUMEN ---',
        ];

        $attachedCount = 0;
        if ($order->files->isNotEmpty()) {
            foreach ($order->files as $idx => $file) {
                $num = $idx + 1;
                $txtLines[] = sprintf(
                    "%d. %s | %d hal x %d rangkap | %s (%s)%s",
                    $num,
                    $file->original_name,
                    $file->page_count,
                    $file->copies,
                    $file->print_mode,
                    $file->paper_size,
                    $file->finishing_option ? " + {$file->finishing_option}" : ''
                );
                $txtLines[] = '   CATATAN KHUSUS: ' . ($file->note ?: '-');

                $disk = $file->storage_disk ?: 'local';
                if (!empty($file->stored_path) && Storage::disk($disk)->exists($file->stored_path)) {
                    $safeEntryName = sprintf('%02d_%s', $num, basename($file->original_name));
                    $zip->addFromString($safeEntryName, (string) Storage::disk($disk)->get($file->stored_path));
                    $attachedCount++;
                }
            }
        } elseif ($order->file_path) {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($order->file_path)) {
                    $zip->addFromString('01_' . ($order->file_name ?: 'dokumen.pdf'), (string) Storage::disk($disk)->get($order->file_path));
                    $attachedCount++;
                    break;
                }
            }
        }

        if (!empty($order->invoice_pdf_path) && Storage::disk('local')->exists($order->invoice_pdf_path)) {
            $zip->addFromString(
                ($order->invoice_number ?: 'INVOICE') . '.pdf',
                (string) Storage::disk('local')->get($order->invoice_pdf_path)
            );
        }

        $txtLines[] = '';
        $txtLines[] = '--- RINCIAN ITEM ORDER ---';
        foreach ($order->items as $it) {
            $txtLines[] = "- {$it->service_name} (x{$it->quantity}) = Rp " . number_format($it->subtotal ?? ($it->price * $it->quantity), 0, ',', '.');
        }

        $zip->addFromString('CATATAN_CETAK.txt', implode(PHP_EOL, $txtLines) . PHP_EOL);
        $zip->close();

        if ($attachedCount === 0 && !$order->invoice_pdf_path) {
            @unlink($zipPath);
            return redirect()->back()->with('error', 'Belum ada file PDF fisik yang tersedia untuk diunduh.');
        }

        return response()
            ->download($zipPath, "Order_{$order->order_code}_Berkas.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Kirim ulang paket Ringkasan + Semua PDF + Note + Invoice ke WhatsApp toko secara manual dari panel kasir.
     */
    public function resendWhatsApp(FotocopyOrder $order)
    {
        $this->authorizeStoreAccess($order);

        if (!$order->isPaid()) {
            return redirect()->back()->with('error', "Pesanan #{$order->order_code} belum berstatus Lunas. Pengiriman WhatsApp otomatis hanya aktif untuk pesanan terbayar.");
        }

        $order->update(['wa_dispatch_status' => 'queued']);
        DispatchOrderToWhatsAppJob::dispatch($order->id, true);

        return redirect()->back()->with('success', "Pengiriman ulang paket dokumen & invoice order #{$order->order_code} ke WhatsApp toko telah dijalankan.");
    }

    /**
     * Halaman / Dialog Cetak Khusus: Surat Perintah Kerja (SPK) Cetak & Nota Transaksi Kasir.
     */
    public function printJob(FotocopyOrder $order)
    {
        $this->authorizeStoreAccess($order);
        $order->load(['items', 'store', 'files']);
        return view('features.fotocopy.cashier.orders.print', compact('order'));
    }

    protected function authorizeStoreAccess(FotocopyOrder $order): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if ($user->isAdmin() || $user->isAdminFotokopi()) {
            return;
        }

        $myStore = FotocopyStore::where('user_id', $user->id)->first();
        if ($myStore && (int) $order->store_id !== (int) $myStore->id) {
            abort(403, 'Akses ditolak: pesanan ini milik mitra toko lain.');
        }
    }
}
