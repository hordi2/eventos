<?php

declare(strict_types=1);

use App\Http\Middleware\AuthorizeOrganizationAbility;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentOrganization;
use App\Http\Middleware\ResolveGuestEvent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'resolve-organization' => ResolveCurrentOrganization::class,
            'can-organization' => AuthorizeOrganizationAbility::class,
            'resolve-guest-event' => ResolveGuestEvent::class,
        ]);

        // Un webhook de prestataire (Postmark, T-043) ne porte jamais de
        // jeton CSRF — protégé par Basic Auth à la place, voir
        // PostmarkWebhookController.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
