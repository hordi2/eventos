<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\WebhookSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('auth.login');

    // Test de connexion : premier appel que font Zapier et n8n quand
    // l'utilisateur colle sa clé API.
    Route::middleware(['auth:sanctum', 'resolve-api-organization'])
        ->get('me', [AuthController::class, 'me'])
        ->name('me');

    // REST Hooks (Zapier, n8n) : abonnement/désabonnement piloté par
    // l'outil externe lui-même, avec une clé API personnelle.
    Route::middleware(['auth:sanctum', 'resolve-api-organization'])
        ->prefix('hooks')
        ->name('hooks.')
        ->group(function (): void {
            Route::post('/', [WebhookSubscriptionController::class, 'store'])->name('store');
            Route::delete('{webhook}', [WebhookSubscriptionController::class, 'destroy'])->name('destroy');
            Route::get('sample', [WebhookSubscriptionController::class, 'sample'])->name('sample');
        });

    Route::middleware(['auth:sanctum', 'resolve-api-check-in-event'])
        ->prefix('events/{event}')
        ->name('check-in.')
        ->group(function (): void {
            Route::get('guests', [CheckInController::class, 'guests'])->name('guests');
            Route::post('check-ins', [CheckInController::class, 'store'])->name('store');
            Route::post('check-ins/sync', [CheckInController::class, 'sync'])->name('sync');
        });
});
