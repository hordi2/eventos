<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'organisation et l'événement depuis les segments de route publics
 * ({organization}/{event}, par slug) et positionne le contexte multi-tenant
 * — pas de middleware "resolve-organization" ici, l'invité n'est jamais
 * authentifié. Bloque aussi l'accès à un événement non publié ou protégé
 * par mot de passe non encore validé, avant que la moindre requête
 * Domain/Form (RLS comprise) ne puisse s'exécuter.
 */
final class ResolveGuestEvent
{
    /**
     * Routes qui doivent rester joignables même sans mot de passe validé —
     * elles servent justement à le saisir.
     */
    private const PASSWORD_EXEMPT_ROUTES = [
        'guest.registration.password.show',
        'guest.registration.password.verify',
    ];

    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = Organization::query()->where('slug', $request->route('organization'))->firstOrFail();
        $this->currentOrganization->set($organization);

        $event = Event::query()->where('slug', $request->route('event'))->firstOrFail();

        abort_if($event->status !== EventStatus::Published, 404);

        $needsPassword = $event->access_mode === EventAccessMode::Password
            && ! in_array($request->route()?->getName(), self::PASSWORD_EXEMPT_ROUTES, true)
            && ! $request->session()->get("guest_event_password_verified.{$event->id}");

        if ($needsPassword) {
            return Redirect::route('guest.registration.password.show', [$organization->slug, $event->slug]);
        }

        $request->attributes->set('guestOrganization', $organization);
        $request->attributes->set('guestEvent', $event);

        return $next($request);
    }
}
