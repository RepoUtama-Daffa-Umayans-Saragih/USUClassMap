<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::with('building')->withCount('schedules');

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('building', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $rooms = $query->latest()->paginate(15)->withQueryString();

        return view('admin.rooms.index', compact('rooms'));
    }

    public function create()
    {
        $buildings = Building::orderBy('name')->get();
        return view('admin.rooms.create', compact('buildings'));
    }

    public function store(RoomRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('photo_360')) {
            $file = $request->file('photo_360');
            $filename = 'room_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('rooms/360', $filename, 'public');
            $data['photo_360_path'] = $path;
        }

        Room::create($data);

        return redirect()->route('admin.rooms.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Room $room)
    {
        $buildings = Building::orderBy('name')->get();
        return view('admin.rooms.edit', compact('room', 'buildings'));
    }

    public function update(RoomRequest $request, Room $room)
    {
        $data = $request->validated();

        if ($request->hasFile('photo_360')) {
            // Delete old photo if exists
            if ($room->photo_360_path) {
                Storage::disk('public')->delete($room->photo_360_path);
            }
            $file = $request->file('photo_360');
            $filename = 'room_' . $room->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('rooms/360', $filename, 'public');
            $data['photo_360_path'] = $path;
        }

        $room->update($data);

        return redirect()->route('admin.rooms.index')
            ->with('success', 'Data ruangan berhasil diperbarui.');
    }

    public function destroy(Room $room)
    {
        if ($room->photo_360_path) {
            Storage::disk('public')->delete($room->photo_360_path);
        }
        $room->delete();

        return redirect()->route('admin.rooms.index')
            ->with('success', 'Ruangan berhasil dihapus.');
    }
}
