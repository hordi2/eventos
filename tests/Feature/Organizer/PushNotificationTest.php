<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Messaging\Models\PushNotification;
use App\Domain\Messaging\Models\PushSubscription;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Une paire VAPID de test, générée une fois pour ce fichier : signer un jeton
 * ES256 exige une vraie clé EC, même quand l'appel HTTP qui suit est simulé.
 */
function testVapidConfig(): void
{
    config([
        'webpush.public_key' => 'BMkIIUURS64tQfqONAJ-xW0BeMBGxj2DCg0sTV2OTsDtoYbVczslcYKpD06v14DNLOc9aDCKs3mv-jEG06Ye7Bg',
        'webpush.private_key' => 'LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JR0hBZ0VBTUJNR0J5cUdTTTQ5QWdFR0NDcUdTTTQ5QXdFSEJHMHdhd0lCQVFRZ3FQRHJFOUlEbDhmd29QeFcKMi9SVEs2WS9DTlFXKzNvdkxOZGMwVWJTbzE2aFJBTkNBQVRKQ0NGRkVVdXVMVUg2ampRQ2ZzVnRBWGpBUnNZOQpnd29OTEUxZGprN0E3YUdHMVhNN0pYR0NxUTlPcjllQXpTem5QV2d3aXJONXIvb3hCdE9tSHV3WQotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tCg==',
        'webpush.subject' => 'mailto:contact@itaza.app',
    ]);
}

/**
 * Une inscription minimale, explicitement rattachée à $organization et
 * $event à chaque étage (Form, FormVersion, Registration) : sans cela, les
 * valeurs par défaut imbriquées des factories (`event_id => Event::factory()`
 * dans RegistrationFactory, etc.) créeraient chacune leur propre organisation,
 * rejetée par la RLS dès que PushSubscriptionFactory résout son
 * `registration_id` par défaut (même piège que registerContactForEvent).
 */
function pushSubscriptionRegistration(Organization $organization, Event $event): Registration
{
    $form = Form::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);

    return Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
    ]);
}

it('affiche la page avec le nombre d\'abonnés et les notifications déjà envoyées', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_participant_app' => true]);
    $registration = pushSubscriptionRegistration($organization, $event);
    PushSubscription::factory()->count(3)->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
    ]);
    PushNotification::factory()->sent()->create(['organization_id' => $organization->id, 'event_id' => $event->id, 'title' => 'Le vin d\'honneur commence']);

    $this->actingAs($admin)->get("/events/{$event->id}/notifications-push")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subscriberCount', 3)
            ->where('notifications.0.title', 'Le vin d\'honneur commence'));
});

it('diffuse une notification aux abonnés et journalise l\'envoi', function (): void {
    testVapidConfig();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response('', 201)]);

    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_participant_app' => true]);
    $registration = pushSubscriptionRegistration($organization, $event);
    PushSubscription::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
    ]);

    $this->actingAs($admin)->post("/events/{$event->id}/notifications-push", [
        'title' => 'Le vin d\'honneur commence',
        'body' => 'Rejoignez-nous dans le jardin.',
    ])->assertSessionHas('status', 'push-notification-sent');

    $notification = PushNotification::query()->sole();
    expect($notification->title)->toBe('Le vin d\'honneur commence')
        ->and($notification->recipient_count)->toBe(1)
        ->and($notification->sent_at)->not->toBeNull();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://fcm.googleapis.com/fcm/send/abc123'
        && str_starts_with((string) $request->header('Authorization')[0], 'vapid t='));

    expect(AuditLog::query()->where('action', 'push_notification.sent')->sole()->metadata)
        ->toMatchArray(['title' => 'Le vin d\'honneur commence', 'recipient_count' => 1]);
});

it('supprime un abonnement dont le service de push répond qu\'il a expiré', function (): void {
    testVapidConfig();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response('', 410)]);

    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_participant_app' => true]);
    $registration = pushSubscriptionRegistration($organization, $event);
    PushSubscription::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/perime',
    ]);

    $this->actingAs($admin)->post("/events/{$event->id}/notifications-push", [
        'title' => 'Titre',
        'body' => 'Message',
    ]);

    expect(PushSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('refuse d\'envoyer sans abonné, ou sans VAPID configuré, ou sans le droit d\'écrire aux invités', function (): void {
    // Remis à zéro explicitement : config() persiste d'un test à l'autre dans
    // le même process Pest, et les deux tests précédents ont configuré VAPID.
    config(['webpush.public_key' => null, 'webpush.private_key' => null, 'webpush.subject' => null]);

    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->published()->create(['has_participant_app' => true]);

    // Aucun abonné : rien à envoyer, même sans VAPID configuré en test.
    $this->actingAs($admin)->post("/events/{$event->id}/notifications-push", ['title' => 'Titre', 'body' => 'Message'])
        ->assertSessionHasErrors('push');

    $registration = pushSubscriptionRegistration($organization, $event);
    PushSubscription::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
    ]);

    // VAPID non configuré (valeur par défaut des tests) : refus explicite.
    $this->actingAs($admin)->post("/events/{$event->id}/notifications-push", ['title' => 'Titre', 'body' => 'Message'])
        ->assertSessionHasErrors('push');

    testVapidConfig();
    Http::fake();
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->post("/events/{$event->id}/notifications-push", ['title' => 'Titre', 'body' => 'Message'])
        ->assertForbidden();

    Http::assertNothingSent();
});
