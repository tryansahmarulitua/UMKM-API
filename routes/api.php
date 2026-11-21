<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\RasaController;
use App\Http\Controllers\RingkasanKeuanganController;
use App\Http\Controllers\PesananController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\ProfilUMKMController;
use App\Http\Controllers\LaporanKeuanganController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// No public registration (PP §4.5): the admin account is created by the seeder during setup.
Route::post('/auth/login', [AuthController::class, 'login']);

// Public storefront routes
Route::get('/public/produk', [ProdukController::class, 'publicIndex']);
Route::get('/public/profil', [ProfilUMKMController::class, 'publicShow']);
Route::get('/public/pengaturan', [PengaturanController::class, 'publicShow']);

// Checkout without an account (Project Plan §1.2)
Route::post('/pesanan', [PesananController::class, 'store']);

// AI Chatbot
Route::post('/chat', ChatController::class)->middleware('throttle:chat');

// Protected routes – requires valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::get('/auth/login-history', [AuthController::class, 'loginHistory']);
    Route::apiResource('produk',   ProdukController::class);
    Route::apiResource('kategori', KategoriController::class, ['only' => ['index', 'store', 'update', 'destroy']]);
    Route::apiResource('rasa', RasaController::class, ['only' => ['index', 'store', 'update', 'destroy']]);
    Route::get('/profil', [ProfilUMKMController::class, 'show']);
    Route::post('/profil', [ProfilUMKMController::class, 'store']);
    Route::get('/pesanan', [PesananController::class, 'index']);
    Route::get('/pesanan/{pesanan}', [PesananController::class, 'show'])
        ->missing(fn () => response()->json(['message' => 'Pesanan tidak ditemukan.'], 404));
    Route::patch('/pesanan/{pesanan}/status', [PesananController::class, 'ubahStatus'])
        ->missing(fn () => response()->json(['message' => 'Pesanan tidak ditemukan.'], 404));
    Route::get('/pengaturan', [PengaturanController::class, 'show']);
    Route::put('/pengaturan', [PengaturanController::class, 'update']);
    // Before the resource, so "ringkasan" is not read as a report ID.
    Route::get('/laporan-keuangan/ringkasan', RingkasanKeuanganController::class);
    Route::apiResource('laporan-keuangan', LaporanKeuanganController::class)
        ->parameters(['laporan-keuangan' => 'laporan'])
        ->missing(fn () => LaporanKeuanganController::tidakDitemukan());
});
