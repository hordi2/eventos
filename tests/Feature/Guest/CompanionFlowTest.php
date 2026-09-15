<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @param  array<string, mixed>  $rsvp
 * @param  array<string, mixed>  $eventOverrides
 * @return array{organization: Organization, event: Event, base: string}
 */
function companionGuestEvent(array $fields, array $rsvp, array $eventOverrides = []): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent($fields, ['type' => EventType::Conference, ...$eventOverrides]);

    app(CurrentOrganization::class)->set($organization);
    Form::query()->where('event_id', $event->id)->firstOrFail()->update(['settings' => ['rsvp' => $rsvp]]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'base' => "/r/{$organization->slug}/{$event->slug}"];
}

function companionDraftToken(Event $event): string
{
    return RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
}

/**
 * @return array<string, mixed>
 */
function companionMenuField(): array
{
    return [
        'key' => 'menu',
        'type' => 'meal_choice',
        'label' => 'Choix du menu',
        'is_required' => true,
        'config' => ['ask_scope' => 'each_attendee'],
        'options' => [
            ['value' => 'poisson', 'label' => 'Poisson braisé'],
            ['value' => 'poulet', 'label' => 'Poulet mayo'],
        ],
    ];
}

it('inscrit un invité avec son accompagnant, leurs réponses et un QR chacun', function (): void {
    ['event' => $event, 'base' => $base] = companionGuestEvent(
        [companionMenuField(), ['key' => 'mot', 'type' => 'short_text', 'label' => 'Un mot pour les hôtes']],
        ['max_companions' => 2],
    );

    $this->get("{$base}/commencer");
    $token = companionDraftToken($event);

    $this->get("{$base}/{$token}/identite")
        ->assertSee('Vos accompagnants')
        ->assertSee('name="_companions[1][first_name]"', false);

    // La seconde ligne, laissée vide, n'est pas un accompagnant.
    $this->post("{$base}/{$token}/identite", [
        'email' => 'marie@example.com',
        'first_name' => 'Marie',
        'last_name' => 'Lusala',
        '_companions' => [['first_name' => 'Paul', 'last_name' => 'Kalala'], ['first_name' => '', 'last_name' => '']],
    ])->assertRedirect("{$base}/{$token}/reponses");

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('Pour Paul Kalala')
        ->assertSee('name="_companions[0][answers][menu]"', false)
        ->assertDontSee('name="_companions[0][answers][mot]"', false);

    $this->post("{$base}/{$token}/reponses", ['menu' => 'poisson', '_companions' => [['answers' => []]]])
        ->assertSessionHasErrors('_companions.0.answers.menu');

    $this->post("{$base}/{$token}/reponses", ['menu' => 'poisson', 'mot' => 'Merci', '_companions' => [['answers' => ['menu' => 'poulet']]]])
        ->assertRedirect("{$base}/{$token}/recap");

    $this->get("{$base}/{$token}/recap")
        ->assertSee('Vos accompagnants')
        ->assertSee('Paul Kalala')
        ->assertSee('Poulet mayo');

    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    $registration = Registration::withoutGlobalScopes()->where('email', 'marie@example.com')->firstOrFail();
    $attendees = Attendee::withoutGlobalScopes()->where('registration_id', $registration->id)->orderBy('position')->get();
    expect($attendees->pluck('first_name')->all())->toBe(['Marie', 'Paul']);
    expect(RegistrationAnswer::withoutGlobalScopes()->where('attendee_id', $attendees[1]->id)->firstOrFail()->value)->toBe('poulet');

    $confirmation = $this->get("{$base}/{$token}/confirmation")
        ->assertOk()
        ->assertSee("Vos QR codes d'entrée");
    expect(substr_count((string) $confirmation->getContent(), 'data:image/png;base64,'))->toBe(2);
});

it('refuse plus d\'accompagnants que le formulaire n\'en autorise', function (): void {
    ['event' => $event, 'base' => $base] = companionGuestEvent([], ['max_companions' => 1]);

    $this->get("{$base}/commencer");
    $token = companionDraftToken($event);

    $this->post("{$base}/{$token}/identite", [
        'email' => 'marie@example.com',
        '_companions' => [['first_name' => 'Paul'], ['first_name' => 'Lina']],
    ])->assertSessionHasErrors('_companions');
});

it('n\'inscrit aucun accompagnant pour un invité qui ne peut pas venir', function (): void {
    ['event' => $event, 'base' => $base] = companionGuestEvent([], ['max_companions' => 2, 'decline_enabled' => true]);

    $this->get("{$base}/commencer");
    $token = companionDraftToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'absent@example.com', 'attending' => '0', '_companions' => [['first_name' => 'Paul']]]);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    $registration = Registration::withoutGlobalScopes()->where('email', 'absent@example.com')->firstOrFail();
    expect($registration->status)->toBe(RegistrationStatus::Declined);
    expect(Attendee::withoutGlobalScopes()->where('registration_id', $registration->id)->count())->toBe(1);
});

it('modifie le nom et la réponse d\'un accompagnant depuis le lien de modification', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base] = companionGuestEvent([companionMenuField()], ['max_companions' => 2], ['allow_guest_edit' => true]);

    $this->get("{$base}/commencer");
    $token = companionDraftToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'marie@example.com', 'first_name' => 'Marie', '_companions' => [['first_name' => 'Paul']]]);
    $this->post("{$base}/{$token}/reponses", ['menu' => 'poulet', '_companions' => [['answers' => ['menu' => 'poulet']]]]);
    $this->post("{$base}/{$token}/recap");

    $registration = Registration::withoutGlobalScopes()->where('email', 'marie@example.com')->firstOrFail();
    $url = URL::temporarySignedRoute('guest.registration.edit', now()->addDay(), [$organization->slug, $event->slug, $registration->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Accompagnant 1')
        ->assertSee('value="Paul"', false);

    $this->post($url, [
        'email' => 'marie@example.com',
        'first_name' => 'Marie',
        'menu' => 'poulet',
        '_companions' => [['first_name' => 'Paulin', 'last_name' => 'Kalala', 'answers' => ['menu' => 'poisson']]],
    ])->assertOk();

    $companion = Attendee::withoutGlobalScopes()->where('registration_id', $registration->id)->where('is_primary', false)->firstOrFail();
    expect($companion->first_name)->toBe('Paulin');
    expect(RegistrationAnswer::withoutGlobalScopes()->where('attendee_id', $companion->id)->firstOrFail()->value)->toBe('poisson');
});
