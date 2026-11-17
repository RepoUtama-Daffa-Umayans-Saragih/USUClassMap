<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyStore;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
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

        FotocopyStore::create($validated);

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

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Data mitra toko '{$store->name}' berhasil diperbarui!");
    }

    public function destroy(FotocopyStore $store)
    {
        $name = $store->name;
        $store->delete();

        return redirect()->route('admin-fotokopi.stores.index')
            ->with('success', "Mitra toko '{$name}' berhasil dihapus.");
    }
}
