<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use App\Services\Fotocopy\StoreMapSyncService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public const ALLOWED_CATEGORIES = ['Print', 'Fotocopy', 'Jilid', 'Finishing', 'Digital', 'ATK'];

    public function __construct(
        protected ?StoreMapSyncService $syncService = null
    ) {
        $this->syncService = $syncService ?? app(StoreMapSyncService::class);
    }

    /**
     * Sinkronkan FotocopyService ke PhotocopyItem agar halaman publik (/fotocopy/{id}) selalu real-time.
     */
    protected function syncToPhotocopyItem(FotocopyService $service, ?int $oldStoreId = null, ?string $oldName = null): void
    {
        try {
            $this->syncService->syncServiceToItem($service, $oldStoreId, $oldName);
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menyinkronkan FotocopyService ke PhotocopyItem: ' . $e->getMessage());
        }
    }

    protected function authorizeScopedService(?FotocopyService $service = null): ?int
    {
        $scopedStoreId = auth()->user()?->scopedStoreId();
        if ($scopedStoreId !== null && $service !== null && (int) $service->store_id !== $scopedStoreId) {
            abort(403, 'Akses ditolak: layanan ini milik mitra toko lain.');
        }

        return $scopedStoreId;
    }

    public function index(Request $request)
    {
        $scopedStoreId = $this->authorizeScopedService();
        $query = FotocopyService::with('store');

        if ($scopedStoreId !== null) {
            $query->where('store_id', $scopedStoreId);
        } elseif ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->input('item_type'));
        }

        if ($request->filled('sub_category')) {
            $query->where('sub_category', $request->input('sub_category'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('sub_category', 'like', "%{$q}%");
            });
        }

        $services = $query->latest()->paginate(20)->withQueryString();
        $storesQuery = FotocopyStore::orderBy('name');
        if ($scopedStoreId !== null) {
            $storesQuery->where('id', $scopedStoreId);
        }
        $stores = $storesQuery->get(['id', 'name']);

        $subCatQuery = FotocopyService::whereNotNull('sub_category')
            ->where('sub_category', '!=', '');
        if ($scopedStoreId !== null) {
            $subCatQuery->where('store_id', $scopedStoreId);
        }
        $subCategories = $subCatQuery
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category');

        return view('features.fotocopy.admin.services.index', compact('services', 'stores', 'subCategories'));
    }

    public static function defaultCategoryImagePath(string $category): string
    {
        return match ($category) {
            'Print' => 'fotocopy_catalog/print-hitam-putih-a4-70gsm.webp',
            'Fotocopy' => 'fotocopy_catalog/fotokopi-hitam-putih-a4-1-sisi.webp',
            'Jilid' => 'fotocopy_catalog/jilid-softcover-direct-binding.webp',
            'Finishing' => 'fotocopy_catalog/laminating-a4-ijazah-sertifikat.webp',
            'Digital' => 'fotocopy_catalog/scan-dokumen-pdf-jpg-per-lembar.webp',
            'ATK' => 'fotocopy_catalog/pulpen-joyko-gp-265-q-gel-1pcs.webp',
            default => 'fotocopy_catalog/print-hitam-putih-a4-70gsm.webp',
        };
    }

    /**
     * Peta URL publik default per kategori untuk pratinjau formulir.
     *
     * @return array<string, string>
     */
    public static function categoryDefaultUrls(): array
    {
        $map = [];
        foreach (self::ALLOWED_CATEGORIES as $cat) {
            $map[$cat] = asset('storage/' . self::defaultCategoryImagePath($cat));
        }

        return $map;
    }

    /**
     * Preset gambar katalog lokal yang terverifikasi eksis di disk.
     *
     * @return array<int, array{path: string, url: string, label: string}>
     */
    public static function presetImageOptions(): array
    {
        $raw = [
            ['path' => 'fotocopy_catalog/print-hitam-putih-a4-70gsm.webp', 'label' => 'Print — Dokumen A4 / HVS'],
            ['path' => 'fotocopy_catalog/print-warna-teks-grafik-a4.webp', 'label' => 'Print — Cetak Warna & Grafik'],
            ['path' => 'fotocopy_catalog/fotokopi-hitam-putih-a4-1-sisi.webp', 'label' => 'Fotocopy — Mesin & Berkas HVS'],
            ['path' => 'fotocopy_catalog/jilid-softcover-direct-binding.webp', 'label' => 'Jilid — Softcover / Jilid Lakban'],
            ['path' => 'fotocopy_catalog/jilid-hardcover-skripsi-tinta-emas.webp', 'label' => 'Jilid — Hardcover Skripsi Emas'],
            ['path' => 'fotocopy_catalog/laminating-a4-ijazah-sertifikat.webp', 'label' => 'Finishing — Laminating & Stiker'],
            ['path' => 'fotocopy_catalog/scan-dokumen-pdf-jpg-per-lembar.webp', 'label' => 'Digital — Scan PDF & Arsip'],
            ['path' => 'fotocopy_catalog/pulpen-gel-pink-pastel-05mm.webp', 'label' => 'ATK — Pulpen Gel Pink Pastel'],
            ['path' => 'fotocopy_catalog/correction-tape-joyko-5mm-12m.webp', 'label' => 'ATK — Tipe-X / Correction Tape'],
            ['path' => 'fotocopy_catalog/joyko-highlighter-pastel-hl.webp', 'label' => 'ATK — Stabilo / Highlighter Pastel'],
            ['path' => 'fotocopy_catalog/sticky-notes-3x3-joyko-pad.webp', 'label' => 'ATK — Sticky Notes / Post-It'],
            ['path' => 'fotocopy_catalog/map-kancing-plastik-1-folio.webp', 'label' => 'ATK — Map Kancing & Binder'],
        ];

        return array_values(array_filter(array_map(function (array $row): ?array {
            $clean = ltrim($row['path'], '/');
            if (!is_file(public_path('storage/' . $clean))) {
                return null;
            }

            return [
                'path' => $clean,
                'url' => asset('storage/' . $clean),
                'label' => $row['label'],
            ];
        }, $raw)));
    }

    protected function resolveServiceImagePath(Request $request, string $category, ?string $currentImagePath = null, bool $isCreate = false): ?string
    {
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            return $request->file('image')->store('fotocopy_catalog/uploads', 'public');
        }

        if ($request->boolean('remove_image')) {
            return self::defaultCategoryImagePath($category);
        }

        if ($request->filled('image_preset')) {
            $preset = ltrim(trim((string) $request->input('image_preset')), '/');
            if (str_starts_with($preset, 'fotocopy_catalog/') && is_file(public_path('storage/' . $preset))) {
                return $preset;
            }
        }

        if ($isCreate || empty($currentImagePath)) {
            return self::defaultCategoryImagePath($category);
        }

        return $currentImagePath;
    }

    public function create()
    {
        $scopedStoreId = $this->authorizeScopedService();
        $storesQuery = FotocopyStore::orderBy('name');
        if ($scopedStoreId !== null) {
            $storesQuery->where('id', $scopedStoreId);
        }
        $stores = $storesQuery->get(['id', 'name']);

        $subCatQuery = FotocopyService::whereNotNull('sub_category')
            ->where('sub_category', '!=', '');
        if ($scopedStoreId !== null) {
            $subCatQuery->where('store_id', $scopedStoreId);
        }
        $subCategories = $subCatQuery
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category');

        $presetImages = self::presetImageOptions();
        $categoryDefaultUrls = self::categoryDefaultUrls();

        return view('features.fotocopy.admin.services.create', compact('stores', 'subCategories', 'presetImages', 'categoryDefaultUrls'));
    }

    public function store(Request $request)
    {
        $scopedStoreId = $this->authorizeScopedService();
        $validated = $this->validateService($request);
        if ($scopedStoreId !== null) {
            $validated['store_id'] = $scopedStoreId;
        }

        $validated['image_path'] = $this->resolveServiceImagePath($request, (string) $validated['category'], null, true);

        $service = FotocopyService::create($validated);
        $this->syncToPhotocopyItem($service);

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Item layanan '{$validated['name']}' berhasil ditambahkan ke katalog!");
    }

    public function edit(FotocopyService $service)
    {
        $scopedStoreId = $this->authorizeScopedService($service);
        $storesQuery = FotocopyStore::orderBy('name');
        if ($scopedStoreId !== null) {
            $storesQuery->where('id', $scopedStoreId);
        }
        $stores = $storesQuery->get(['id', 'name']);

        $subCatQuery = FotocopyService::whereNotNull('sub_category')
            ->where('sub_category', '!=', '');
        if ($scopedStoreId !== null) {
            $subCatQuery->where('store_id', $scopedStoreId);
        }
        $subCategories = $subCatQuery
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category');

        $presetImages = self::presetImageOptions();
        $categoryDefaultUrls = self::categoryDefaultUrls();

        return view('features.fotocopy.admin.services.edit', compact('service', 'stores', 'subCategories', 'presetImages', 'categoryDefaultUrls'));
    }

    public function update(Request $request, FotocopyService $service)
    {
        $scopedStoreId = $this->authorizeScopedService($service);
        $oldStoreId = (int) $service->store_id;
        $oldName = $service->name;

        $validated = $this->validateService($request);
        if ($scopedStoreId !== null) {
            $validated['store_id'] = $scopedStoreId;
        }

        $validated['image_path'] = $this->resolveServiceImagePath(
            $request,
            (string) $validated['category'],
            $service->image_path,
            false
        );

        $service->update($validated);
        $service->load('store');
        $this->syncToPhotocopyItem($service, $oldStoreId, $oldName);

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Item layanan '{$service->name}' berhasil diperbarui!");
    }

    public function destroy(FotocopyService $service)
    {
        $this->authorizeScopedService($service);
        $name = $service->name;

        try {
            $this->syncService->deleteItemForService($service);
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menghapus PhotocopyItem: ' . $e->getMessage());
        }

        $service->delete();

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Layanan '{$name}' berhasil dihapus dari katalog.");
    }

    /**
     * Validasi dan normalisasi input form create/edit layanan & barang ATK.
     *
     * @return array<string, mixed>
     */
    protected function validateService(Request $request): array
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:fotocopy_stores,id',
            'name' => 'required|string|max:150',
            'category' => 'required|in:' . implode(',', self::ALLOWED_CATEGORIES),
            'item_type' => 'nullable|in:jasa,barang',
            'sub_category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'pack_qty' => 'nullable|integer|min:1',
            'stock' => 'nullable|integer|min:0',
            'price' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_available' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'image_preset' => 'nullable|string|max:255',
            'remove_image' => 'nullable|boolean',
        ]);

        unset($validated['image'], $validated['image_preset'], $validated['remove_image']);
        $validated['item_type'] = !empty($validated['item_type'])
            ? $validated['item_type']
            : (($validated['category'] ?? '') === 'ATK' ? 'barang' : 'jasa');
        $validated['pack_qty'] = (int) ($validated['pack_qty'] ?? 1);
        $validated['brand'] = !empty($validated['brand']) ? $validated['brand'] : null;
        $validated['sub_category'] = !empty($validated['sub_category']) ? $validated['sub_category'] : null;

        if ($validated['item_type'] === 'jasa') {
            $validated['stock'] = null;
        }

        return $validated;
    }
}
