<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\KategoriController;
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

// AI Chatbot
Route::post('/chat', ChatController::class)->middleware('throttle:30,1');

// Protected routes – requires valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::get('/auth/login-history', [AuthController::class, 'loginHistory']);
    Route::apiResource('produk',   ProdukController::class);
    Route::apiResource('kategori', KategoriController::class, ['only' => ['index', 'store', 'update', 'destroy']]);
    Route::get('/profil', [ProfilUMKMController::class, 'show']);
    Route::post('/profil', [ProfilUMKMController::class, 'store']);
    Route::apiResource('laporan-keuangan', LaporanKeuanganController::class)
        ->parameters(['laporan-keuangan' => 'laporan']);
});
