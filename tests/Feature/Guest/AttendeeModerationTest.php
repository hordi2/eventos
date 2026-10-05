<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\AttendeeMessage;
use App\Domain\Form\Models\AttendeeMessageReport;
use App\Domain\Form\Models\AttendeeReportStatus;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Networking\AttendeeConversations;
use App\Support\Networking\SuggestConnections;
use Illuminate\Support\Facades\URL;

/**
 * Modération de la messagerie entre participants (D8) : blocage par
 * l'intéressé, signalement, décision de l'organisateur.
 *
 * @return array{organization: object, event: object, me: Registration, other: Registration, url: string}
 */
function moderationEvent(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_attendee_directory' => true,
        'has_attendee_messaging' => true,
    ]);

    app(CurrentOrganization::class)->set($organization);
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();

    $make = fn (string $first): Registration => Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => $first,
        'last_name' => 'Mbuyi',
        'directory_consent_at' => now(),
        'directory_interests' => ['formation'],
    ]);

    $me = $make('Awa');
    $other = $make('Jean');
    app(CurrentOrganization::class)->clear();

    $url = URL::temporarySignedRoute('guest.registration.directory', now()->addDay(), [
        $organization->slug, $event->slug, $me->id,
    ]);

    return ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other, 'url' => $url];
}

it('fait taire qui a été bloqué, dans les deux sens', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = moderationEvent();

    $this->post(networkingUrl('messages.block', $organization, $event, $me), ['registration_id' => $other->id])
        ->assertSessionHas('status', 'blocked');

    app(CurrentOrganization::class)->set($organization);
    $conversations = app(AttendeeConversations::class);

    // Ni dans un sens…
    expect($conversations->send($event, $other->refresh(), $me->id, 'Bonjour ?'))->toBeNull()
        // …ni dans l'autre.
        ->and($conversations->send($event, $me->refresh(), $other->id, 'Bonjour ?'))->toBeNull()
        // La personne bloquée disparaît aussi des suggestions.
        ->and(app(SuggestConnections::class)->handle($event, $me))->toBe([]);
    app(CurrentOrganization::class)->clear();
});

it('rend la parole à qui a été débloqué', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = moderationEvent();

    $this->post(networkingUrl('messages.block', $organization, $event, $me), ['registration_id' => $other->id]);
    $this->post(networkingUrl('messages.block', $organization, $event, $me), ['registration_id' => $other->id, 'unblock' => 1])
        ->assertSessionHas('status', 'unblocked');

    app(CurrentOrganization::class)->set($organization);
    expect(app(AttendeeConversations::class)->send($event, $other->refresh(), $me->id, 'Bonjour'))->not->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('signale à l\'organisateur le seul message reçu, et une seule fois', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = moderationEvent();

    app(CurrentOrganization::class)->set($organization);
    $message = app(AttendeeConversations::class)->send($event, $other, $me->id, 'Propos déplacés.');
    app(CurrentOrganization::class)->clear();

    $report = networkingUrl('messages.report', $organization, $event, $me);
    $this->post($report, ['message_id' => $message->id, 'reason' => 'Insultes'])->assertSessionHas('status', 'reported');
    $this->post($report, ['message_id' => $message->id, 'reason' => 'Insultes'])->assertSessionHas('status', 'reported');

    app(CurrentOrganization::class)->set($organization);
    expect(AttendeeMessageReport::query()->count())->toBe(1)
        ->and(AttendeeMessageReport::query()->sole()->reason)->toBe('Insultes');
    app(CurrentOrganization::class)->clear();
});

it('refuse de signaler un message qu\'on n\'a pas reçu', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = moderationEvent();

    app(CurrentOrganization::class)->set($organization);
    // Message envoyé par moi : je ne peux pas me signaler moi-même.
    $mine = app(AttendeeConversations::class)->send($event, $me, $other->id, 'Bonjour');
    app(CurrentOrganization::class)->clear();

    $this->post(networkingUrl('messages.report', $organization, $event, $me), ['message_id' => $mine->id])
        ->assertSessionHas('status', 'message-refused');

    app(CurrentOrganization::class)->set($organization);
    expect(AttendeeMessageReport::query()->count())->toBe(0);
    app(CurrentOrganization::class)->clear();
});

it('laisse l\'organisateur retirer un message signalé, et le journalise', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = moderationEvent();

    app(CurrentOrganization::class)->set($organization);
    $message = app(AttendeeConversations::class)->send($event, $other, $me->id, 'Propos déplacés.');
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    $this->post(networkingUrl('messages.report', $organization, $event, $me), ['message_id' => $message->id]);

    app(CurrentOrganization::class)->set($organization);
    $report = AttendeeMessageReport::query()->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $organization->id])
        ->patch("/events/{$event->id}/moderation/{$report->id}", ['decision' => 'remove'])
        ->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    expect($report->refresh()->status)->toBe(AttendeeReportStatus::Handled)
        ->and(AttendeeMessage::query()->whereKey($message->id)->sole()->removed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'attendee_message.removed')->exists())->toBeTrue();

    // Le texte ne se lit plus, la place reste.
    $threads = app(AttendeeConversations::class)->forAttendee($event, $me->refresh());
    expect($threads[0]['messages'][0]['body'])->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('laisse l\'organisateur suspendre l\'auteur, qui garde l\'annuaire', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = moderationEvent();

    app(CurrentOrganization::class)->set($organization);
    $message = app(AttendeeConversations::class)->send($event, $other, $me->id, 'Propos déplacés.');
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    $this->post(networkingUrl('messages.report', $organization, $event, $me), ['message_id' => $message->id]);

    app(CurrentOrganization::class)->set($organization);
    $report = AttendeeMessageReport::query()->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $organization->id])
        ->patch("/events/{$event->id}/moderation/{$report->id}", ['decision' => 'suspend'])
        ->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    expect($other->refresh()->messaging_suspended_at)->not->toBeNull()
        // Plus un mot ne part de lui…
        ->and(app(AttendeeConversations::class)->send($event, $other, $me->id, 'Encore ?'))->toBeNull()
        // …mais il reste à l'annuaire.
        ->and($other->directory_consent_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'attendee_messaging.suspended')->exists())->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('réserve la modération à qui peut l\'exercer', function (): void {
    ['organization' => $organization, 'event' => $event] = moderationEvent();

    app(CurrentOrganization::class)->set($organization);
    $editor = User::factory()->create();
    $editor->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Editor]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($editor)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/events/{$event->id}/moderation")
        ->assertForbidden();
});

it('n\'ouvre « Modération » au menu que lorsqu\'un signalement attend', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me, 'other' => $other] = moderationEvent();

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    $session = ['current_organization_id' => $organization->id];

    // Rien à modérer : pas de lien.
    $this->actingAs($admin)->withSession($session)->get("/events/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('eventNav.links.moderation', null));

    app(CurrentOrganization::class)->set($organization);
    $message = app(AttendeeConversations::class)->send($event, $other, $me->id, 'Propos déplacés.');
    app(CurrentOrganization::class)->clear();

    $this->post(networkingUrl('messages.report', $organization, $event, $me), ['message_id' => $message->id]);

    $this->actingAs($admin)->withSession($session)->get("/events/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('eventNav.links.moderation', route('events.moderation.index', $event->id)));
});
