<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    /**
     * Sinkronkan data FotocopyStore ke PhotocopyPlace agar Peta User (/map) & Direktori Publik (/fotocopy) selalu real-time.
     */
    protected function syncToPhotocopyPlace(FotocopyStore $store, ?string $oldName = null): void
    {
        try {
            $hoursParts = array_map('trim', explode('-', $store->opening_hours ?? '07:30 - 20:30'));
            $openTime = $hoursParts[0] ?? '07:30';
            $closeTime = $hoursParts[1] ?? '20:30';

            $place = PhotocopyPlace::find($store->id);
            if (!$place && $oldName) {
                $place = PhotocopyPlace::where('name', $oldName)->first();
            }
            if (!$place) {
                $place = PhotocopyPlace::where('name', $store->name)->first();
            }

            $payload = [
                'name' => $store->name,
                'slug' => $store->slug ?: (Str::slug($store->name) . '-' . Str::random(5)),
                'location_name' => $store->location_name,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'open_time' => $openTime ?: '07:30',
                'close_time' => $closeTime ?: '20:30',
                'operating_days' => $store->operating_days,
                'whatsapp' => $store->whatsapp,
                'phone' => $store->whatsapp ?: $store->phone,
                'instagram' => $store->instagram,
                'facebook' => $store->facebook,
                'rating' => $store->rating,
                'is_active' => (bool) $store->is_open,
                'business_status' => $store->is_open ? 'OPERATIONAL' : 'CLOSED_TEMPORARILY',
                'description' => $store->description,
            ];

            if ($place) {
                $place->update($payload);
            } else {
                $newPlace = new PhotocopyPlace($payload);
                $newPlace->id = $store->id;
                $newPlace->save();
            }
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menyinkronkan FotocopyStore ke PhotocopyPlace: ' . $e->getMessage());
        }
    }
    public function index(Request $request)
    {
        $query = FotocopyStore::withCount(['services', 'orders']);

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where('name', 'like', "%{$q}%")
                ->orWhere('location_name', 'like', "%{$q}%")
                ->orWhere('whatsapp', 'like', "%{$q}%");
        }

        $stores = $query->latest()->paginate(10)->withQueryString();

        return view('features.fotocopy.admin.stores.index', compact('stores'));
    }

    public function create()
    {
        $cashiers = User::where('role', 'cashier_fotokopi')->get();
        return view('features.fotocopy.admin.stores.create', compact('cashiers'));
    }

    public function store(Request $request)
    {
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
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);

        $store = FotocopyStore::create($validated);
        $this->syncToPhotocopyPlace($store);

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Mitra Toko '{$validated['name']}' berhasil ditambahkan!");
    }

    public function edit(FotocopyStore $store)
    {
        $cashiers = User::where('role', 'cashier_fotokopi')->get();
        return view('features.fotocopy.admin.stores.edit', compact('store', 'cashiers'));
    }

    public function update(Request $request, FotocopyStore $store)
    {
        $oldName = $store->name;

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
        ]);

        $store->update($validated);
        $this->syncToPhotocopyPlace($store, $oldName);

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Data mitra toko '{$store->name}' berhasil diperbarui!");
    }

    public function destroy(FotocopyStore $store)
    {
        $id = $store->id;
        $name = $store->name;
        $store->delete();

        try {
            $place = PhotocopyPlace::find($id) ?? PhotocopyPlace::where('name', $name)->first();
            if ($place) {
                $place->delete();
            }
        } catch (\Throwable $e) {
            logger()->warning('[USUClassMap Sync] Gagal menghapus PhotocopyPlace: ' . $e->getMessage());
        }

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Mitra toko '{$name}' berhasil dihapus.");
    }
}
