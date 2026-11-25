<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\User;
use App\Models\WhatsappDispatchLog;
use App\Services\Fotocopy\StoreMapSyncService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function __construct(
        protected ?StoreMapSyncService $syncService = null
    ) {
        $this->syncService = $syncService ?? app(StoreMapSyncService::class);
    }

    /**
     * Sinkronkan data FotocopyStore ke PhotocopyPlace agar Peta User (/map) & Direktori Publik (/fotocopy) selalu akurat.
     */
    protected function syncToPhotocopyPlace(FotocopyStore $store, ?string $oldName = null, ?string $oldSlug = null): void
    {
        try {
            $this->syncService->syncStoreToPlace($store, $oldName, $oldSlug);
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menyinkronkan FotocopyStore ke PhotocopyPlace: ' . $e->getMessage());
        }
    }
    public function index(Request $request, WhatsAppManager $waManager)
    {
        $scopedStoreId = auth()->user()?->scopedStoreId();
        $query = FotocopyStore::withCount(['services', 'orders']);

        if ($scopedStoreId !== null) {
            $query->where('id', $scopedStoreId);
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('location_name', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%");
            });
        }

        $stores = $query->latest()->paginate(10)->withQueryString();
        $waStatus = $waManager->statusSummary();

        return view('features.fotocopy.admin.stores.index', compact('stores', 'waStatus'));
    }

    /**
     * Kirim pesan uji coba (Tes Ping) ke WhatsApp aktif toko mitra via driver Gateway terpilih.
     */
    public function testWhatsApp(FotocopyStore $store, WhatsAppManager $waManager)
    {
        $this->authorizeScopedStore($store);
        $targetDigits = WhatsAppPhoneNormalizer::normalize($store->whatsapp ?: $store->phone);
        if (!$targetDigits) {
            return redirect()->back()->with('error', "Toko '{$store->name}' belum memiliki nomor WhatsApp valid.");
        }

        $driver = $waManager->driver();
        $senderDisplay = WhatsAppPhoneNormalizer::formatDisplay(
            (string) config('whatsapp.sender_phone', '6281283575656')
        );
        $message = implode("\n", [
            '*TES KONEKSI WHATSAPP - USUCLASSMAP*',
            "Mitra Toko : {$store->name}",
            "No. Pengirim : {$senderDisplay}",
            'No. Tujuan : ' . WhatsAppPhoneNormalizer::formatDisplay($targetDigits),
            'Driver     : ' . strtoupper($driver->driverName()),
            'Waktu      : ' . now('Asia/Jakarta')->format('d M Y, H:i:s') . ' WIB',
            'Status     : Koneksi gateway pengiriman otomatis siap menerima order Multi-PDF & Invoice.',
        ]);

        $res = $driver->sendText($targetDigits, $message);

        if ($res['success']) {
            $modeNote = ($res['simulated'] ?? false)
                ? ' (dicatat via Sandbox Simulator — aktifkan WHATSAPP_DRIVER=waha untuk kirim ke HP nyata)'
                : '';

            return redirect()->back()->with(
                'success',
                "Tes pesan WhatsApp ke {$store->name} (" . WhatsAppPhoneNormalizer::formatDisplay($targetDigits) . ") berhasil{$modeNote}!"
            );
        }

        return redirect()->back()->with('error', 'Tes kirim WhatsApp gagal: ' . ($res['error'] ?? 'Unknown gateway error'));
    }

    /**
     * Ambil daftar log pesan WhatsApp terbaru untuk modal "Simulasi Chat WA (Sandbox Viewer)".
     */
    public function whatsappSandboxLogs()
    {
        $logs = WhatsappDispatchLog::with(['order.store'])
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(function (WhatsappDispatchLog $log): array {
                return [
                    'id' => $log->id,
                    'order_code' => $log->order?->order_code ?? '-',
                    'store_name' => $log->order?->store?->name ?? 'Mitra Fotokopi USU',
                    'customer_name' => $log->order?->customer_name ?? '-',
                    'recipient_phone' => WhatsAppPhoneNormalizer::formatDisplay($log->recipient_phone),
                    'recipient_role' => $log->recipient_role,
                    'message_type' => $log->message_type,
                    'driver' => $log->driver,
                    'status' => $log->status,
                    'text' => $log->payload_json['text'] ?? $log->payload_json['caption'] ?? '',
                    'file_name' => $log->payload_json['file_name'] ?? ($log->attachment_path ? basename($log->attachment_path) : null),
                    'error_message' => $log->error_message,
                    'sent_at' => ($log->sent_at ?? $log->created_at)?->timezone('Asia/Jakarta')->format('d M Y, H:i:s'),
                ];
            });

        return response()->json(['logs' => $logs]);
    }

    protected function authorizeScopedStore(?FotocopyStore $store = null, bool $allowCreate = true): void
    {
        $scopedStoreId = auth()->user()?->scopedStoreId();
        if ($scopedStoreId === null) {
            return;
        }

        if (!$allowCreate && $store === null) {
            abort(403, 'Akun admin toko hanya dapat mengelola toko miliknya sendiri.');
        }

        if ($store !== null && (int) $store->id !== $scopedStoreId) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengelola toko Anda sendiri.');
        }
    }

    /**
     * Daftar preset banner lokal untuk foto gerai mitra fotokopi.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function storePresetOptions(): array
    {
        return [
            ['value' => '/images/stores/preset-modern-copy.webp', 'label' => 'Preset 1 — Gerai Fotokopi & Print Modern'],
            ['value' => '/images/stores/preset-campus-press.webp', 'label' => 'Preset 2 — Penerbitan, Buku & Skripsi'],
            ['value' => '/images/stores/preset-atk-stationery.webp', 'label' => 'Preset 3 — Pusat ATK & Perlengkapan Kuliah'],
        ];
    }

    /**
     * Resolusi URL gambar toko dari file upload, checkbox hapus, atau preset lokal.
     */
    protected function resolveStoreImageUpdate(Request $request, ?string $currentImageUrl = null): ?string
    {
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $storedPath = $request->file('image')->store('stores', 'public');

            return '/storage/' . ltrim((string) $storedPath, '/');
        }

        if ($request->boolean('remove_image')) {
            return null;
        }

        if ($request->filled('image_preset')) {
            $preset = trim((string) $request->input('image_preset'));
            if (str_starts_with($preset, '/images/stores/') && is_file(public_path(ltrim($preset, '/')))) {
                return $preset;
            }
            if (str_starts_with($preset, 'http://') || str_starts_with($preset, 'https://')) {
                return $preset;
            }
        }

        return $currentImageUrl;
    }

    public function create()
    {
        $this->authorizeScopedStore(null, false);
        $cashiers = User::where('role', 'cashier_fotokopi')->get();
        $storePresets = self::storePresetOptions();

        return view('features.fotocopy.admin.stores.create', compact('cashiers', 'storePresets'));
    }

    public function store(Request $request)
    {
        $this->authorizeScopedStore(null, false);
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'location_name' => 'required|string|max:200',
            'user_id' => 'nullable|exists:users,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'opening_hours' => 'required|string|max:50',
            'operating_days' => 'required|string|max:100',
            'whatsapp' => 'nullable|string|max:30',
            'instagram' => 'nullable|string|max:50',
            'facebook' => 'nullable|string|max:50',
            'starting_price' => 'required|integer|min:0',
            'rating' => 'required|numeric|between:1,5',
            'is_open' => 'required|boolean',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'image_preset' => 'nullable|string|max:255',
            'remove_image' => 'nullable|boolean',
        ]);

        unset($validated['image'], $validated['image_preset'], $validated['remove_image']);
        $validated['image_url'] = $this->resolveStoreImageUpdate($request, null);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        if (!empty($validated['whatsapp'])) {
            $normalizedWa = WhatsAppPhoneNormalizer::normalize($validated['whatsapp']);
            if ($normalizedWa) {
                $validated['whatsapp'] = $normalizedWa;
                $validated['phone'] = WhatsAppPhoneNormalizer::formatDisplay($normalizedWa);
            }
        }

        $store = FotocopyStore::create($validated);
        $this->syncToPhotocopyPlace($store);

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Mitra Toko '{$validated['name']}' berhasil ditambahkan!");
    }

    public function edit(FotocopyStore $store)
    {
        $this->authorizeScopedStore($store);
        $scopedStoreId = auth()->user()?->scopedStoreId();
        $cashiers = User::query()
            ->when($scopedStoreId !== null, function ($q) use ($scopedStoreId, $store) {
                $q->where(function ($sub) use ($scopedStoreId, $store) {
                    $sub->where('role', 'cashier_fotokopi')
                        ->where('store_id', $scopedStoreId);
                    if ($store->user_id) {
                        $sub->orWhere('id', $store->user_id);
                    }
                });
            }, function ($q) use ($store) {
                $q->where('role', 'cashier_fotokopi');
                if ($store->user_id) {
                    $q->orWhere('id', $store->user_id);
                }
            })
            ->get();

        $storePresets = self::storePresetOptions();

        return view('features.fotocopy.admin.stores.edit', compact('store', 'cashiers', 'storePresets'));
    }

    public function update(Request $request, FotocopyStore $store)
    {
        $this->authorizeScopedStore($store);
        $oldName = $store->name;
        $oldSlug = $store->slug;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'location_name' => 'required|string|max:200',
            'user_id' => 'nullable|exists:users,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'opening_hours' => 'required|string|max:50',
            'operating_days' => 'required|string|max:100',
            'whatsapp' => 'nullable|string|max:30',
            'instagram' => 'nullable|string|max:50',
            'facebook' => 'nullable|string|max:50',
            'starting_price' => 'required|integer|min:0',
            'rating' => 'required|numeric|between:1,5',
            'is_open' => 'required|boolean',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'image_preset' => 'nullable|string|max:255',
            'remove_image' => 'nullable|boolean',
        ]);

        unset($validated['image'], $validated['image_preset'], $validated['remove_image']);
        $validated['image_url'] = $this->resolveStoreImageUpdate($request, $store->image_url);

        if (auth()->user()?->scopedStoreId() !== null && empty($validated['user_id'])) {
            $validated['user_id'] = $store->user_id;
        }

        if (!empty($validated['whatsapp'])) {
            $normalizedWa = WhatsAppPhoneNormalizer::normalize($validated['whatsapp']);
            if ($normalizedWa) {
                $validated['whatsapp'] = $normalizedWa;
                $validated['phone'] = WhatsAppPhoneNormalizer::formatDisplay($normalizedWa);
            }
        }

        $store->update($validated);
        $this->syncToPhotocopyPlace($store, $oldName, $oldSlug);

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Data mitra toko '{$store->name}' berhasil diperbarui!");
    }

    public function destroy(FotocopyStore $store)
    {
        if (auth()->user()?->scopedStoreId() !== null) {
            abort(403, 'Akun admin toko tidak diizinkan menghapus entitas toko.');
        }

        $name = $store->name;

        try {
            $this->syncService->deletePlaceForStore($store);
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menghapus PhotocopyPlace: ' . $e->getMessage());
        }

        $store->delete();

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Mitra toko '{$name}' berhasil dihapus.");
    }
}
