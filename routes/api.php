<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DesaController;
use App\Http\Controllers\Api\DhkpController;
use App\Http\Controllers\Api\DusunController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SetoranKecamatanController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API Routes - LENTERA (Layanan Elektronik Terpadu Pajak Daerah) (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public Settings & Health Routes
    Route::get('/settings', [SettingController::class, 'index']);
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'LENTERA (Layanan Elektronik Terpadu Pajak Daerah) API',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Audit Logs & Activity Trail Route
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        // Users Management Routes (RBAC)
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}/status', [UserController::class, 'toggleStatus']);
        Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

        // DHKP & Master SPPT Routes
        Route::get('/dhkp/summary', [DhkpController::class, 'summary']);
        Route::get('/dhkp', [DhkpController::class, 'index']);
        Route::get('/dhkp/{id}', [DhkpController::class, 'show']);
        Route::post('/dhkp', [DhkpController::class, 'store']);
        Route::put('/dhkp/{id}', [DhkpController::class, 'update']);
        Route::post('/dhkp/import', [DhkpController::class, 'import']);
        Route::delete('/dhkp/{id}', [DhkpController::class, 'destroy']);

        // Dusun Routes (Master Dusun & Per-Desa Scope)
        Route::get('/dusun', [DusunController::class, 'index']);
        Route::get('/dusuns', [DusunController::class, 'index']);
        Route::post('/dusuns', [DusunController::class, 'store']);
        Route::get('/dusuns/{id}', [DusunController::class, 'show']);
        Route::put('/dusuns/{id}', [DusunController::class, 'update']);
        Route::patch('/dusuns/{id}/toggle-status', [DusunController::class, 'toggleStatus']);
        Route::delete('/dusuns/{id}', [DusunController::class, 'destroy']);

        // Transactions & Kasir STTS Routes (Protected with Rate Limiting)
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/transactions', [TransactionController::class, 'pay']);
            Route::post('/transactions/pay', [TransactionController::class, 'pay']);
            Route::delete('/transactions/{id}', [TransactionController::class, 'void']);
            Route::post('/transactions/{id}/void', [TransactionController::class, 'void']);
            Route::post('/transactions/group', [TransactionController::class, 'createGroup']);
            Route::post('/transactions/ungroup', [TransactionController::class, 'dissolveGroup']);
        });

        // Setoran ke Kecamatan Routes (Protected with Rate Limiting)
        Route::get('/setoran-kecamatan/summary', [SetoranKecamatanController::class, 'summary']);
        Route::get('/setoran-kecamatan/pending-reviews', [SetoranKecamatanController::class, 'pendingReviews']);
        Route::get('/setoran-kecamatan', [SetoranKecamatanController::class, 'index']);
        Route::get('/setoran-kecamatan/{id}', [SetoranKecamatanController::class, 'show']);
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/setoran-kecamatan', [SetoranKecamatanController::class, 'store']);
            Route::put('/setoran-kecamatan/{id}', [SetoranKecamatanController::class, 'update']);
            Route::post('/setoran-kecamatan/{id}/verify', [SetoranKecamatanController::class, 'verify']);
            Route::delete('/setoran-kecamatan/{id}', [SetoranKecamatanController::class, 'destroy']);
        });

        // Reports Routes
        Route::get('/reports/21-column', [ReportController::class, 'report21Column']);
        Route::get('/reports/21-columns', [ReportController::class, 'report21Column']);

        // Settings Save Route
        Route::post('/settings', [SettingController::class, 'update']);

        // Multi-Desa Management Routes (Super Admin System)
        Route::get('/desas', [DesaController::class, 'index']);
        Route::post('/desas', [DesaController::class, 'store']);
        Route::get('/desas/{id}', [DesaController::class, 'show']);
        Route::put('/desas/{id}', [DesaController::class, 'update']);
        Route::patch('/desas/{id}/toggle-status', [DesaController::class, 'toggleStatus']);
        Route::delete('/desas/{id}', [DesaController::class, 'destroy']);
    });
});
