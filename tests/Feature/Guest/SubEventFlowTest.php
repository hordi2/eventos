<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Actions\GenerateAttendeeQrToken;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * Événement publié dont le formulaire propose deux sessions qui se
 * chevauchent : un dîner de 10 places et un cocktail.
 *
 * @return array{organization: Organization, event: Event, dinner: Event, cocktail: Event, doorStaff: User, base: string}
 */
function guestEventWithSessions(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['type' => EventType::Conference]);

    app(CurrentOrganization::class)->set($organization);
    $start = CarbonImmutable::now()->addWeek()->setTime(18, 0);
    $dinner = Event::factory()->for($organization)->create(['parent_event_id' => $event->id, 'title' => 'Dîner de gala', 'start_at' => $start, 'end_at' => $start->addHours(3), 'capacity' => 10, 'timezone' => 'UTC']);
    $cocktail = Event::factory()->for($organization)->create(['parent_event_id' => $event->id, 'title' => 'Cocktail', 'start_at' => $start->addHour(), 'end_at' => $start->addHours(2), 'timezone' => 'UTC']);

    $admin = User::factory()->create();
    Membership::factory()->for($organization)->for($admin)->create(['role' => MembershipRole::Admin]);
    $doorStaff = User::factory()->create();
    Membership::factory()->for($organization)->for($doorStaff)->create(['role' => MembershipRole::DoorStaff]);

    $form = Form::query()->where('event_id', $event->id)->firstOrFail();
    app(ReviseForm::class)->handle($form, $admin, [[
        'key' => 'sessions',
        'type' => 'sub_events',
        'label' => 'Vos sessions',
        'config' => ['sub_events' => [['id' => $dinner->id, 'title' => 'Dîner de gala'], ['id' => $cocktail->id, 'title' => 'Cocktail']]],
    ]]);
    app(PublishFormVersion::class)->handle($form->fresh(), $admin);
    app(CurrentOrganization::class)->clear();

    return [
        'organization' => $organization,
        'event' => $event,
        'dinner' => $dinner,
        'cocktail' => $cocktail,
        'doorStaff' => $doorStaff,
        'base' => "/r/{$organization->slug}/{$event->slug}",
    ];
}

function sessionDraftToken(Event $event): string
{
    return RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
}

it('propose les sessions avec leurs places et refuse deux sessions simultanées', function (): void {
    ['event' => $event, 'dinner' => $dinner, 'cocktail' => $cocktail, 'base' => $base] = guestEventWithSessions();

    $this->get("{$base}/commencer");
    $token = sessionDraftToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'marie@example.com']);

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('Dîner de gala')
        ->assertSee('10 places restantes')
        ->assertSee('name="sessions[]"', false);

    $this->post("{$base}/{$token}/reponses", ['sessions' => [(string) $dinner->id, (string) $cocktail->id]])
        ->assertSessionHasErrors('sessions');
});

it('inscrit l\'invité à la session choisie et reconnaît son QR à l\'accueil de la session', function (): void {
    ['organization' => $organization, 'event' => $event, 'dinner' => $dinner, 'doorStaff' => $doorStaff, 'base' => $base] = guestEventWithSessions();

    $this->get("{$base}/commencer");
    $token = sessionDraftToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'marie@example.com', 'first_name' => 'Marie', 'last_name' => 'Lusala']);
    $this->post("{$base}/{$token}/reponses", ['sessions' => [(string) $dinner->id]])->assertRedirect("{$base}/{$token}/recap");
    $this->get("{$base}/{$token}/recap")->assertSee('Dîner de gala');
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    $registration = Registration::withoutGlobalScopes()->where('email', 'marie@example.com')->whereNull('parent_registration_id')->firstOrFail();
    $session = Registration::withoutGlobalScopes()->where('parent_registration_id', $registration->id)->firstOrFail();
    expect($session->event_id)->toBe($dinner->id);
    expect($session->status)->toBe(RegistrationStatus::Confirmed);

    app(CurrentOrganization::class)->set($organization);
    $holder = Attendee::query()->where('registration_id', $registration->id)->where('is_primary', true)->firstOrFail();
    $qrToken = app(GenerateAttendeeQrToken::class)->handle($holder, CarbonImmutable::now()->addDay());
    app(CurrentOrganization::class)->clear();

    $this->actingAs($doorStaff)->postJson("/events/{$dinner->id}/check-in/scan", ['token' => $qrToken])
        ->assertOk()
        ->assertJsonPath('status', 'accepted')
        ->assertJsonPath('guest.name', 'Marie Lusala');

    $sessionAttendee = Attendee::withoutGlobalScopes()->where('registration_id', $session->id)->firstOrFail();
    expect(CheckIn::withoutGlobalScopes()->where('event_id', $dinner->id)->value('attendee_id'))->toBe($sessionAttendee->id);
});
