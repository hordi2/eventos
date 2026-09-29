<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CancelRegistration;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Actions\UpdateRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\SubEventFullException;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $setup
 * @param  list<int>  $sessionIds
 * @param  list<CompanionData>  $companions
 */
function registerForSessions(array $setup, string $email, array $sessionIds, array $companions = []): Registration
{
    return app(SubmitRegistration::class)->handle(
        $setup['context'],
        $setup['version'],
        new AttendeeIdentity($email, 'Marie', 'Lusala'),
        ['sessions' => array_map(strval(...), $sessionIds)],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
        null,
        $companions,
    )->registration;
}

function heldPlaces(Event $session): int
{
    return (int) CapacityHold::query()
        ->where('holder_type', 'event')
        ->where('holder_id', (string) $session->id)
        ->where('status', CapacityHoldStatus::Held)
        ->sum('quantity');
}

it('inscrit le groupe à chaque session cochée, sur la capacité de la session', function (): void {
    $setup = eventWithSessions();

    $registration = registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id, $setup['workshop']->id], [new CompanionData('Paul')]);

    $sessions = Registration::query()->where('parent_registration_id', $registration->id)->orderBy('event_id')->get();
    expect($sessions->pluck('event_id')->all())->toBe([$setup['dinner']->id, $setup['workshop']->id]);
    expect($sessions[0]->status)->toBe(RegistrationStatus::Confirmed);
    // Deux personnes pour une seule place : le groupe passe en liste d'attente.
    expect($sessions[1]->status)->toBe(RegistrationStatus::Waitlisted);
    expect(Attendee::query()->where('registration_id', $sessions[0]->id)->orderBy('position')->pluck('first_name')->all())->toBe(['Marie', 'Paul']);
    expect(heldPlaces($setup['dinner']))->toBe(2);
});

it('refuse une session complète sans liste d\'attente et n\'enregistre rien', function (): void {
    $setup = eventWithSessions();
    registerForSessions($setup, 'premiere@example.com', [$setup['dinner']->id], [new CompanionData('Paul')]);

    expect(fn () => registerForSessions($setup, 'seconde@example.com', [$setup['dinner']->id]))
        ->toThrow(SubEventFullException::class);
    expect(Registration::query()->where('email', 'seconde@example.com')->exists())->toBeFalse();
});

it('refuse deux sessions qui ont lieu en même temps', function (): void {
    $setup = eventWithSessions();

    try {
        registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id, $setup['cocktail']->id]);
        $this->fail('Le chevauchement aurait dû être refusé.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['sessions'][0])->toContain('ont lieu en même temps');
    }
});

it('annule les sessions avec l\'inscription principale et libère leurs places', function (): void {
    $setup = eventWithSessions();
    $registration = registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id]);

    app(CancelRegistration::class)->handle($registration, new EventEditPolicy(true, null, 'UTC'));

    expect(Registration::query()->where('parent_registration_id', $registration->id)->firstOrFail()->status)->toBe(RegistrationStatus::Cancelled);
    expect(heldPlaces($setup['dinner']))->toBe(0);
});

it('met les sessions à jour quand l\'invité change son choix, y compris en recochant une session', function (): void {
    $setup = eventWithSessions();
    $registration = registerForSessions($setup, 'marie@example.com', [$setup['dinner']->id]);
    $choose = fn (array $sessionIds) => app(UpdateRegistration::class)->handle(
        $registration->fresh(),
        new EventEditPolicy(true, null, 'UTC'),
        new AttendeeIdentity('marie@example.com', 'Marie', 'Lusala'),
        ['sessions' => array_map(strval(...), $sessionIds)],
        null,
        [],
        $setup['context']->subEvents,
    );

    $choose([$setup['workshop']->id]);
    expect(heldPlaces($setup['dinner']))->toBe(0);

    $choose([$setup['dinner']->id, $setup['workshop']->id]);

    $active = Registration::query()
        ->where('parent_registration_id', $registration->id)
        ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Waitlisted->value])
        ->orderBy('event_id')
        ->pluck('event_id')
        ->all();
    expect($active)->toBe([$setup['dinner']->id, $setup['workshop']->id]);
    expect(heldPlaces($setup['dinner']))->toBe(1);
});
