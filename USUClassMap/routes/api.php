<?php

use App\Http\Controllers\Api\BuildingApiController;
use App\Http\Controllers\Api\CampusRoutingController;
use App\Http\Controllers\Api\ScheduleApiController;
use App\Http\Controllers\Api\SmartSearchApiController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - USUStarterMap Semantic Web, Geospatial & Sync Endpoints
|--------------------------------------------------------------------------
*/

// Semantic Web SPARQL Schedule Search
Route::get('/schedules/search', [ScheduleApiController::class, 'search'])->name('api.schedules.search');

// Semantic Web SPARQL 1.1 Dedicated Concepts (REGEX, ASK, AS)
Route::prefix('sparql')->group(function () {
    Route::get('/regex-search', [ScheduleApiController::class, 'regexSearch'])->name('api.sparql.regex');
    Route::get('/check-room', [ScheduleApiController::class, 'checkRoom'])->name('api.sparql.check-room');
    Route::get('/building-stats', [ScheduleApiController::class, 'buildingStats'])->name('api.sparql.building-stats');
});

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

// Copy Shops & Percetakan USU Endpoints
Route::prefix('copy-shops')->group(function () {
    Route::get('/services', [\App\Http\Controllers\Api\CopyShopApiController::class, 'services'])->name('api.copy-shops.services');
    Route::get('/', [\App\Http\Controllers\Api\CopyShopApiController::class, 'index'])->name('api.copy-shops.index');
    Route::get('/{id}', [\App\Http\Controllers\Api\CopyShopApiController::class, 'show'])->name('api.copy-shops.show');
});

// In-App Campus Pedestrian & Vehicle Routing Engine
Route::match(['get', 'post'], '/route', [CampusRoutingController::class, 'route'])->name('api.routing.route');
Route::get('/routing/destinations', [CampusRoutingController::class, 'destinations'])->name('api.routing.destinations');

// Unified Smart Search & Smart Photocopy Search Engines
Route::get('/smart-search', [SmartSearchApiController::class, 'search'])->name('api.smart-search');
Route::get('/fotocopy/smart-search', [SmartSearchApiController::class, 'fotocopy'])->name('api.fotocopy.smart-search');
