<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Actions\WriteConditionalRules;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Data\SubmitRegistrationOutcome;
use App\Domain\Form\EventFullException;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\OptionFullException;
use App\Domain\Form\RegistrationClosedException;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @return array{organization: Organization, event: Event, formVersion: FormVersion}
 */
function makePublishedRegistrationForm(array $fields, array $eventOverrides = []): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $event = Event::factory()->for($organization)->create($eventOverrides);

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => $fields,
    ]);

    app(PublishFormVersion::class)->handle($form, $admin);

    return ['organization' => $organization, 'event' => $event, 'formVersion' => $form->fresh()->currentVersion];
}

function registrationContextFor(Organization $organization, Event $event): EventRegistrationContext
{
    return new EventRegistrationContext(
        eventId: $event->id,
        organizationId: $organization->id,
        capacity: $event->capacity,
        allowWaitlist: $event->allow_waitlist,
        registrationOpensAt: $event->registration_opens_at,
        registrationClosesAt: $event->registration_closes_at,
        timezone: $event->timezone,
        registrationClosedMessage: $event->registration_closed_message,
    );
}

it('confirme une inscription simple et crée l\'attendee et les réponses', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([
        ['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom', 'is_required' => true],
        ['key' => 'diner', 'type' => 'yes_no', 'label' => 'Présent au dîner ?'],
    ]);

    $result = app(SubmitRegistration::class)->handle(
        registrationContextFor($organization, $event),
        $version,
        new AttendeeIdentity(email: 'Marie@Example.com', firstName: 'Marie', lastName: 'Lusala'),
        ['nom' => 'Marie Lusala', 'diner' => '1'],
        new RegistrationSubmissionMetadata(source: 'direct', locale: 'fr'),
        (string) Str::uuid(),
    );

    expect($result->outcome)->toBe(SubmitRegistrationOutcome::Created);
    expect($result->registration->status)->toBe(RegistrationStatus::Confirmed);
    expect($result->registration->email)->toBe('marie@example.com');

    $attendee = Attendee::query()->where('registration_id', $result->registration->id)->first();
    expect($attendee->first_name)->toBe('Marie');
    expect($attendee->is_primary)->toBeTrue();

    $answers = RegistrationAnswer::query()->where('registration_id', $result->registration->id)->get();
    expect($answers)->toHaveCount(2);
});

it('bascule sur liste d\'attente quand la capacité de l\'événement est atteinte', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm(
        [['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom']],
        ['capacity' => 1, 'allow_waitlist' => true],
    );

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);

    $action->handle($context, $version, new AttendeeIdentity('a@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid());
    $second = $action->handle($context, $version, new AttendeeIdentity('b@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid());

    expect($second->registration->status)->toBe(RegistrationStatus::Waitlisted);
});

it('refuse l\'inscription quand la capacité est atteinte sans liste d\'attente', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm(
        [],
        ['capacity' => 1, 'allow_waitlist' => false],
    );

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);

    $action->handle($context, $version, new AttendeeIdentity('a@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid());

    expect(fn () => $action->handle($context, $version, new AttendeeIdentity('b@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid()))
        ->toThrow(EventFullException::class);
});

it('refuse une inscription hors période avec le message personnalisé de l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm(
        [],
        [
            'registration_opens_at' => CarbonImmutable::now()->addDay(),
            'registration_closed_message' => 'Les inscriptions ouvrent bientôt !',
        ],
    );

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);

    expect(fn () => $action->handle($context, $version, new AttendeeIdentity('a@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid()))
        ->toThrow(RegistrationClosedException::class, 'Les inscriptions ouvrent bientôt !');
});

it('détecte un doublon par e-mail et propose l\'inscription existante sans en créer une nouvelle', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([]);

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);

    $first = $action->handle($context, $version, new AttendeeIdentity('a@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid());
    $second = $action->handle($context, $version, new AttendeeIdentity('A@Example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid());

    expect($second->outcome)->toBe(SubmitRegistrationOutcome::DuplicateFound);
    expect($second->registration->id)->toBe($first->registration->id);
    expect(Registration::query()->count())->toBe(1);
});

it('est idempotent : rejouer la même clé de réservation ne crée pas de doublon', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([]);

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);
    $key = (string) Str::uuid();

    $first = $action->handle($context, $version, new AttendeeIdentity('a@example.com'), [], new RegistrationSubmissionMetadata, $key);
    $replay = $action->handle($context, $version, new AttendeeIdentity('a@example.com'), [], new RegistrationSubmissionMetadata, $key);

    expect($replay->registration->id)->toBe($first->registration->id);
    expect(Registration::query()->count())->toBe(1);
});

it('valide les réponses soumises et refuse un champ obligatoire manquant', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([
        ['key' => 'nom', 'type' => 'short_text', 'label' => 'Nom', 'is_required' => true],
    ]);

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);

    expect(fn () => $action->handle($context, $version, new AttendeeIdentity('a@example.com'), [], new RegistrationSubmissionMetadata, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
});

it('refuse toute réponse soumise pour un champ masqué par la logique conditionnelle', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([
        ['key' => 'diner', 'type' => 'yes_no', 'label' => 'Présent au dîner ?'],
        ['key' => 'menu', 'type' => 'short_text', 'label' => 'Menu'],
    ]);

    app(WriteConditionalRules::class)->handle($version, [
        [
            'target_field_key' => 'menu',
            'action' => 'show',
            'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'diner', 'operator' => 'is', 'value' => '1']]],
        ],
    ]);

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);

    // Le champ "menu" est masqué (diner = non) : une valeur quand même
    // soumise pour lui est rejetée d'entrée par la validation (T-022 : un
    // champ masqué est marqué "prohibited", jamais simplement facultatif),
    // avant même d'atteindre la logique d'enregistrement des réponses.
    expect(fn () => $action->handle($context, $version, new AttendeeIdentity('a@example.com'), ['diner' => '0', 'menu' => 'Poisson'], new RegistrationSubmissionMetadata, (string) Str::uuid()))
        ->toThrow(ValidationException::class);

    // Un client bien formé omet simplement le champ masqué : l'inscription
    // passe, et aucune réponse n'est enregistrée pour "menu".
    $result = $action->handle($context, $version, new AttendeeIdentity('a@example.com'), ['diner' => '0'], new RegistrationSubmissionMetadata, (string) Str::uuid());

    $answers = RegistrationAnswer::query()->where('registration_id', $result->registration->id)->get();
    expect($answers)->toHaveCount(1);
    expect($answers->first()->formField->key)->toBe('diner');
});

it('applique le quota d\'une option et refuse l\'inscription quand elle est complète', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([
        [
            'key' => 'menu', 'type' => 'single_choice', 'label' => 'Menu', 'is_required' => true,
            'options' => [['value' => 'vegetarien', 'label' => 'Végétarien', 'quota' => 1]],
        ],
    ]);

    $action = app(SubmitRegistration::class);
    $context = registrationContextFor($organization, $event);

    $action->handle($context, $version, new AttendeeIdentity('a@example.com'), ['menu' => 'vegetarien'], new RegistrationSubmissionMetadata, (string) Str::uuid());

    expect(fn () => $action->handle($context, $version, new AttendeeIdentity('b@example.com'), ['menu' => 'vegetarien'], new RegistrationSubmissionMetadata, (string) Str::uuid()))
        ->toThrow(OptionFullException::class);
});

it('capture la source, l\'UTM, le référent, l\'IP, le user-agent et la locale', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([]);

    $result = app(SubmitRegistration::class)->handle(
        registrationContextFor($organization, $event),
        $version,
        new AttendeeIdentity('a@example.com'),
        [],
        new RegistrationSubmissionMetadata(
            source: 'email_campaign',
            utm: ['utm_source' => 'newsletter', 'utm_campaign' => 'rentree'],
            referrer: 'https://exemple.org',
            ipAddress: '41.243.0.1',
            userAgent: 'Mozilla/5.0',
            locale: 'fr-CD',
        ),
        (string) Str::uuid(),
    );

    expect($result->registration->source)->toBe('email_campaign');
    expect($result->registration->utm)->toBe(['utm_source' => 'newsletter', 'utm_campaign' => 'rentree']);
    expect($result->registration->referrer)->toBe('https://exemple.org');
    expect($result->registration->ip_address)->toBe('41.243.0.1');
    expect($result->registration->locale)->toBe('fr-CD');
});

it('émet RegistrationCreated après la création d\'une inscription', function (): void {
    EventFacade::fake([RegistrationCreated::class]);

    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([]);

    $result = app(SubmitRegistration::class)->handle(
        registrationContextFor($organization, $event),
        $version,
        new AttendeeIdentity('a@example.com'),
        [],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
    );

    EventFacade::assertDispatched(RegistrationCreated::class, fn (RegistrationCreated $e): bool => $e->registration->id === $result->registration->id);
});

it('réserve effectivement la capacité de l\'événement dans le moteur générique', function (): void {
    ['organization' => $organization, 'event' => $event, 'formVersion' => $version] = makePublishedRegistrationForm([], ['capacity' => 10]);

    app(SubmitRegistration::class)->handle(
        registrationContextFor($organization, $event),
        $version,
        new AttendeeIdentity('a@example.com'),
        [],
        new RegistrationSubmissionMetadata,
        (string) Str::uuid(),
    );

    expect(CapacityHold::query()->where('holder_type', 'event')->where('holder_id', (string) $event->id)->count())->toBe(1);
});
