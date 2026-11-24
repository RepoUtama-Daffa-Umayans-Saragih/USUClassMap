<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchOrderToWhatsAppJob;
use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderFile;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\PhotocopyItem;
use App\Models\WhatsappDispatchLog;
use App\Services\Fotocopy\OrderArchiveService;
use App\Services\Fotocopy\OrderPaymentFulfillmentService;
use App\Services\Invoice\OrderInvoiceService;
use App\Services\Payment\PaymentManager;
use App\Services\Pdf\PdfMetadataInspectorService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppPayloadFormatter;
use App\Support\CatalogBehaviorClassifier;
use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PhotocopyController extends Controller
{
    /**
     * Pasangkan PhotocopyPlace ke FotocopyStore melalui slug (atau nama), bukan raw ID.
     */
    protected function resolveStore(PhotocopyPlace $place): ?FotocopyStore
    {
        return FotocopyStore::where('slug', $place->slug)->first()
            ?? FotocopyStore::where('name', $place->name)->first()
            ?? FotocopyStore::find($place->id);
    }

    /**
     * Siapkan daftar item katalog terurut (Jasa lebih dulu, lalu kategori & nama).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildCatalogItems(PhotocopyPlace $place): array
    {
        return $place->items
            ->where('is_available', true)
            ->sort(function ($a, $b): int {
                $ta = $a->item_type === 'jasa' ? 0 : 1;
                $tb = $b->item_type === 'jasa' ? 0 : 1;

                return [$ta, (string) $a->category, (string) $a->sub_category, (string) $a->name]
                    <=> [$tb, (string) $b->category, (string) $b->sub_category, (string) $b->name];
            })
            ->map->toCatalogArray()
            ->values()
            ->all();
    }
    /**
     * Display a listing of photocopy places with search and sideways cards.
     */
    public function index(Request $request)
    {
        $search = $request->input('q');
        $filter = $request->input('status'); // 'all', 'open'

        $query = PhotocopyPlace::with('items')->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('items', function ($itemQ) use ($search) {
                      $itemQ->where('name', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%");
                  });
            });
        }

        $allPlaces = $query->orderBy('name')->get();

        // Ensure default dummy items for any place without items
        foreach ($allPlaces as $place) {
            if ($place->items->isEmpty()) {
                $place->seedDefaultItemsAndServices();
                $place->load('items');
            }
        }

        // Separate into currently open places for horizontal scrolling section
        $openPlaces = $allPlaces->filter(function ($place) {
            return $place->isOpenNow();
        });

        // If user specifically asked for only open places
        $places = ($filter === 'open') ? $openPlaces : $allPlaces;

        $openCount = $openPlaces->count();
        $totalCount = $allPlaces->count();

        return view('features.fotocopy.public.index', [
            'places' => $places,
            'openPlaces' => $openPlaces,
            'search' => $search,
            'filter' => $filter,
            'openCount' => $openCount,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Display the specified photocopy place details.
     */
    public function show($id)
    {
        $place = is_numeric($id)
            ? PhotocopyPlace::with(['items', 'services', 'reviews'])->findOrFail($id)
            : PhotocopyPlace::with(['items', 'services', 'reviews'])->where('slug', $id)->firstOrFail();

        if ($place->items->isEmpty() || $place->services->isEmpty()) {
            $place->seedDefaultItemsAndServices();
            $place->load(['items', 'services']);
        }

        $isOpen = $place->isOpenNow();
        $itemsByCategory = $place->items->where('is_available', true)->groupBy('category');
        $catalogItems = $this->buildCatalogItems($place);

        // Other nearby / recommended places
        $nearbyPlaces = PhotocopyPlace::where('id', '!=', $place->id)
            ->where('is_active', true)
            ->limit(3)
            ->get();

        return view('features.fotocopy.public.show', [
            'place' => $place,
            'isOpen' => $isOpen,
            'itemsByCategory' => $itemsByCategory,
            'catalogItems' => $catalogItems,
            'nearbyPlaces' => $nearbyPlaces,
        ]);
    }

    /**
     * Display the order page for a specific photocopy place.
     */
    public function order($id)
    {
        $place = is_numeric($id)
            ? PhotocopyPlace::with('items')->findOrFail($id)
            : PhotocopyPlace::with('items')->where('slug', $id)->firstOrFail();

        if ($place->items->isEmpty()) {
            $place->seedDefaultItemsAndServices();
            $place->load('items');
        }

        $isOpen = $place->isOpenNow();
        $itemsByCategory = $place->items->where('is_available', true)->groupBy('category');
        $catalogItems = $this->buildCatalogItems($place);

        $printServices = collect($catalogItems)
            ->filter(fn (array $it): bool => ($it['order_behavior'] ?? '') === CatalogBehaviorClassifier::DIGITAL_PRINT)
            ->values()
            ->all();

        if (empty($printServices)) {
            $printServices = collect($catalogItems)
                ->filter(fn (array $it): bool => ($it['item_type'] ?? '') === 'jasa')
                ->values()
                ->all();
        }

        $finishingServices = collect($catalogItems)
            ->filter(fn (array $it): bool => ($it['order_behavior'] ?? '') === CatalogBehaviorClassifier::HYBRID_FINISHING)
            ->values()
            ->all();

        return view('features.fotocopy.public.order', [
            'place' => $place,
            'isOpen' => $isOpen,
            'itemsByCategory' => $itemsByCategory,
            'catalogItems' => $catalogItems,
            'printServices' => $printServices,
            'finishingServices' => $finishingServices,
        ]);
    }

    /**
     * Process order submission (supports multi-PDF + per-file note + mixed ATK), save to DB, and initialize payment strategy.
     */
    public function storeOrder(Request $request, $id, PdfMetadataInspectorService $pdfInspector)
    {
        $place = is_numeric($id)
            ? PhotocopyPlace::findOrFail($id)
            : PhotocopyPlace::where('slug', $id)->firstOrFail();

        $store = $this->resolveStore($place);
        $storeId = $store ? $store->id : $place->id;

        if (is_string($request->input('items'))) {
            $decodedItems = json_decode($request->input('items'), true);
            if (is_array($decodedItems)) {
                $request->merge(['items' => $decodedItems]);
            }
        }
        if (is_string($request->input('pdf_configs'))) {
            $decodedConfigs = json_decode($request->input('pdf_configs'), true);
            if (is_array($decodedConfigs)) {
                $request->merge(['pdf_configs' => $decodedConfigs]);
            }
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'customer_whatsapp' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|max:50',
            'payment_channel' => 'nullable|string|max:50',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string',
            'items.*.service_name' => 'nullable|string',
            'items.*.category' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.service_id' => 'nullable|integer',
            'items.*.fulfillment_mode' => 'nullable|string|in:standard,offline_physical',
            'pdf_files' => 'nullable|array|max:' . PdfMetadataInspectorService::MAX_FILES_PER_ORDER,
            'pdf_files.*' => 'file|max:25600',
            'pdf_configs' => 'nullable|array',
            'pdf_configs.*.page_count' => 'nullable|integer|min:1|max:5000',
            'pdf_configs.*.copies' => 'nullable|integer|min:1|max:500',
            'pdf_configs.*.print_service_id' => 'nullable|integer',
            'pdf_configs.*.print_mode' => 'nullable|string|max:100',
            'pdf_configs.*.paper_size' => 'nullable|string|in:A4,F4,A3,Legal',
            'pdf_configs.*.finishing_service_id' => 'nullable|integer',
            'pdf_configs.*.finishing_option' => 'nullable|string|max:120',
            'pdf_configs.*.is_encrypted' => 'nullable|boolean',
            'pdf_configs.*.note' => 'nullable|string|max:500',
            'expected_pdf_count' => 'nullable|integer|min:0|max:' . PdfMetadataInspectorService::MAX_FILES_PER_ORDER,
        ]);

        $uploadedFiles = $request->file('pdf_files', []);
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = $uploadedFiles ? [$uploadedFiles] : [];
        }
        $cartItems = $validated['items'] ?? [];
        $pdfConfigs = $validated['pdf_configs'] ?? [];

        if (isset($validated['expected_pdf_count']) && count($uploadedFiles) !== (int) $validated['expected_pdf_count']) {
            return $this->orderValidationError(
                $request,
                'Jumlah file PDF yang diunggah (' . count($uploadedFiles) . ') tidak sesuai dengan total kuota menu cetak (' . (int) $validated['expected_pdf_count'] . ' file). Lengkapi semua slot PDF sebelum melanjutkan.'
            );
        }

        if (!empty($pdfConfigs) && count($uploadedFiles) !== count($pdfConfigs)) {
            return $this->orderValidationError(
                $request,
                'Jumlah file PDF (' . count($uploadedFiles) . ') harus sama persis dengan jumlah konfigurasi slot cetak (' . count($pdfConfigs) . '). Lengkapi seluruh slot file PDF Anda.'
            );
        }

        if (empty($uploadedFiles) && empty($cartItems)) {
            return $this->orderValidationError(
                $request,
                'Keranjang kosong. Silakan unggah minimal 1 file PDF atau pilih item dari katalog.'
            );
        }

        // Total batch upload guard (<= 80 MB)
        $totalUploadBytes = 0;
        foreach ($uploadedFiles as $file) {
            $totalUploadBytes += (int) $file->getSize();
        }
        if ($totalUploadBytes > PdfMetadataInspectorService::MAX_TOTAL_BYTES) {
            return $this->orderValidationError(
                $request,
                'Total ukuran seluruh file PDF melebihi batas maksimal 80 MB dalam satu transaksi.'
            );
        }

        // Strict pre-validation & server-side price/page calculation for every uploaded PDF
        $pdfConfigs = $validated['pdf_configs'] ?? [];
        $preparedPdfs = [];
        $totalPagesToPrint = 0;

        try {
            foreach ($uploadedFiles as $idx => $uploadedFile) {
                $cfg = is_array($pdfConfigs[$idx] ?? null) ? $pdfConfigs[$idx] : [];
                $clientPages = isset($cfg['page_count']) ? (int) $cfg['page_count'] : null;
                $clientEncrypted = !empty($cfg['is_encrypted']);

                $meta = $pdfInspector->inspectPdf($uploadedFile, $clientPages, $clientEncrypted);

                $copies = max(1, min(500, (int) ($cfg['copies'] ?? 1)));
                $paperSize = (string) ($cfg['paper_size'] ?? 'A4');

                // Authoritative lookup for print service price
                $printService = $this->resolveCatalogItem(
                    $place->id,
                    $storeId,
                    $cfg['print_service_id'] ?? null,
                    $cfg['print_mode'] ?? null
                );
                $printUnitPrice = $printService['price'] ?? 300;
                $printModeLabel = $printService['name'] ?? ($cfg['print_mode'] ?? 'Print Hitam Putih A4');
                $matchedPrintServiceId = $printService['fotocopy_service_id'] ?? null;

                // Authoritative lookup for optional finishing/jilid service
                $finishService = null;
                if (!empty($cfg['finishing_service_id']) || !empty($cfg['finishing_option'])) {
                    $finishService = $this->resolveCatalogItem(
                        $place->id,
                        $storeId,
                        $cfg['finishing_service_id'] ?? null,
                        $cfg['finishing_option'] ?? null
                    );
                }
                $finishPrice = $finishService['price'] ?? 0;
                $finishUnit = $finishService['unit'] ?? null;
                $finishLabel = $finishService['name'] ?? (!empty($cfg['finishing_option']) ? (string) $cfg['finishing_option'] : null);

                $breakdown = $pdfInspector->calculatePdfBreakdown(
                    $meta['page_count'],
                    $copies,
                    $printUnitPrice,
                    $finishPrice,
                    $finishUnit,
                    $finishLabel
                );

                $totalPagesToPrint += ($meta['page_count'] * $copies);

                $preparedPdfs[] = [
                    'uploaded_file' => $uploadedFile,
                    'meta' => $meta,
                    'copies' => $copies,
                    'paper_size' => $paperSize,
                    'print_mode' => $printModeLabel,
                    'matched_service_id' => $matchedPrintServiceId,
                    'finishing_option' => $finishLabel,
                    'breakdown' => $breakdown,
                    'note' => !empty($cfg['note']) ? trim((string) $cfg['note']) : null,
                    'subtotal' => $breakdown['total_subtotal'],
                    'sort_order' => $idx + 1,
                ];
            }
        } catch (InvalidArgumentException $e) {
            return $this->orderValidationError($request, $e->getMessage());
        }

        // Authoritative lookup for standard / offline physical cart items
        $preparedItems = [];
        foreach ($cartItems as $item) {
            $itemName = $item['name'] ?? $item['service_name'] ?? 'Item Cetak';
            $qty = max(1, (int) $item['quantity']);

            $resolved = $this->resolveCatalogItem($place->id, $storeId, $item['service_id'] ?? null, $itemName);
            $unitPrice = $resolved ? $resolved['price'] : (int) round((float) $item['price']);
            $baseCategory = $resolved['category'] ?? ($item['category'] ?? 'Fotocopy');
            $behavior = $resolved['order_behavior']
                ?? CatalogBehaviorClassifier::classify(null, $baseCategory, null, $itemName);
            $fulfillmentMode = $item['fulfillment_mode'] ?? 'standard';

            $isOfflinePhysical = ($behavior === CatalogBehaviorClassifier::PHYSICAL_ONLY)
                || ($behavior === CatalogBehaviorClassifier::HYBRID_FINISHING && $fulfillmentMode === 'offline_physical');

            $storedCategory = $isOfflinePhysical && !str_contains((string) $baseCategory, 'Offline Fisik')
                ? "{$baseCategory} (Offline Fisik)"
                : $baseCategory;

            $preparedItems[] = [
                'service_id' => $resolved['fotocopy_service_id'] ?? FotocopyService::where('store_id', $storeId)->where('name', $itemName)->value('id'),
                'service_name' => $itemName,
                'category' => $storedCategory,
                'price' => $unitPrice,
                'quantity' => $qty,
                'subtotal' => $unitPrice * $qty,
            ];
        }

        $totalPrice = array_sum(array_column($preparedPdfs, 'subtotal'))
            + array_sum(array_column($preparedItems, 'subtotal'));

        // Normalize payment method to strategy keys: 'qris', 'bank_transfer', 'cash'
        $rawMethod = strtolower(trim((string) ($validated['payment_method'] ?? 'qris')));
        $paymentMethod = match ($rawMethod) {
            'cash', 'tunai', 'cod' => 'cash',
            'bank_transfer', 'bank', 'transfer', 'va' => 'bank_transfer',
            default => 'qris',
        };

        $rawWaInput = $validated['customer_whatsapp'] ?? $validated['customer_phone'] ?? null;
        $normalizedCustomerWa = WhatsAppPhoneNormalizer::normalize($rawWaInput);
        $rawPhoneStored = $validated['customer_phone'] ?? $rawWaInput;

        $orderCode = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        $pickupCode = $pdfInspector->generatePickupCode();
        $activeQueue = FotocopyOrder::where('store_id', $storeId)->where('order_status', 'Diproses')->count();
        $pickupEstimate = $pdfInspector->estimatePickupTime(max(1, $totalPagesToPrint), $activeQueue);

        $order = DB::transaction(function () use (
            $orderCode,
            $pickupCode,
            $pickupEstimate,
            $storeId,
            $validated,
            $rawPhoneStored,
            $normalizedCustomerWa,
            $paymentMethod,
            $totalPrice,
            $preparedPdfs,
            $preparedItems,
            $pdfInspector
        ): FotocopyOrder {
            $order = FotocopyOrder::create([
                'order_code' => $orderCode,
                'pickup_code' => $pickupCode,
                'store_id' => $storeId,
                'user_id' => auth()->id(),
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $rawPhoneStored,
                'customer_whatsapp' => $normalizedCustomerWa,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $paymentMethod,
                'payment_channel' => $validated['payment_channel'] ?? null,
                'payment_status' => 'pending',
                'order_status' => 'Menunggu',
                'pickup_estimate' => $pickupEstimate,
                'wa_dispatch_status' => 'not_ready',
                'total_price' => $totalPrice,
            ]);

            $firstPdfRecord = null;
            foreach ($preparedPdfs as $pdfEntry) {
                $meta = $pdfEntry['meta'];
                $storedPath = $pdfInspector->storePrivateFile(
                    $pdfEntry['uploaded_file'],
                    $order->order_code,
                    $pdfEntry['sort_order'],
                    'pdf'
                );

                $bd = $pdfEntry['breakdown'];
                if (!empty($pdfEntry['finishing_option']) && ($bd['finishing_qty'] ?? 0) > 0) {
                    $itemDisplayName = sprintf(
                        'Bundel Cetak PDF + %s: %s (%dx Print Rp %s + %dx %s Rp %s)',
                        $pdfEntry['finishing_option'],
                        $meta['original_name'],
                        $bd['print_qty'],
                        number_format($bd['print_subtotal'], 0, ',', '.'),
                        $bd['finishing_qty'],
                        $pdfEntry['finishing_option'],
                        number_format($bd['finishing_subtotal'], 0, ',', '.')
                    );
                } else {
                    $itemDisplayName = "Cetak PDF: {$meta['original_name']} ({$pdfEntry['print_mode']}, {$meta['page_count']} hal)";
                }

                $orderItem = FotocopyOrderItem::create([
                    'order_id' => $order->id,
                    'service_id' => $pdfEntry['matched_service_id'],
                    'service_name' => $itemDisplayName,
                    'category' => !empty($pdfEntry['finishing_option']) ? 'Bundel Print PDF + Finishing' : 'Print PDF',
                    'price' => (int) round($pdfEntry['subtotal'] / max(1, $pdfEntry['copies'])),
                    'quantity' => $pdfEntry['copies'],
                    'subtotal' => $pdfEntry['subtotal'],
                ]);

                $fileRecord = FotocopyOrderFile::create([
                    'fotocopy_order_id' => $order->id,
                    'fotocopy_order_item_id' => $orderItem->id,
                    'original_name' => $meta['original_name'],
                    'stored_path' => $storedPath,
                    'storage_disk' => 'local',
                    'mime_type' => $meta['mime_type'],
                    'file_size' => $meta['file_size'],
                    'page_count' => $meta['page_count'],
                    'copies' => $pdfEntry['copies'],
                    'print_mode' => $pdfEntry['print_mode'],
                    'paper_size' => $pdfEntry['paper_size'],
                    'finishing_option' => $pdfEntry['finishing_option'],
                    'note' => $pdfEntry['note'],
                    'subtotal_estimated' => $pdfEntry['subtotal'],
                    'sort_order' => $pdfEntry['sort_order'],
                    'is_encrypted' => $meta['is_encrypted'],
                ]);

                if ($firstPdfRecord === null) {
                    $firstPdfRecord = $fileRecord;
                }
            }

            if ($firstPdfRecord) {
                $order->update([
                    'file_path' => $firstPdfRecord->stored_path,
                    'file_name' => $firstPdfRecord->original_name,
                    'file_size' => $firstPdfRecord->formatted_size,
                    'file_type' => 'pdf',
                ]);
            }

            foreach ($preparedItems as $itemData) {
                FotocopyOrderItem::create(array_merge(['order_id' => $order->id], $itemData));
            }

            return $order;
        });

        // Execute payment strategy (Order stays 'pending' -> DO NOT dispatch WhatsApp yet)
        $paymentManager = app(PaymentManager::class);
        $paymentResult = $paymentManager->process($order, $paymentMethod, [
            'payment_channel' => $validated['payment_channel'] ?? null,
        ]);

        // Build manual fallback wa.me URL (rich summary + PDF links + notes)
        $order->load(['items', 'files', 'store']);
        $whatsappUrl = app(WhatsAppPayloadFormatter::class)->buildRichCustomerWaMeUrl($order, 1440);
        $paymentUrl = route('fotocopy.orders.payment', $order->order_code);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat!',
                'order_code' => $order->order_code,
                'order_id' => $order->id,
                'pickup_code' => $order->pickup_code,
                'pickup_estimate' => $order->pickup_estimate,
                'payment_url' => $paymentUrl,
                'whatsapp_url' => $whatsappUrl,
                'payment' => $paymentResult->toArray(),
            ]);
        }

        return redirect()->route('fotocopy.orders.payment', $order->order_code)
            ->with('success', "Pesanan #{$order->order_code} berhasil dibuat! Silakan selesaikan pembayaran.")
            ->with('whatsapp_url', $whatsappUrl);
    }

    /**
     * Resolve authoritative catalog pricing, unit & behavior from PhotocopyItem and FotocopyService.
     *
     * @return array{price: int, name: string, category: string, unit: ?string, order_behavior: string, fotocopy_service_id: ?int}|null
     */
    protected function resolveCatalogItem(int $placeId, int $storeId, mixed $candidateId = null, ?string $candidateName = null): ?array
    {
        $placeItem = null;
        if (!empty($candidateId)) {
            $placeItem = PhotocopyItem::where('photocopy_place_id', $placeId)->find((int) $candidateId);
        }
        if (!$placeItem && !empty($candidateName)) {
            $placeItem = PhotocopyItem::where('photocopy_place_id', $placeId)->where('name', $candidateName)->first();
        }

        $storeService = null;
        if ($placeItem) {
            $storeService = FotocopyService::where('store_id', $storeId)->where('name', $placeItem->name)->first();
            return [
                'price' => (int) $placeItem->price,
                'name' => (string) $placeItem->name,
                'category' => (string) $placeItem->category,
                'unit' => $placeItem->unit,
                'order_behavior' => CatalogBehaviorClassifier::classify(
                    $placeItem->item_type,
                    $placeItem->category,
                    $placeItem->sub_category,
                    $placeItem->name
                ),
                'fotocopy_service_id' => $storeService?->id,
            ];
        }

        if (!empty($candidateId)) {
            $storeService = FotocopyService::where('store_id', $storeId)->find((int) $candidateId);
        }
        if (!$storeService && !empty($candidateName)) {
            $storeService = FotocopyService::where('store_id', $storeId)->where('name', $candidateName)->first();
        }

        if ($storeService) {
            return [
                'price' => (int) $storeService->price,
                'name' => (string) $storeService->name,
                'category' => (string) $storeService->category,
                'unit' => $storeService->unit,
                'order_behavior' => CatalogBehaviorClassifier::classify(
                    $storeService->item_type,
                    $storeService->category,
                    $storeService->sub_category,
                    $storeService->name
                ),
                'fotocopy_service_id' => $storeService->id,
            ];
        }

        return null;
    }

    protected function orderValidationError(Request $request, string $message)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    /**
     * Tampilkan halaman instruksi pembayaran dan rincian pesanan.
     */
    public function paymentInstructions($orderCode)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)
            ->with(['store', 'photocopyPlace', 'items', 'files'])
            ->firstOrFail();

        if ($order->isPaid() && (empty($order->invoice_pdf_path) || !Storage::disk('local')->exists($order->invoice_pdf_path))) {
            app(OrderInvoiceService::class)->generateForOrder($order);
            $order->refresh();
            $order->load(['store', 'photocopyPlace', 'items', 'files']);
        }

        $place = $order->photocopy_place_resolved ?? new PhotocopyPlace([
            'name' => $order->store?->name ?? 'Mitra Fotokopi USU',
            'location_name' => $order->store?->location_name ?? 'Area Kampus USU Medan',
            'whatsapp' => $order->store?->whatsapp ?? '6285769737410',
        ]);

        $invoiceDownloadUrl = $order->isPaid() ? $order->invoiceUrl(1440) : null;
        $invoicePreviewUrl = $order->isPaid() ? $order->invoicePreviewUrl(1440) : null;
        $zipDownloadUrl = $order->signedZipUrl(1440);
        $waStatus = app(WhatsAppManager::class)->statusSummary();
        $waManualUrl = app(WhatsAppPayloadFormatter::class)->buildRichCustomerWaMeUrl($order, 1440);

        $orderFilesPayload = $order->files->map(function (FotocopyOrderFile $f): array {
            return [
                'id' => $f->id,
                'original_name' => $f->original_name,
                'formatted_size' => $f->formatted_size,
                'page_count' => $f->page_count,
                'copies' => $f->copies,
                'spec' => $f->getDisplaySpecSummary(),
                'note' => $f->note,
                'finishing_option' => $f->finishing_option,
                'subtotal_estimated' => $f->subtotal_estimated,
                'available' => $f->isPhysicallyAvailable(),
                'preview_url' => $f->signedPreviewUrl(1440),
                'download_url' => $f->signedDownloadUrl(1440),
            ];
        })->values()->all();

        return view('features.fotocopy.public.payment', compact(
            'order',
            'place',
            'invoiceDownloadUrl',
            'invoicePreviewUrl',
            'zipDownloadUrl',
            'waStatus',
            'waManualUrl',
            'orderFilesPayload'
        ));
    }

    /**
     * Endpoint simulasi pembayaran (PoC) untuk menandai pesanan menjadi Lunas + generate Invoice PDF & WA dispatch.
     */
    public function simulatePayment(Request $request, $orderCode)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        $paymentManager = app(PaymentManager::class);
        $success = $paymentManager->simulateSuccess($order);
        $order->refresh();

        if ($success && $order->isPaid()) {
            app(OrderPaymentFulfillmentService::class)->handlePaidOrder($order, true);
            $order->refresh();
        }

        $order->load(['store', 'items', 'files']);
        $waStatus = app(WhatsAppManager::class)->statusSummary();
        $waManualUrl = app(WhatsAppPayloadFormatter::class)->buildRichCustomerWaMeUrl($order, 1440);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'message' => 'Status pembayaran berhasil diperbarui menjadi Lunas (Simulasi PoC)!',
                'order_code' => $order->order_code,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'invoice_number' => $order->invoice_number,
                'pickup_code' => $order->pickup_code,
                'invoice_url' => $order->invoiceUrl(1440),
                'invoice_preview_url' => $order->invoicePreviewUrl(1440),
                'zip_download_url' => $order->signedZipUrl(1440),
                'wa_dispatch_status' => $order->wa_dispatch_status,
                'wa_driver' => $waStatus['driver'],
                'wa_driver_label' => $waStatus['label'],
                'wa_healthy' => $waStatus['healthy'],
                'wa_manual_url' => $waManualUrl,
                'paid_at' => $order->paid_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i'),
            ]);
        }

        return redirect()->route('fotocopy.orders.payment', $order->order_code)
            ->with('success', 'Status pembayaran berhasil diperbarui menjadi Lunas!');
    }

    /**
     * Dispatch (or resend) order summary + all PDF files + notes + Invoice PDF to Store WhatsApp.
     */
    public function dispatchWhatsapp(
        Request $request,
        $orderCode,
        WhatsAppManager $waManager,
        OrderInvoiceService $invoiceService,
        WhatsAppPayloadFormatter $formatter
    ) {
        $order = FotocopyOrder::where('order_code', $orderCode)
            ->with(['store', 'items', 'files'])
            ->firstOrFail();

        if (!$order->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan belum lunas. Selesaikan pembayaran terlebih dahulu sebelum mengirim bukti & file PDF ke WhatsApp toko.',
            ], 422);
        }

        if (empty($order->invoice_pdf_path) || !Storage::disk('local')->exists($order->invoice_pdf_path)) {
            $invoiceService->generateForOrder($order);
            $order->refresh();
        }

        DispatchOrderToWhatsAppJob::dispatchSync($order->id, true);
        $order->refresh();
        $order->load(['store', 'items', 'files']);

        $waStatus = $waManager->statusSummary();
        $storeLogs = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->where('recipient_role', 'store')
            ->get();

        $sentSummary = $storeLogs->where('message_type', 'summary')->where('status', 'sent')->count() > 0;
        $sentPdfs = $storeLogs->where('message_type', 'pdf_attachment')->where('status', 'sent')->count();
        $totalPdfs = $order->files->count();
        $sentInvoice = $storeLogs->where('message_type', 'invoice')->where('status', 'sent')->count() > 0;

        $isLiveGateway = in_array($waStatus['driver'], ['waha', 'fonnte'], true);
        $deliveryOk = in_array((string) $order->wa_dispatch_status, ['sent', 'sandbox'], true);

        $msg = match (true) {
            $waStatus['driver'] === 'sandbox' => "Mode Sandbox Simulator aktif: Ringkasan, {$totalPdfs} file PDF + Note, dan Invoice tercatat di log simulator lokal. Aktifkan WHATSAPP_DRIVER=waha di .env untuk mengirim lampiran PDF langsung ke chat WhatsApp toko.",
            $deliveryOk => "Berhasil! Ringkasan pesanan, {$sentPdfs}/{$totalPdfs} file PDF beserta note, dan Bukti Invoice PDF resmi telah dikirim otomatis ke WhatsApp toko.",
            $order->wa_dispatch_status === 'partial' => "Sebagian pesan terkirim ({$sentPdfs}/{$totalPdfs} PDF). Anda tetap dapat mengirim ulang atau membuka Chat WA Manual.",
            default => 'Gateway WhatsApp (' . strtoupper($waStatus['driver']) . ') belum merespons. Gunakan tombol Buka Chat WA Manual di bawah atau unduh paket ZIP.',
        };

        return response()->json([
            'success' => $deliveryOk || !$isLiveGateway,
            'wa_dispatch_status' => $order->wa_dispatch_status,
            'driver' => $waStatus['driver'],
            'driver_label' => $waStatus['label'],
            'healthy' => $waStatus['healthy'],
            'sent_summary' => $sentSummary,
            'sent_pdfs' => $sentPdfs,
            'total_pdfs' => $totalPdfs,
            'sent_invoice' => $sentInvoice,
            'wa_manual_url' => $formatter->buildRichCustomerWaMeUrl($order, 1440),
            'message' => $msg,
        ]);
    }

    /**
     * Unduh Bukti Invoice PDF Resmi (terproteksi Signed URL atau pemilik/staf terautentikasi).
     */
    public function downloadInvoice(Request $request, $orderCode)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        $this->authorizePrivateOrderAccess($request, $order);

        if (!$order->isPaid()) {
            abort(403, 'Bukti Invoice PDF resmi hanya tersedia setelah pembayaran Lunas.');
        }

        if (empty($order->invoice_pdf_path) || !Storage::disk('local')->exists($order->invoice_pdf_path)) {
            app(OrderInvoiceService::class)->generateForOrder($order);
            $order->refresh();
        }

        $safeName = str_replace(['"', "\r", "\n", '\\'], '', ($order->invoice_number ?: $order->order_code) . '.pdf');

        return Storage::disk('local')->download(
            $order->invoice_pdf_path,
            $safeName,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Pratinjau inline Bukti Invoice PDF Resmi (Content-Disposition: inline).
     */
    public function previewInvoice(Request $request, $orderCode)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        $this->authorizePrivateOrderAccess($request, $order);

        if (!$order->isPaid()) {
            abort(403, 'Bukti Invoice PDF resmi hanya tersedia setelah pembayaran Lunas.');
        }

        if (empty($order->invoice_pdf_path) || !Storage::disk('local')->exists($order->invoice_pdf_path)) {
            app(OrderInvoiceService::class)->generateForOrder($order);
            $order->refresh();
        }

        $safeName = str_replace(['"', "\r", "\n", '\\'], '', ($order->invoice_number ?: $order->order_code) . '.pdf');

        return response()->file(Storage::disk('local')->path($order->invoice_pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safeName . '"',
        ]);
    }

    /**
     * Unduh arsip .zip berisi seluruh PDF, Note SPK, dan Invoice via Signed URL.
     */
    public function downloadOrderZip(Request $request, $orderCode, OrderArchiveService $archiveService)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        $this->authorizePrivateOrderAccess($request, $order);

        $zipPath = $archiveService->buildOrderZip($order);

        return response()
            ->download($zipPath, "Order_{$order->order_code}_Berkas.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Pratinjau inline file dokumen privat pesanan via Signed URL (Content-Disposition: inline).
     */
    public function previewOrderFile(Request $request, $orderCode, FotocopyOrderFile $file)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        if ((int) $file->fotocopy_order_id !== (int) $order->id) {
            abort(404);
        }

        $this->authorizePrivateOrderAccess($request, $order);

        if (!$file->isPhysicallyAvailable()) {
            abort(404, 'File dokumen sudah tidak tersedia atau telah dibersihkan oleh kebijakan retensi.');
        }

        $disk = $file->storage_disk ?: 'local';
        $safeName = str_replace(['"', "\r", "\n", '\\'], '', $file->original_name ?: 'dokumen.pdf');

        return response()->file(Storage::disk($disk)->path($file->stored_path), [
            'Content-Type' => $file->mime_type ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safeName . '"',
        ]);
    }

    /**
     * Unduh file dokumen privat pesanan via Signed URL atau pemilik/staf terautentikasi.
     */
    public function downloadOrderFile(Request $request, $orderCode, FotocopyOrderFile $file)
    {
        $order = FotocopyOrder::where('order_code', $orderCode)->firstOrFail();
        if ((int) $file->fotocopy_order_id !== (int) $order->id) {
            abort(404);
        }

        $this->authorizePrivateOrderAccess($request, $order);

        if (!$file->isPhysicallyAvailable()) {
            abort(404, 'File dokumen sudah tidak tersedia atau telah dibersihkan oleh kebijakan retensi.');
        }

        $disk = $file->storage_disk ?: 'local';
        $safeName = str_replace(['"', "\r", "\n", '\\'], '', $file->original_name ?: 'dokumen.pdf');

        return Storage::disk($disk)->download(
            $file->stored_path,
            $safeName,
            ['Content-Type' => $file->mime_type ?: 'application/pdf']
        );
    }

    protected function authorizePrivateOrderAccess(Request $request, FotocopyOrder $order): void
    {
        if ($request->hasValidSignature()) {
            return;
        }

        $user = auth()->user();
        if (
            $user
            && (
                $user->isCashierFotokopi()
                || $user->isAdminFotokopi()
                || $user->isAdmin()
                || ($order->user_id && (int) $order->user_id === (int) $user->id)
            )
        ) {
            return;
        }

        abort(403, 'Akses dokumen privat ditolak.');
    }
}
