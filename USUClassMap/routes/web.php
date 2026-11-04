<?php

use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LecturerController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\AdminMapController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\PhotoInspectorController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (No Auth Required)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/map', function () {
    return view('map.index');
})->name('map');

Route::get('/bookmarks', function () {
    return view('public.bookmarks');
})->name('bookmarks');

Route::get('/faq', [FaqController::class, 'index'])->name('faq');

// 360 Photo Inspector & SPARQL Calibration (No Auth Required)
Route::get('/admin/cek-foto', [PhotoInspectorController::class, 'index'])->name('admin.cek-foto');
Route::post('/admin/cek-foto/kalibrasi', [PhotoInspectorController::class, 'saveCalibration'])->name('admin.cek-foto.saveCalibration');
Route::get('/admin/cek-foto-p', [PhotoInspectorController::class, 'indexCylinder'])->name('admin.cek-foto-p');

/*
|--------------------------------------------------------------------------
| Authenticated User Profile & Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('map');
    })->name('dashboard');

    Route::get('/my-schedules', [\App\Http\Controllers\ScheduleController::class, 'mySchedules'])->name('my-schedules');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin Routes (Auth + Admin Middleware)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware(['auth', 'admin'])
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Schedules CRUD
        Route::resource('schedules', ScheduleController::class)->except(['show']);

        // Lecturers CRUD
        Route::resource('lecturers', LecturerController::class)->except(['show']);

        // Buildings CRUD
        Route::resource('buildings', BuildingController::class)->except(['show']);

        // Rooms CRUD
        Route::resource('rooms', RoomController::class)->except(['show']);

        // Courses CRUD
        Route::resource('courses', CourseController::class)->except(['show']);

        // Excel Import
        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
        Route::get('/import/template', [ImportController::class, 'downloadTemplate'])->name('import.template');
        Route::get('/import/download-template', [ImportController::class, 'downloadTemplate'])->name('import.download');
    });

/*
|--------------------------------------------------------------------------
| Admin Spatial Map Dashboard (Phase 7.0 — Development Route)
|--------------------------------------------------------------------------
*/

Route::prefix('admin-sementara-/map')->group(function () {
    Route::get('/', [AdminMapController::class, 'index'])->name('admin.map.index');
    Route::post('/building', [AdminMapController::class, 'storeBuilding'])->name('admin.map.storeBuilding');
    Route::put('/building/{building}', [AdminMapController::class, 'updateBuilding'])->name('admin.map.updateBuilding');
    Route::post('/room', [AdminMapController::class, 'storeRoom'])->name('admin.map.storeRoom');
    Route::put('/room/{room}', [AdminMapController::class, 'updateRoom'])->name('admin.map.updateRoom');
    Route::post('/schedule', [AdminMapController::class, 'storeSchedule'])->name('admin.map.storeSchedule');
    Route::get('/rooms/{building}', [AdminMapController::class, 'getRoomsByBuilding'])->name('admin.map.roomsByBuilding');

    // Immersive Multimedia Management Routes (Phase 7.3)
    Route::get('/rooms/{room}/media', [AdminMapController::class, 'getRoomMedia'])->name('admin.map.roomMedia.index');
    Route::post('/rooms/{room}/media', [AdminMapController::class, 'storeRoomMedia'])->name('admin.map.roomMedia.store');
    Route::put('/media/{media}/metadata', [AdminMapController::class, 'updateMediaMetadata'])->name('admin.map.roomMedia.updateMetadata');
    Route::delete('/media/{media}', [AdminMapController::class, 'destroyRoomMedia'])->name('admin.map.roomMedia.destroy');
});

/*
|--------------------------------------------------------------------------
| Breeze Authentication Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
