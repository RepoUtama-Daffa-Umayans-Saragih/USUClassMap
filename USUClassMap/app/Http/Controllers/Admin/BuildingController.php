<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BuildingRequest;
use App\Models\Building;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request)
    {
        $query = Building::withCount('rooms');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $buildings = $query->latest()->paginate(15)->withQueryString();

        return view('admin.buildings.index', compact('buildings'));
    }

    public function create()
    {
        return view('admin.buildings.create');
    }

    public function store(BuildingRequest $request)
    {
        Building::create($request->validated());

        return redirect()->route('admin.buildings.index')
            ->with('success', 'Gedung berhasil ditambahkan.');
    }

    public function edit(Building $building)
    {
        return view('admin.buildings.edit', compact('building'));
    }

    public function update(BuildingRequest $request, Building $building)
    {
        $building->update($request->validated());

        return redirect()->route('admin.buildings.index')
            ->with('success', 'Data gedung berhasil diperbarui.');
    }

    public function destroy(Building $building)
    {
        $building->delete();

        return redirect()->route('admin.buildings.index')
            ->with('success', 'Gedung berhasil dihapus.');
    }
}
