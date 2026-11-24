<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public const ALLOWED_CATEGORIES = ['Print', 'Fotocopy', 'Jilid', 'Finishing', 'Digital', 'ATK'];

    /**
     * Cari ID PhotocopyPlace berdasarkan slug atau nama toko (jangan pernah pakai raw store_id).
     */
    protected function resolvePlaceId(?FotocopyStore $store): ?int
    {
        if (!$store) {
            return null;
        }

        $place = PhotocopyPlace::where('slug', $store->slug)->first()
            ?? PhotocopyPlace::where('name', $store->name)->first();

        return $place?->id;
    }

    /**
     * Sinkronkan FotocopyService ke PhotocopyItem agar halaman publik (/fotocopy/{id}) selalu real-time.
     */
    protected function syncToPhotocopyItem(FotocopyService $service, ?int $oldStoreId = null, ?string $oldName = null): void
    {
        try {
            $store = $service->relationLoaded('store')
                ? $service->store
                : FotocopyStore::find($service->store_id);

            $placeId = $this->resolvePlaceId($store);
            if (!$placeId) {
                logger()->warning("[USUClassMap Sync] PhotocopyPlace untuk store #{$service->store_id} tidak ditemukan.");
                return;
            }

            $oldPlaceId = $oldStoreId ? $this->resolvePlaceId(FotocopyStore::find($oldStoreId)) : null;

            $item = null;
            if ($oldPlaceId && $oldName) {
                $item = PhotocopyItem::where('photocopy_place_id', $oldPlaceId)
                    ->where('name', $oldName)
                    ->first();
            }
            if (!$item) {
                $item = PhotocopyItem::where('photocopy_place_id', $placeId)
                    ->where('name', $service->name)
                    ->first();
            }

            $payload = [
                'photocopy_place_id' => $placeId,
                'name' => $service->name,
                'category' => $service->category,
                'item_type' => $service->item_type ?? 'jasa',
                'sub_category' => $service->sub_category,
                'brand' => $service->brand,
                'pack_qty' => (int) ($service->pack_qty ?? 1),
                'price' => $service->price,
                'unit' => $service->unit,
                'description' => $service->description,
                'image_path' => $service->image_path,
                'metadata' => $service->metadata,
                'stock' => $service->stock,
                'is_available' => (bool) $service->is_available,
            ];

            if ($item) {
                $item->update($payload);
            } else {
                PhotocopyItem::create($payload);
            }
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menyinkronkan FotocopyService ke PhotocopyItem: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $query = FotocopyService::with('store');

        if ($request->filled('store_id')) {
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
        $stores = FotocopyStore::orderBy('name')->get(['id', 'name']);
        $subCategories = FotocopyService::whereNotNull('sub_category')
            ->where('sub_category', '!=', '')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category');

        return view('features.fotocopy.admin.services.index', compact('services', 'stores', 'subCategories'));
    }

    public function create()
    {
        $stores = FotocopyStore::orderBy('name')->get(['id', 'name']);
        $subCategories = FotocopyService::whereNotNull('sub_category')
            ->where('sub_category', '!=', '')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category');

        return view('features.fotocopy.admin.services.create', compact('stores', 'subCategories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateService($request);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $validated['image_path'] = $request->file('image')->store('fotocopy_catalog/uploads', 'public');
        }

        $service = FotocopyService::create($validated);
        $this->syncToPhotocopyItem($service);

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Item layanan '{$validated['name']}' berhasil ditambahkan ke katalog!");
    }

    public function edit(FotocopyService $service)
    {
        $stores = FotocopyStore::orderBy('name')->get(['id', 'name']);
        $subCategories = FotocopyService::whereNotNull('sub_category')
            ->where('sub_category', '!=', '')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category');

        return view('features.fotocopy.admin.services.edit', compact('service', 'stores', 'subCategories'));
    }

    public function update(Request $request, FotocopyService $service)
    {
        $oldStoreId = (int) $service->store_id;
        $oldName = $service->name;

        $validated = $this->validateService($request);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $validated['image_path'] = $request->file('image')->store('fotocopy_catalog/uploads', 'public');
        }

        $service->update($validated);
        $service->load('store');
        $this->syncToPhotocopyItem($service, $oldStoreId, $oldName);

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Item layanan '{$service->name}' berhasil diperbarui!");
    }

    public function destroy(FotocopyService $service)
    {
        $placeId = $this->resolvePlaceId($service->store ?? FotocopyStore::find($service->store_id));
        $name = $service->name;
        $service->delete();

        if ($placeId) {
            try {
                PhotocopyItem::where('photocopy_place_id', $placeId)
                    ->where('name', $name)
                    ->delete();
            } catch (\Throwable $e) {
                logger()->warning('[USUClassMap Sync] Gagal menghapus PhotocopyItem: ' . $e->getMessage());
            }
        }

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
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        unset($validated['image']);
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
