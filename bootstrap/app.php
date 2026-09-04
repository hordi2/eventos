<?php

declare(strict_types=1);

use App\Http\Middleware\AuthorizeOrganizationAbility;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveApiCheckInEvent;
use App\Http\Middleware\ResolveCurrentOrganization;
use App\Http\Middleware\ResolveGuestEvent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // La découverte automatique des listeners (scan de App\Listeners par
    // signature de handle()) doublait silencieusement chaque abonnement déjà
    // posé explicitement dans AppServiceProvider::boot() — un
    // RegistrationCreated déclenchait deux fois SendConfirmationEmail et
    // SendConfirmationWhatsapp (bug réel trouvé en testant T-045bis, invisible
    // jusqu'ici car aucun test n'affirmait un compte exact d'envois).
    // AppServiceProvider reste la seule source de vérité, y compris pour
    // l'ordre (LinkRegistrationToContact avant les deux listeners de
    // confirmation).
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'resolve-organization' => ResolveCurrentOrganization::class,
            'can-organization' => AuthorizeOrganizationAbility::class,
            'resolve-guest-event' => ResolveGuestEvent::class,
            'resolve-api-check-in-event' => ResolveApiCheckInEvent::class,
        ]);

        // Un webhook de prestataire (Postmark, T-043) ne porte jamais de
        // jeton CSRF — protégé par Basic Auth à la place, voir
        // PostmarkWebhookController.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
