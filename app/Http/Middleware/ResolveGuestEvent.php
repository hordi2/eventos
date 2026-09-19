<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\RegistrationDraft;
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

    /**
     * Routes joignables sans invitation identifiée, quand l'événement est
     * réservé à sa liste : celles qui servent à la retrouver, la feuille de
     * style, et les liens signés de modification ou d'annulation d'une
     * réponse déjà donnée.
     */
    private const INVITATION_EXEMPT_ROUTES = [
        'guest.registration.invitation.find',
        'guest.registration.invitation.lookup',
        'guest.registration.invitation.open',
        'guest.registration.password.show',
        'guest.registration.password.verify',
        'guest.registration.theme-style',
        'guest.registration.edit',
        'guest.registration.cancel',
    ];

    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = Organization::query()->where('slug', $request->route('organization'))->firstOrFail();
        $this->currentOrganization->set($organization);

        $event = Event::query()->where('slug', $request->route('event'))->firstOrFail();

        abort_unless($event->status === EventStatus::Published || $this->isDraftPreview($request, $event), 404);

        $needsPassword = $event->access_mode === EventAccessMode::Password
            && ! in_array($request->route()?->getName(), self::PASSWORD_EXEMPT_ROUTES, true)
            && ! $request->session()->get("guest_event_password_verified.{$event->id}");

        if ($needsPassword) {
            return Redirect::route('guest.registration.password.show', [$organization->slug, $event->slug]);
        }

        // Invitation identifiée (lien personnel, e-mail ou WhatsApp) : elle
        // personnalise la réponse sur tout événement, et seule ouvre la porte
        // d'un événement réservé à sa liste.
        $invitee = $this->identifiedInvitee($request, $event);

        if ($event->access_mode === EventAccessMode::ClosedList
            && $invitee === null
            && ! in_array($request->route()?->getName(), self::INVITATION_EXEMPT_ROUTES, true)) {
            return Redirect::route('guest.registration.invitation.find', [$organization->slug, $event->slug]);
        }

        $request->attributes->set('guestOrganization', $organization);
        $request->attributes->set('guestEvent', $event);
        $request->attributes->set('guestInvitee', $invitee);

        return $next($request);
    }

    /**
     * Retenue en session avec le jeton du lien qui l'a ouverte : un lien
     * renouvelé (RenewInvitationLink) ne laisse plus entrer le navigateur qui
     * avait ouvert l'ancien. À défaut, portée par le brouillon ouvert (lien de
     * reprise suivi sur un autre appareil) — un renouvellement détache aussi
     * ces brouillons —, puis remise en session.
     */
    private function identifiedInvitee(Request $request, Event $event): ?EventInvitee
    {
        $sessionKey = "guest_invitee.{$event->id}";
        $remembered = $request->session()->get($sessionKey);
        $inviteeId = is_array($remembered) ? ($remembered['id'] ?? null) : null;
        $expectedToken = is_array($remembered) ? ($remembered['token'] ?? null) : null;
        $draftToken = $request->route('token');

        if ($inviteeId === null && is_string($draftToken)) {
            $inviteeId = RegistrationDraft::query()->where('event_id', $event->id)->where('resume_token', $draftToken)->value('event_invitee_id');
        }

        $invitee = $inviteeId === null ? null : EventInvitee::query()->where('event_id', $event->id)->whereHas('contact')->find($inviteeId);

        if ($invitee === null || ($expectedToken !== null && $expectedToken !== $invitee->invitation_token)) {
            $request->session()->forget($sessionKey);

            return null;
        }

        $request->session()->put($sessionKey, ['id' => $invitee->id, 'token' => $invitee->invitation_token]);

        return $invitee;
    }

    /**
     * Lien d'aperçu signé (EventPublicLinks) sur un brouillon. Mémorisé en
     * session : les étapes suivantes du parcours d'inscription ont leurs
     * propres URL, sans la signature. Un événement archivé reste fermé.
     */
    private function isDraftPreview(Request $request, Event $event): bool
    {
        if ($event->status !== EventStatus::Draft) {
            return false;
        }

        $sessionKey = "guest_event_preview.{$event->id}";

        if ($request->hasValidSignature()) {
            $request->session()->put($sessionKey, true);

            return true;
        }

        return (bool) $request->session()->get($sessionKey, false);
    }
}
