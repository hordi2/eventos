<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckInController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'resolve-api-check-in-event'])
        ->prefix('events/{event}')
        ->name('check-in.')
        ->group(function (): void {
            Route::get('guests', [CheckInController::class, 'guests'])->name('guests');
            Route::post('check-ins', [CheckInController::class, 'store'])->name('store');
            Route::post('check-ins/sync', [CheckInController::class, 'sync'])->name('sync');
        });
});
