<?php

declare(strict_types=1);

use App\Http\Controllers\Guest\RegistrationController;
use App\Http\Controllers\Organizer\AuditLogController;
use App\Http\Controllers\Organizer\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Organizer\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Organizer\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Organizer\Auth\GoogleAuthController;
use App\Http\Controllers\Organizer\Auth\NewPasswordController;
use App\Http\Controllers\Organizer\Auth\PasswordResetLinkController;
use App\Http\Controllers\Organizer\Auth\RegisteredUserController;
use App\Http\Controllers\Organizer\Auth\VerifyEmailController;
use App\Http\Controllers\Organizer\DashboardController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\FormController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)
        ->middleware(['verified', 'resolve-organization'])
        ->name('dashboard');

    Route::middleware(['verified', 'resolve-organization', 'can-organization:viewAuditLog'])->group(function (): void {
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        Route::get('audit-log/export', [AuditLogController::class, 'export'])->name('audit-log.export');
    });

    Route::middleware(['verified', 'resolve-organization'])->group(function (): void {
        Route::middleware('can-organization:createEvents')->group(function (): void {
            Route::get('events/create', [EventController::class, 'create'])->name('events.create');
            Route::post('events', [EventController::class, 'store'])->name('events.store');
        });

        Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::patch('events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');

        Route::middleware('can-organization:createEvents')->group(function (): void {
            Route::get('events/{event}/form/create', [FormController::class, 'create'])->name('forms.create');
            Route::post('events/{event}/form', [FormController::class, 'store'])->name('forms.store');
        });

        Route::get('forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
        Route::patch('forms/{form}', [FormController::class, 'update'])->name('forms.update');
        Route::post('forms/{form}/publish', [FormController::class, 'publish'])->name('forms.publish');
    });
});

// Page RSVP publique (T-031) : jamais d'authentification, jamais de
// création de compte demandée à l'invité. resolve-guest-event résout
// organisation + événement depuis le slug et pose le contexte multi-tenant.
Route::middleware('resolve-guest-event')
    ->prefix('r/{organization}/{event}')
    ->name('guest.registration.')
    ->group(function (): void {
        Route::get('mot-de-passe', [RegistrationController::class, 'passwordShow'])->name('password.show');
        Route::post('mot-de-passe', [RegistrationController::class, 'passwordVerify'])->name('password.verify');

        Route::get('/', [RegistrationController::class, 'start'])->name('start');

        Route::get('{token}/identite', [RegistrationController::class, 'identityShow'])->name('identity.show');
        Route::post('{token}/identite', [RegistrationController::class, 'identityStore'])->name('identity.store');

        Route::get('{token}/reponses', [RegistrationController::class, 'answersShow'])->name('answers.show');
        Route::post('{token}/reponses', [RegistrationController::class, 'answersStore'])->name('answers.store');

        Route::get('{token}/recap', [RegistrationController::class, 'reviewShow'])->name('review.show');
        Route::post('{token}/recap', [RegistrationController::class, 'reviewConfirm'])->name('review.confirm');

        Route::get('{token}/confirmation', [RegistrationController::class, 'confirmation'])->name('confirmation');
        Route::get('{token}/deja-inscrit', [RegistrationController::class, 'duplicate'])->name('duplicate');
    });
