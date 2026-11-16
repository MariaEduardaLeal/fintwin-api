<?php

use App\Http\Controllers\Api\V1\HealthCheckController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthCheckController::class)->name('api.v1.health');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', static fn (Request $request) => response()->json([
            'data' => $request->user(),
        ]))->name('api.v1.me');
    });
});
