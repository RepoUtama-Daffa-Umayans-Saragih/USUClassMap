<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with summary statistics.
     */
    public function index()
    {
        $totalSchedules = Schedule::count();
        $totalLecturers = Lecturer::count();
        $totalBuildings = Building::count();
        $totalRooms = Room::count();
        $totalCourses = Course::count();

        $latestSchedules = Schedule::with(['course', 'lecturer', 'room'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalSchedules',
            'totalLecturers',
            'totalBuildings',
            'totalRooms',
            'totalCourses',
            'latestSchedules'
        ));
    }
}
