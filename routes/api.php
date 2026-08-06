<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DhkpController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API Routes - LENTERA Pajak Desa Pro (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

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

        // Transactions & Kasir STTS Routes
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
        Route::post('/transactions', [TransactionController::class, 'pay']);
        Route::post('/transactions/pay', [TransactionController::class, 'pay']);
        Route::delete('/transactions/{id}', [TransactionController::class, 'void']);
        Route::post('/transactions/{id}/void', [TransactionController::class, 'void']);
        Route::post('/transactions/group', [TransactionController::class, 'createGroup']);
        Route::post('/transactions/ungroup', [TransactionController::class, 'dissolveGroup']);

        // Reports Routes
        Route::get('/reports/21-column', [ReportController::class, 'report21Column']);

        // Settings Routes
        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']);
    });
});
