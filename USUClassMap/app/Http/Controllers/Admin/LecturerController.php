<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LecturerRequest;
use App\Models\Lecturer;
use Illuminate\Http\Request;

class LecturerController extends Controller
{
    public function index(Request $request)
    {
        $query = Lecturer::withCount('schedules');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $lecturers = $query->latest()->paginate(15)->withQueryString();

        return view('admin.lecturers.index', compact('lecturers'));
    }

    public function create()
    {
        return view('admin.lecturers.create');
    }

    public function store(LecturerRequest $request)
    {
        Lecturer::create($request->validated());

        return redirect()->route('admin.lecturers.index')
            ->with('success', 'Dosen berhasil ditambahkan.');
    }

    public function edit(Lecturer $lecturer)
    {
        return view('admin.lecturers.edit', compact('lecturer'));
    }

    public function update(LecturerRequest $request, Lecturer $lecturer)
    {
        $lecturer->update($request->validated());

        return redirect()->route('admin.lecturers.index')
            ->with('success', 'Data dosen berhasil diperbarui.');
    }

    public function destroy(Lecturer $lecturer)
    {
        $lecturer->delete();

        return redirect()->route('admin.lecturers.index')
            ->with('success', 'Dosen berhasil dihapus.');
    }
}
