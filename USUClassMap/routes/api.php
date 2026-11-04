<?php

use App\Http\Controllers\Api\BuildingApiController;
use App\Http\Controllers\Api\ScheduleApiController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - USUClassMap Semantic Web, Geospatial & Sync Endpoints
|--------------------------------------------------------------------------
*/

// Semantic Web SPARQL Schedule Search
Route::get('/schedules/search', [ScheduleApiController::class, 'search'])->name('api.schedules.search');

// Campus Buildings & GPS Map Pins
Route::get('/buildings', [BuildingApiController::class, 'index'])->name('api.buildings.index');
Route::get('/buildings/{building}', [BuildingApiController::class, 'show'])->name('api.buildings.show');

// Chrome Extension Schedule Synchronization Endpoint
Route::post('/schedule/sync', [ScheduleApiController::class, 'sync'])->name('api.schedule.sync');
Route::post('/schedules/sync', [ScheduleApiController::class, 'sync'])->name('api.schedules.sync');

// Visualisasi Jadwal Mahasiswa di Peta Leaflet (Phase 6)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/my-schedules', [ScheduleController::class, 'mySchedules'])->name('api.my-schedules');
});
