<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleRequest;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = Schedule::with(['course', 'lecturer', 'room.building']);

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->whereHas('course', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                })
                ->orWhereHas('lecturer', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            });
        }

        $schedules = $query->latest()->paginate(15)->withQueryString();

        return view('admin.schedules.index', compact('schedules'));
    }

    public function create()
    {
        $courses = Course::orderBy('name')->get();
        $lecturers = Lecturer::orderBy('name')->get();
        $rooms = Room::with('building')->orderBy('name')->get();
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return view('admin.schedules.create', compact('courses', 'lecturers', 'rooms', 'days'));
    }

    public function store(ScheduleRequest $request)
    {
        Schedule::create($request->validated());

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function edit(Schedule $schedule)
    {
        $courses = Course::orderBy('name')->get();
        $lecturers = Lecturer::orderBy('name')->get();
        $rooms = Room::with('building')->orderBy('name')->get();
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return view('admin.schedules.edit', compact('schedule', 'courses', 'lecturers', 'rooms', 'days'));
    }

    public function update(ScheduleRequest $request, Schedule $schedule)
    {
        $schedule->update($request->validated());

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Jadwal berhasil dihapus.');
    }
}
