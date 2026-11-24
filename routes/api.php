<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\OpenFinanceSyncController;
use App\Http\Controllers\Api\V1\PluggyConnectController;
use App\Http\Controllers\Api\V1\PluggyWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthCheckController::class)->name('api.v1.health');

    Route::post('/webhooks/pluggy', PluggyWebhookController::class)
        ->middleware('throttle:60,1')
        ->name('api.v1.webhooks.pluggy');

    Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
        Route::post('/google', [AuthController::class, 'google'])->name('api.v1.auth.google');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        Route::get('/me', static fn (Request $request) => response()->json([
            'data' => $request->user(),
        ]))->name('api.v1.me');

        Route::get('/dashboard/summary', DashboardSummaryController::class)->name('api.v1.dashboard.summary');
        Route::post('/pluggy/connect-token', PluggyConnectController::class)->name('api.v1.pluggy.connect_token');
        Route::post('/sync/open-finance', OpenFinanceSyncController::class)->name('api.v1.sync.open_finance');
    });
});
