<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CancelRegistration;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\GenerateAttendeeQrToken;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Actions\UpdateRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\EventFullException;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\OptionFullException;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @param  array<string, mixed>  $eventOverrides
 * @return array{organization: Organization, event: Event, version: FormVersion, context: EventRegistrationContext}
 */
function companionRegistrationForm(array $fields, array $eventOverrides = []): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $event = Event::factory()->for($organization)->create($eventOverrides);
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, ['name' => 'Inscription', 'fields' => $fields]);
    app(PublishFormVersion::class)->handle($form, $admin);

    return [
        'organization' => $organization,
        'event' => $event,
        'version' => $form->fresh()->currentVersion,
        'context' => new EventRegistrationContext(
            eventId: $event->id,
            organizationId: $organization->id,
            capacity: $event->capacity,
            allowWaitlist: $event->allow_waitlist,
            registrationOpensAt: null,
            registrationClosesAt: null,
            timezone: $event->timezone,
            registrationClosedMessage: null,
        ),
    ];
}

/**
 * @return array<string, mixed>
 */
function perPersonMealField(): array
{
    return [
        'key' => 'menu',
        'type' => 'meal_choice',
        'label' => 'Choix du menu',
        'is_required' => true,
        'config' => ['ask_scope' => 'each_attendee'],
        'options' => [
            ['value' => 'poisson', 'label' => 'Poisson braisé', 'quota' => 2],
            ['value' => 'poulet', 'label' => 'Poulet mayo'],
        ],
    ];
}

it('compte une place par personne, accompagnants compris', function (): void {
    ['version' => $version, 'context' => $context] = companionRegistrationForm([], ['capacity' => 3, 'allow_waitlist' => false]);
    $submit = app(SubmitRegistration::class);

    $registration = $submit->handle(
        $context,
        $version,
        new AttendeeIdentity('marie@example.com', 'Marie', 'Lusala'),
        [],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
        null,
        [new CompanionData('Paul', 'Kalala'), new CompanionData('Lina')],
    )->registration;

    expect($registration->status)->toBe(RegistrationStatus::Confirmed);
    expect(
        Attendee::query()->where('registration_id', $registration->id)->orderBy('position')->get()
            ->map(fn (Attendee $attendee): array => [$attendee->first_name, $attendee->is_primary, $attendee->position])
            ->all(),
    )->toBe([['Marie', true, 0], ['Paul', false, 1], ['Lina', false, 2]]);
    expect(CapacityHold::query()->where('reservation_key', $registration->reservation_key)->value('quantity'))->toBe(3);

    expect(fn () => $submit->handle($context, $version, new AttendeeIdentity('seul@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid()))
        ->toThrow(EventFullException::class);
});

it('enregistre la réponse de chaque personne et tient un quota d\'option par personne', function (): void {
    ['version' => $version, 'context' => $context] = companionRegistrationForm([perPersonMealField()]);
    $submit = app(SubmitRegistration::class);

    $registration = $submit->handle(
        $context,
        $version,
        new AttendeeIdentity('marie@example.com', 'Marie'),
        ['menu' => 'poisson'],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
        null,
        [new CompanionData('Paul', answers: ['menu' => 'poisson']), new CompanionData('Lina', answers: ['menu' => 'poulet'])],
    )->registration;

    expect($registration->answers()->count())->toBe(1);
    expect(RegistrationAnswer::query()->whereNotNull('attendee_id')->orderBy('attendee_id')->get()->pluck('value')->all())->toBe(['poisson', 'poulet']);

    // Marie et Paul tiennent les deux places « Poisson braisé ».
    expect(fn () => $submit->handle($context, $version, new AttendeeIdentity('autre@example.com'), ['menu' => 'poisson'], new RegistrationSubmissionMetadata, (string) Str::uuid()))
        ->toThrow(OptionFullException::class);
});

it('exige la réponse de chaque accompagnant à une question obligatoire posée à chacun', function (): void {
    ['version' => $version, 'context' => $context] = companionRegistrationForm([perPersonMealField()]);

    try {
        app(SubmitRegistration::class)->handle(
            $context,
            $version,
            new AttendeeIdentity('marie@example.com'),
            ['menu' => 'poulet'],
            new RegistrationSubmissionMetadata,
            (string) Str::uuid(),
            null,
            [new CompanionData('Paul')],
        );
        $this->fail('La validation aurait dû échouer.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('_companions.0.answers.menu');
    }

    expect(Registration::query()->count())->toBe(0);
});

it('libère les places et les quotas de chaque personne et révoque les QR à l\'annulation', function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    ['version' => $version, 'context' => $context, 'event' => $event] = companionRegistrationForm([perPersonMealField()], ['capacity' => 3]);

    $registration = app(SubmitRegistration::class)->handle(
        $context,
        $version,
        new AttendeeIdentity('marie@example.com', 'Marie'),
        ['menu' => 'poisson'],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
        null,
        [new CompanionData('Paul', answers: ['menu' => 'poisson']), new CompanionData('Lina', answers: ['menu' => 'poulet'])],
    )->registration;
    app(GenerateAttendeeQrToken::class)->handle($registration->companions()->firstOrFail(), CarbonImmutable::now()->addDay());

    app(CancelRegistration::class)->handle($registration, new EventEditPolicy(true, null, $event->timezone));

    expect(CapacityHold::query()->where('status', CapacityHoldStatus::Held)->count())->toBe(0);
    expect(Attendee::query()->where('registration_id', $registration->id)->whereNotNull('qr_jti')->count())->toBe(0);
});

it('modifie le nom et la réponse d\'un accompagnant en déplaçant son quota d\'option', function (): void {
    ['version' => $version, 'context' => $context, 'event' => $event] = companionRegistrationForm([perPersonMealField()]);

    $registration = app(SubmitRegistration::class)->handle(
        $context,
        $version,
        new AttendeeIdentity('marie@example.com', 'Marie'),
        ['menu' => 'poulet'],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
        null,
        [new CompanionData('Paul', answers: ['menu' => 'poisson'])],
    )->registration;
    $paul = $registration->companions()->firstOrFail();

    app(UpdateRegistration::class)->handle(
        $registration,
        new EventEditPolicy(true, null, $event->timezone),
        new AttendeeIdentity('marie@example.com', 'Marie'),
        ['menu' => 'poulet'],
        null,
        [new CompanionData('Paulin', 'Kalala', ['menu' => 'poulet'], $paul->id)],
    );

    expect($paul->fresh()->first_name)->toBe('Paulin');
    expect(RegistrationAnswer::query()->where('attendee_id', $paul->id)->firstOrFail()->value)->toBe('poulet');
    expect(CapacityHold::query()->where('holder_type', 'form_field_option')->where('status', CapacityHoldStatus::Held)->count())->toBe(0);
});
