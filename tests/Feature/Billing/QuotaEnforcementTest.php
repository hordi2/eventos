<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;

/**
 * Type fixé à "corporate" (au lieu du tirage aléatoire par défaut de
 * EventFactory) : les identités soumises ici n'incluent pas de téléphone,
 * obligatoire depuis T-045 pour un type "personnel" — même piège que
 * RegistrationEditTest::registerGuestFor(), qui documente déjà ce
 * comportement. Sans ce fixage, la validation de l'identité échoue
 * silencieusement une fraction du temps (tirage aléatoire d'un type
 * personnel), laissant la réponse rediriger vers le formulaire d'identité
 * au lieu d'avancer — le brouillon garde alors un e-mail vide, ce qui a fait
 * échouer ces tests de façon intermittente avant ce correctif.
 */
function beginAndSubmitRegistration(string $organizationSlug, string $eventSlug, int $eventId, string $email): void
{
    test()->get("/r/{$organizationSlug}/{$eventSlug}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $eventId)->latest('id')->firstOrFail()->resume_token;
    test()->post("/r/{$organizationSlug}/{$eventSlug}/{$token}/identite", ['email' => $email]);
    test()->post("/r/{$organizationSlug}/{$eventSlug}/{$token}/reponses", []);
    test()->post("/r/{$organizationSlug}/{$eventSlug}/{$token}/recap");
}

it('confirme une inscription tant que le quota mensuel du plan n\'est pas atteint', function (): void {
    config(['plans.free.registrations_per_month' => 5]);
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);

    beginAndSubmitRegistration($organization->slug, $event->slug, $event->id, 'premier@example.com');

    $registration = Registration::withoutGlobalScopes()->where('email', 'premier@example.com')->firstOrFail();
    expect($registration->status->value)->toBe('confirmed');
});

it('bascule une inscription en liste d\'attente au-delà du quota mensuel, sans jamais la rejeter (T-074)', function (): void {
    config(['plans.free.registrations_per_month' => 1]);
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);

    beginAndSubmitRegistration($organization->slug, $event->slug, $event->id, 'premier@example.com');
    beginAndSubmitRegistration($organization->slug, $event->slug, $event->id, 'second@example.com');

    $first = Registration::withoutGlobalScopes()->where('email', 'premier@example.com')->firstOrFail();
    $second = Registration::withoutGlobalScopes()->where('email', 'second@example.com')->firstOrFail();

    expect($first->status->value)->toBe('confirmed');
    // Jamais un rejet : l'inscription est bien créée, seulement mise en
    // attente (AC T-074 : « blocage non destructif »).
    expect($second->status->value)->toBe('waitlisted');
});

it('n\'applique aucun quota quand le plan est illimité pour cette métrique', function (): void {
    config(['plans.free.registrations_per_month' => null]);
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);

    beginAndSubmitRegistration($organization->slug, $event->slug, $event->id, 'premier@example.com');
    beginAndSubmitRegistration($organization->slug, $event->slug, $event->id, 'second@example.com');

    $second = Registration::withoutGlobalScopes()->where('email', 'second@example.com')->firstOrFail();
    expect($second->status->value)->toBe('confirmed');
});
