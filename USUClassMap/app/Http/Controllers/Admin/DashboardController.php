<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'schedules' => Schedule::count(),
            'lecturers' => Lecturer::count(),
            'buildings' => Building::count(),
            'rooms'     => Room::count(),
        ];

        $recentSchedules = Schedule::with(['room.building', 'lecturer', 'course'])
            ->latest()
            ->take(5)
            ->get();

        return view('features.admin.dashboard', compact('stats', 'recentSchedules'));
    }
}
