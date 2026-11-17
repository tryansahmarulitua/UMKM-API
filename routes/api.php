<?php

use App\Http\Controllers\AuthController;
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

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Protected routes – requires valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::apiResource('produk',   ProdukController::class);
    Route::apiResource('kategori', KategoriController::class, ['only' => ['index', 'store', 'update', 'destroy']]);
    Route::get('/profil', [ProfilUMKMController::class, 'show']);
    Route::post('/profil', [ProfilUMKMController::class, 'store']);
    Route::apiResource('laporan-keuangan', LaporanKeuanganController::class);
});
