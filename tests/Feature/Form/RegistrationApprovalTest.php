<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Order;
use App\Mail\RegistrationDecisionMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Événement en validation manuelle, prêt pour les invités, avec un
 * administrateur qui décide.
 *
 * @param  array<int, array<string, mixed>>  $fields
 * @return array{organization: Organization, event: Event, admin: User, base: string}
 */
function makeApprovalEvent(array $fields = [], array $eventOverrides = []): array
{
    // Type professionnel : un événement personnel exigerait aussi le téléphone.
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent($fields, [
        'type' => EventType::Conference,
        'requires_approval' => true,
        ...$eventOverrides,
    ]);

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'base' => "/r/{$organization->slug}/{$event->slug}"];
}

/**
 * Parcours invité complet jusqu'à la soumission ; renvoie l'inscription.
 *
 * @param  array<string, mixed>  $answers
 */
function answerAsGuest(TestCase $test, Event $event, string $base, string $email, array $answers = []): Registration
{
    $test->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;

    $test->post("{$base}/{$token}/identite", ['email' => $email, 'first_name' => 'Awa', 'last_name' => 'Diallo']);
    $test->post("{$base}/{$token}/reponses", $answers)->assertSessionHasNoErrors();
    $test->post("{$base}/{$token}/recap");

    return Registration::withoutGlobalScopes()->where('event_id', $event->id)->where('email', $email)->sole();
}

it('retient la place de l\'invité et attend la validation de l\'organisateur', function (): void {
    ['event' => $event, 'base' => $base, 'organization' => $organization] = makeApprovalEvent();
    app(CurrentOrganization::class)->set($organization);
    $event->update(['capacity' => 1, 'allow_waitlist' => true]);
    app(CurrentOrganization::class)->clear();

    $registration = answerAsGuest($this, $event, $base, 'awa@example.com');
    expect($registration->status)->toBe(RegistrationStatus::Pending);

    $token = RegistrationDraft::withoutGlobalScopes()->where('registration_id', $registration->id)->sole()->resume_token;
    $this->get("{$base}/{$token}/confirmation")
        ->assertOk()
        ->assertSee('Votre demande est bien arrivée')
        // Pas de QR tant que la place n'est pas accordée.
        ->assertDontSee("QR code d'entrée");

    // La place est bien retenue : le suivant passe en liste d'attente.
    $this->flushSession();
    expect(answerAsGuest($this, $event, $base, 'moussa@example.com')->status)->toBe(RegistrationStatus::Waitlisted);
});

it('accepte une demande : l\'invité est confirmé, prévenu, et son don s\'ouvre', function (): void {
    Mail::fake();
    ['event' => $event, 'base' => $base, 'admin' => $admin, 'organization' => $organization] = makeApprovalEvent([
        ['key' => 'don', 'type' => 'donation', 'label' => 'Votre don', 'config' => ['currency' => 'EUR', 'amounts' => [5000]]],
    ]);

    $registration = answerAsGuest($this, $event, $base, 'awa@example.com', ['don' => ['choice' => '5000']]);
    app(CurrentOrganization::class)->set($organization);
    expect(Order::query()->where('registration_id', $registration->id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/registrations/{$registration->id}/approve")
        ->assertRedirect()
        ->assertSessionHas('status', 'registration-approved');

    app(CurrentOrganization::class)->set($organization);
    expect($registration->fresh()->status)->toBe(RegistrationStatus::Confirmed)
        // Le don promis n'est réglable qu'une fois la place acquise.
        ->and(Order::query()->where('registration_id', $registration->id)->exists())->toBeTrue();
    app(CurrentOrganization::class)->clear();

    Mail::assertQueued(RegistrationDecisionMail::class, fn (RegistrationDecisionMail $mail): bool => $mail->approved && $mail->eventTitle === $event->title);
});

it('refuse une demande : la place est rendue, la liste d\'attente avance et l\'invité est prévenu', function (): void {
    Mail::fake();
    ['event' => $event, 'base' => $base, 'admin' => $admin, 'organization' => $organization] = makeApprovalEvent();
    app(CurrentOrganization::class)->set($organization);
    $event->update(['capacity' => 1, 'allow_waitlist' => true]);
    app(CurrentOrganization::class)->clear();

    $first = answerAsGuest($this, $event, $base, 'awa@example.com');
    $this->flushSession();
    $second = answerAsGuest($this, $event, $base, 'moussa@example.com');
    expect($second->status)->toBe(RegistrationStatus::Waitlisted);

    $this->actingAs($admin)->post("/registrations/{$first->id}/reject", ['reason' => 'Salle réservée aux membres.'])
        ->assertSessionHas('status', 'registration-rejected');

    app(CurrentOrganization::class)->set($organization);
    expect($first->fresh()->status)->toBe(RegistrationStatus::Rejected)
        ->and($first->fresh()->cancellation_reason)->toBe('Salle réservée aux membres.')
        // La place libérée fait avancer la liste d'attente, sans confirmer personne sans accord.
        ->and($second->fresh()->status)->toBe(RegistrationStatus::Pending);
    app(CurrentOrganization::class)->clear();

    Mail::assertQueued(RegistrationDecisionMail::class, fn (RegistrationDecisionMail $mail): bool => ! $mail->approved && $mail->reason === 'Salle réservée aux membres.');
});

it('refuse une deuxième décision sur la même demande', function (): void {
    Mail::fake();
    ['event' => $event, 'base' => $base, 'admin' => $admin] = makeApprovalEvent();
    $registration = answerAsGuest($this, $event, $base, 'awa@example.com');

    $this->actingAs($admin)->post("/registrations/{$registration->id}/approve")->assertSessionHasNoErrors();
    $this->actingAs($admin)->post("/registrations/{$registration->id}/approve")->assertSessionHasErrors('decision');
});

it('liste les demandes à valider et réserve la décision aux membres qui peuvent modifier les invités', function (): void {
    ['event' => $event, 'base' => $base, 'organization' => $organization] = makeApprovalEvent();
    $registration = answerAsGuest($this, $event, $base, 'awa@example.com');

    app(CurrentOrganization::class)->set($organization);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->get("/events/{$event->id}/validations")->assertInertia(fn ($page) => $page
        ->component('Events/Approvals')
        ->has('registrations', 1)
        ->where('registrations.0.name', 'Awa Diallo')
        ->where('registrations.0.people', 1)
        ->where('canDecide', false));

    $this->actingAs($viewer)->post("/registrations/{$registration->id}/approve")->assertForbidden();
});

it('fait attendre aussi les sessions choisies, puis les confirme avec l\'inscription', function (): void {
    $setup = eventWithSessions();
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $setup['organization']->id, 'role' => MembershipRole::Admin]);

    $context = new EventRegistrationContext(
        eventId: $setup['context']->eventId,
        organizationId: $setup['context']->organizationId,
        capacity: null,
        allowWaitlist: false,
        registrationOpensAt: null,
        registrationClosesAt: null,
        timezone: 'UTC',
        registrationClosedMessage: null,
        subEvents: $setup['context']->subEvents,
        requiresApproval: true,
    );

    $registration = app(SubmitRegistration::class)->handle(
        $context,
        $setup['version'],
        new AttendeeIdentity('awa@example.com', 'Awa', 'Diallo'),
        ['sessions' => [(string) $setup['dinner']->id]],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
    )->registration;

    $session = Registration::query()->where('parent_registration_id', $registration->id)->sole();
    expect($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($session->status)->toBe(RegistrationStatus::Pending);

    $this->actingAs($admin)->post("/registrations/{$registration->id}/approve")->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($setup['organization']);
    expect($registration->fresh()->status)->toBe(RegistrationStatus::Confirmed)
        ->and($session->fresh()->status)->toBe(RegistrationStatus::Confirmed);
});
