<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemResolutionController;
use App\Http\Controllers\ItemScanPollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ScanHistoryController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing.index')->name('home');

// Satu jenis akun saja: semua yang sudah login punya akses yang sama ke datanya sendiri.
// Kepemilikan data dicek di tiap controller (abort 403 kalau bukan milik pengguna).
Route::middleware('auth')->group(function () {

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    // Dasbor mengarah ke kegiatan yang sedang aktif.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Kegiatan (daftar bawaan) ----
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('/activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    Route::post('/activities/{activity}/activate', [ActivityController::class, 'activate'])->name('activities.activate');
    Route::post('/activities/{activity}/restart', [ActivityController::class, 'restart'])->name('activities.restart');

    // Barang tidak kembali saat scan pulang: dipinjam/dititipkan atau hilang (butuh confirmed=1)
    Route::post('/activities/{activity}/items/{item}/resolve', [ItemResolutionController::class, 'store'])
        ->name('items.resolve');

    // ---- Barang + stiker RFID ----
    Route::get('/items/scan-poll', [ItemScanPollController::class, 'show'])->name('items.scan-poll');
    Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);

    // ---- Pindai ----
    Route::get('/scan-history', [ScanHistoryController::class, 'index'])->name('scan-history.index');
    Route::get('/scan-history/latest', [ScanHistoryController::class, 'latest'])->name('scan-history.latest');
    Route::get('/scan-history/export', [ScanHistoryController::class, 'export'])->name('scan-history.export');
    Route::post('/scan-history/mode', [ScanHistoryController::class, 'setMode'])->name('scan-history.mode');

    // ---- Profil ----
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
