<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\PushSubscription;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\URL;

/**
 * Abonnement aux notifications push de l'application participant (D9).
 *
 * @return array{organization: Organization, event: object, registration: Registration, subscribeUrl: string, unsubscribeUrl: string}
 */
function pushSubscriptionEvent(bool $open = true): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_participant_app' => $open,
    ]);

    app(CurrentOrganization::class)->set($organization);
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
    ]);
    app(CurrentOrganization::class)->clear();

    $params = [$organization->slug, $event->slug, $registration->id];

    return [
        'organization' => $organization,
        'event' => $event,
        'registration' => $registration,
        'subscribeUrl' => URL::temporarySignedRoute('guest.registration.push-subscriptions.store', now()->addDay(), $params),
        'unsubscribeUrl' => URL::temporarySignedRoute('guest.registration.push-subscriptions.destroy', now()->addDay(), $params),
    ];
}

it('abonne une inscription confirmée aux notifications push', function (): void {
    ['event' => $event, 'registration' => $registration, 'subscribeUrl' => $url] = pushSubscriptionEvent();

    $this->postJson($url, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertNoContent();

    $subscription = PushSubscription::query()->withoutGlobalScopes()->sole();
    expect($subscription->event_id)->toBe($event->id)
        ->and($subscription->registration_id)->toBe($registration->id)
        ->and($subscription->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/abc123');
});

it('refuse un abonnement dont l\'endpoint est invalide', function (): void {
    ['subscribeUrl' => $url] = pushSubscriptionEvent();

    $this->postJson($url, ['endpoint' => 'pas-une-url'])->assertUnprocessable();

    expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('met à jour l\'inscription d\'un abonnement déjà existant pour le même endpoint', function (): void {
    ['event' => $event, 'subscribeUrl' => $url] = pushSubscriptionEvent();

    app(CurrentOrganization::class)->set(Organization::withoutGlobalScopes()->find($event->organization_id));
    $otherRegistration = Registration::factory()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'form_version_id' => FormVersion::query()->where('organization_id', $event->organization_id)->sole()->id,
        'status' => RegistrationStatus::Confirmed,
    ]);
    PushSubscription::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'registration_id' => $otherRegistration->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
    ]);
    app(CurrentOrganization::class)->clear();

    $this->postJson($url, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertNoContent();

    expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('désabonne un endpoint existant', function (): void {
    ['event' => $event, 'registration' => $registration, 'unsubscribeUrl' => $url] = pushSubscriptionEvent();

    app(CurrentOrganization::class)->set(Organization::withoutGlobalScopes()->find($event->organization_id));
    PushSubscription::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
    ]);
    app(CurrentOrganization::class)->clear();

    $this->deleteJson($url, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertNoContent();

    expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('ferme l\'abonnement push tant que l\'application participant n\'est pas activée', function (): void {
    ['subscribeUrl' => $url] = pushSubscriptionEvent(open: false);

    $this->postJson($url, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertNotFound();
});

it('refuse l\'abonnement push sans lien signé', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = pushSubscriptionEvent();

    $this->postJson("/r/{$organization->slug}/{$event->slug}/inscriptions/{$registration->id}/notifications-push", [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
    ])->assertForbidden();
});
