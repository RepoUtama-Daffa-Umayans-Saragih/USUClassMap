<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = FotocopyService::with('store');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $services = $query->latest()->paginate(12)->withQueryString();
        $stores = FotocopyStore::all(['id', 'name']);

        return view('features.fotocopy.admin.services.index', compact('services', 'stores'));
    }

    public function create()
    {
        $stores = FotocopyStore::all(['id', 'name']);
        return view('features.fotocopy.admin.services.create', compact('stores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:fotocopy_stores,id',
            'name' => 'required|string|max:150',
            'category' => 'required|in:Print,Fotocopy,Jilid,ATK',
            'price' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_available' => 'required|boolean',
        ]);

        FotocopyService::create($validated);

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Item layanan '{$validated['name']}' berhasil ditambahkan ke katalog!");
    }

    public function edit(FotocopyService $service)
    {
        $stores = FotocopyStore::all(['id', 'name']);
        return view('features.fotocopy.admin.services.edit', compact('service', 'stores'));
    }

    public function update(Request $request, FotocopyService $service)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:fotocopy_stores,id',
            'name' => 'required|string|max:150',
            'category' => 'required|in:Print,Fotocopy,Jilid,ATK',
            'price' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_available' => 'required|boolean',
        ]);

        $service->update($validated);

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Item layanan '{$service->name}' berhasil diperbarui!");
    }

    public function destroy(FotocopyService $service)
    {
        $name = $service->name;
        $service->delete();

        return redirect()->route('admin-fotokopi.services.index')
            ->with('success', "Layanan '{$name}' berhasil dihapus dari katalog.");
    }
}
