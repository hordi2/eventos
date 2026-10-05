<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\AttendeeMeeting;
use App\Domain\Form\Models\AttendeeMeetingStatus;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Networking\AttendeeConversations;
use App\Support\Networking\PlanAttendeeMeeting;
use App\Support\Networking\SuggestConnections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * Le reste du networking entre participants (D8) : suggestions par centres
 * d'intérêt, rendez-vous, messagerie.
 *
 * @return array{organization: object, event: Event, me: Registration, other: Registration, url: string}
 */
function networkingEvent(bool $messaging = true): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_attendee_directory' => true,
        'has_attendee_messaging' => $messaging,
        'start_at' => CarbonImmutable::parse('2026-11-12 09:00', 'Africa/Kinshasa')->utc(),
        'end_at' => CarbonImmutable::parse('2026-11-12 18:00', 'Africa/Kinshasa')->utc(),
        'timezone' => 'Africa/Kinshasa',
    ]);

    app(CurrentOrganization::class)->set($organization);
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();

    $make = fn (string $first, array $interests): Registration => Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => $first,
        'last_name' => 'Mbuyi',
        'directory_consent_at' => now(),
        'directory_interests' => $interests,
    ]);

    $me = $make('Awa', ['santé', 'formation']);
    $other = $make('Jean', ['formation', 'logistique']);
    app(CurrentOrganization::class)->clear();

    $url = URL::temporarySignedRoute('guest.registration.directory', now()->addDay(), [
        $organization->slug, $event->slug, $me->id,
    ]);

    return ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other, 'url' => $url];
}

it('suggère les participants qui partagent un centre d\'intérêt', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me] = networkingEvent();

    app(CurrentOrganization::class)->set($organization);
    $suggestions = app(SuggestConnections::class)->handle($event, $me);
    app(CurrentOrganization::class)->clear();

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['name'])->toBe('Jean Mbuyi')
        ->and($suggestions[0]['shared'])->toBe(['formation']);
});

it('normalise les centres d\'intérêt écrits à la main', function (): void {
    expect(SuggestConnections::normalize(' Santé , FORMATION, santé , , logistique'))
        ->toBe(['santé', 'formation', 'logistique'])
        ->and(SuggestConnections::normalize(null))->toBe([])
        // Cinq au plus.
        ->and(SuggestConnections::normalize('a, b, c, d, e, f, g'))->toHaveCount(5);
});

it('ne suggère personne hors de l\'annuaire', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = networkingEvent();

    app(CurrentOrganization::class)->set($organization);
    $other->update(['directory_consent_at' => null]);

    expect(app(SuggestConnections::class)->handle($event, $me))->toBe([]);
    app(CurrentOrganization::class)->clear();
});

it('propose un rendez-vous, et l\'autre l\'accepte', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other, 'url' => $url] = networkingEvent();
    $this->get($url);
    $this->post(networkingUrl('meetings.store', $organization, $event, $me), [
        'guest_registration_id' => $other->id,
        'starts_at' => '2026-11-12T14:30',
        'place' => 'Hall d’accueil',
        'message' => 'Dix minutes ?',
    ])->assertRedirect()->assertSessionHas('status', 'meeting-proposed');

    app(CurrentOrganization::class)->set($organization);
    $meeting = AttendeeMeeting::query()->sole();

    expect($meeting->status)->toBe(AttendeeMeetingStatus::Pending)
        // Saisie dans le fuseau de l'événement, stockée en UTC (règle 4.3).
        ->and($meeting->starts_at->toIso8601String())->toBe('2026-11-12T13:30:00+00:00')
        ->and($meeting->place)->toBe('Hall d’accueil');

    $rows = app(PlanAttendeeMeeting::class)->forAttendee($event, $other);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['mine'])->toBeFalse()
        ->and($rows[0]['when'])->toContain('14h30');
    app(CurrentOrganization::class)->clear();

    $otherUrl = URL::temporarySignedRoute('guest.registration.directory', now()->addDay(), [$organization->slug, $event->slug, $other->id]);
    $this->get($otherUrl);
    $this->post(networkingUrl('meetings.answer', $organization, $event, $other, $meeting->id), ['status' => 'accepted'])
        ->assertRedirect()
        ->assertSessionHas('status', 'meeting-answered');

    app(CurrentOrganization::class)->set($organization);
    expect($meeting->refresh()->status)->toBe(AttendeeMeetingStatus::Accepted);
    app(CurrentOrganization::class)->clear();
});

it('refuse un rendez-vous hors du créneau de l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other, 'url' => $url] = networkingEvent();

    $this->get($url);
    $this->post(networkingUrl('meetings.store', $organization, $event, $me), [
        'guest_registration_id' => $other->id,
        'starts_at' => '2026-11-20T14:30',
    ])->assertSessionHas('status', 'meeting-refused');

    app(CurrentOrganization::class)->set($organization);
    expect(AttendeeMeeting::query()->count())->toBe(0);
    app(CurrentOrganization::class)->clear();
});

it('ne laisse pas un tiers répondre à la place de l\'invité', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other, 'url' => $url] = networkingEvent();

    $this->get($url);
    $this->post(networkingUrl('meetings.store', $organization, $event, $me), [
        'guest_registration_id' => $other->id,
        'starts_at' => '2026-11-12T14:30',
    ]);

    app(CurrentOrganization::class)->set($organization);
    $meeting = AttendeeMeeting::query()->sole();
    app(CurrentOrganization::class)->clear();

    // Celui qui propose ne peut pas accepter à la place de l'invité.
    $this->post(networkingUrl('meetings.answer', $organization, $event, $me, $meeting->id), ['status' => 'accepted'])
        ->assertSessionHas('status', 'meeting-refused');

    app(CurrentOrganization::class)->set($organization);
    expect($meeting->refresh()->status)->toBe(AttendeeMeetingStatus::Pending);
    app(CurrentOrganization::class)->clear();
});

it('porte un message d\'un participant à l\'autre, et le marque lu', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other, 'url' => $url] = networkingEvent();

    $this->get($url);
    $this->post(networkingUrl('messages.store', $organization, $event, $me), [
        'recipient_registration_id' => $other->id,
        'body' => 'Ravi de vous avoir rencontré.',
    ])->assertRedirect()->assertSessionHas('status', 'message-sent');

    app(CurrentOrganization::class)->set($organization);
    $threads = app(AttendeeConversations::class)->forAttendee($event, $other);

    expect($threads)->toHaveCount(1)
        ->and($threads[0]['name'])->toBe('Awa Mbuyi')
        ->and($threads[0]['unread'])->toBe(1)
        ->and($threads[0]['messages'][0]['body'])->toBe('Ravi de vous avoir rencontré.')
        ->and($threads[0]['messages'][0]['mine'])->toBeFalse();

    // Le fil ayant été lu, plus rien n'est en attente.
    expect(app(AttendeeConversations::class)->forAttendee($event, $other)[0]['unread'])->toBe(0);
    app(CurrentOrganization::class)->clear();
});

it('garde la messagerie fermée tant que l\'organisateur ne l\'ouvre pas', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other, 'url' => $url] = networkingEvent(messaging: false);

    $this->get($url);
    $this->post(networkingUrl('messages.store', $organization, $event, $me), [
        'recipient_registration_id' => $other->id,
        'body' => 'Bonjour',
    ])->assertSessionHas('status', 'message-refused');

    app(CurrentOrganization::class)->set($organization);
    expect(app(AttendeeConversations::class)->forAttendee($event, $other))->toBe([]);
    app(CurrentOrganization::class)->clear();
});
