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
use App\Http\Controllers\PhotocopyController;
use App\Http\Controllers\PhotoInspectorController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (No Auth Required)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('features.landing.index');
})->name('home');

Route::get('/map', function () {
    return view('features.map.index');
})->name('map');

Route::redirect('/bookmarks', '/#bookmarks')->name('bookmarks');

Route::get('/faq', [FaqController::class, 'index'])->name('faq');

// Fotocopy Services (Integrasi Layanan & Pemesanan Kampus)
Route::get('/fotocopy', [PhotocopyController::class, 'index'])->name('fotocopy.index');
Route::get('/fotocopy/{id}', [PhotocopyController::class, 'show'])->name('fotocopy.show');
Route::get('/fotocopy/{id}/order', [PhotocopyController::class, 'order'])->name('fotocopy.order');
Route::post('/fotocopy/{id}/order', [PhotocopyController::class, 'storeOrder'])->name('fotocopy.order.store');
Route::get('/fotocopy/orders/{orderCode}/payment', [PhotocopyController::class, 'paymentInstructions'])->name('fotocopy.orders.payment');
Route::post('/fotocopy/orders/{orderCode}/simulate-payment', [PhotocopyController::class, 'simulatePayment'])->name('fotocopy.orders.simulate-payment');
Route::post('/fotocopy/orders/{orderCode}/dispatch-whatsapp', [PhotocopyController::class, 'dispatchWhatsapp'])->name('fotocopy.orders.dispatchWhatsapp');
Route::get('/fotocopy/orders/{orderCode}/invoice', [PhotocopyController::class, 'downloadInvoice'])->name('fotocopy.orders.invoice');
Route::get('/fotocopy/orders/{orderCode}/invoice/preview', [PhotocopyController::class, 'previewInvoice'])->middleware('signed')->name('fotocopy.orders.invoice.preview');
Route::get('/fotocopy/orders/{orderCode}/download-zip', [PhotocopyController::class, 'downloadOrderZip'])->middleware('signed')->name('fotocopy.orders.download-zip');
Route::get('/fotocopy/orders/{orderCode}/files/{file}/preview', [PhotocopyController::class, 'previewOrderFile'])->middleware('signed')->name('fotocopy.orders.files.preview');
Route::get('/fotocopy/orders/{orderCode}/files/{file}/download', [PhotocopyController::class, 'downloadOrderFile'])->middleware('signed')->name('fotocopy.orders.files.download');

// Rute alternatif direktori mitra fotocopy tim
Route::get('/photocopy-directory', [PhotocopyController::class, 'index'])->name('photocopy.directory');
Route::get('/photocopy-directory/{id}', [PhotocopyController::class, 'show'])->name('photocopy.directory.show');
Route::get('/photocopy-directory/{id}/order', [PhotocopyController::class, 'order'])->name('photocopy.directory.order');
Route::post('/photocopy-directory/{id}/order', [PhotocopyController::class, 'storeOrder'])->name('photocopy.directory.order.store');
Route::get('/photocopy-directory/orders/{orderCode}/payment', [PhotocopyController::class, 'paymentInstructions'])->name('photocopy.directory.orders.payment');
Route::post('/photocopy-directory/orders/{orderCode}/simulate-payment', [PhotocopyController::class, 'simulatePayment'])->name('photocopy.directory.orders.simulate-payment');
Route::post('/photocopy-directory/orders/{orderCode}/dispatch-whatsapp', [PhotocopyController::class, 'dispatchWhatsapp'])->name('photocopy.directory.orders.dispatchWhatsapp');
Route::get('/photocopy-directory/orders/{orderCode}/invoice', [PhotocopyController::class, 'downloadInvoice'])->name('photocopy.directory.orders.invoice');
Route::get('/photocopy-directory/orders/{orderCode}/invoice/preview', [PhotocopyController::class, 'previewInvoice'])->middleware('signed')->name('photocopy.directory.orders.invoice.preview');
Route::get('/photocopy-directory/orders/{orderCode}/download-zip', [PhotocopyController::class, 'downloadOrderZip'])->middleware('signed')->name('photocopy.directory.orders.download-zip');
Route::get('/photocopy-directory/orders/{orderCode}/files/{file}/preview', [PhotocopyController::class, 'previewOrderFile'])->middleware('signed')->name('photocopy.directory.orders.files.preview');
Route::get('/photocopy-directory/orders/{orderCode}/files/{file}/download', [PhotocopyController::class, 'downloadOrderFile'])->middleware('signed')->name('photocopy.directory.orders.files.download');

// 360 Photo Inspector & SPARQL Calibration (No Auth Required)
Route::get('/admin/cek-foto', [PhotoInspectorController::class, 'index'])->name('admin.cek-foto');
Route::post('/admin/cek-foto/kalibrasi', [PhotoInspectorController::class, 'saveCalibration'])->name('admin.cek-foto.saveCalibration');
Route::get('/admin/cek-foto-p', [PhotoInspectorController::class, 'indexCylinder'])->name('admin.cek-foto-p');

// Rexa Copilot AI Chatbot (Phase 2 & 3)
Route::post('/copilot/chat', [\App\Http\Controllers\AiCopilotController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('copilot.chat');

/*
|--------------------------------------------------------------------------
| Authenticated User Profile & Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->role === 'admin_fotokopi') {
            return redirect()->route('admin-fotokopi.dashboard');
        }
        if ($user->role === 'cashier_fotokopi') {
            return redirect()->route('cashier-fotokopi.orders.index');
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
| Admin Fotokopi Routes (Auth + Admin Fotokopi Middleware)
|--------------------------------------------------------------------------
*/

Route::prefix('admin-fotokopi')
    ->middleware(['auth', 'admin.fotokopi'])
    ->name('admin-fotokopi.')
    ->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\AdminFotokopi\DashboardController::class, 'index'])->name('dashboard');
        Route::resource('stores', \App\Http\Controllers\AdminFotokopi\StoreController::class)->except(['show']);
        Route::post('/stores/{store}/test-whatsapp', [\App\Http\Controllers\AdminFotokopi\StoreController::class, 'testWhatsApp'])->name('stores.test-whatsapp');
        Route::get('/whatsapp-sandbox', [\App\Http\Controllers\AdminFotokopi\StoreController::class, 'whatsappSandboxLogs'])->name('stores.whatsapp-sandbox');
        Route::resource('services', \App\Http\Controllers\AdminFotokopi\ServiceController::class)->except(['show']);
        Route::get('/reports', [\App\Http\Controllers\AdminFotokopi\ReportController::class, 'index'])->name('reports.index');
    });

/*
|--------------------------------------------------------------------------
| Cashier Fotokopi Routes (Auth + Cashier Middleware)
|--------------------------------------------------------------------------
*/

Route::prefix('cashier-fotokopi')
    ->middleware(['auth', 'cashier.fotokopi'])
    ->name('cashier-fotokopi.')
    ->group(function () {
        Route::get('/orders', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'index'])->name('orders.index');
        Route::patch('/orders/{order}/status', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/resend-wa', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'resendWhatsApp'])->name('orders.resend-wa');
        Route::get('/orders/{order}/download-zip', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'downloadAllZip'])->name('orders.download-zip');
        Route::get('/orders/{order}/files/{file}', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'downloadOrderFile'])->name('orders.files.download');
        Route::get('/orders/{order}/invoice', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'downloadInvoice'])->name('orders.invoice');
        Route::get('/orders/{order}/file', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'downloadFile'])->name('orders.file');
        Route::get('/orders/{order}/print', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'printJob'])->name('orders.print');
        Route::get('/pos', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'createPos'])->name('orders.pos');
        Route::post('/pos', [\App\Http\Controllers\CashierFotokopi\OrderController::class, 'storePos'])->name('orders.pos.store');
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

    // In-App Campus Spatial Routing Editor Routes (Tahap 4 — RBAC Protected)
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/routing-data', [AdminMapController::class, 'getRoutingData'])->name('admin.map.routing.data');
        Route::post('/routing-overrides', [AdminMapController::class, 'storeRoutingOverride'])->name('admin.map.routing.overrides.store');
        Route::delete('/routing-overrides/{override}', [AdminMapController::class, 'destroyRoutingOverride'])->name('admin.map.routing.overrides.destroy');
        Route::post('/routing-entrance', [AdminMapController::class, 'saveRoutingEntrance'])->name('admin.map.routing.entrance.save');
        Route::post('/routing-rebuild', [AdminMapController::class, 'rebuildRoutingGraph'])->name('admin.map.routing.rebuild');
    });
});

/*
|--------------------------------------------------------------------------
| Breeze Authentication Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
