<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\Data\SubmitRegistrationResult;
use App\Domain\Form\EventFullException;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\OptionFullException;
use App\Domain\Form\RegistrationClosedException;
use App\Domain\Form\Support\AskScope;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Form\Support\IsRegistrationWindowOpen;
use App\Domain\Form\Support\OptionReservationKey;
use App\Domain\Form\Support\ValidateCompanions;
use App\Domain\Form\Support\ValidateRegistrationFiles;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationOutcome;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Le point d'entrée unique d'une soumission d'inscription (M2.4, UC-05).
 * Ne référence jamais un modèle de Domain/Event : le contexte événement
 * arrive déjà résolu en valeurs simples (EventRegistrationContext), à
 * charge de l'appelant (T-031, ou un test) de le construire depuis un vrai
 * Event — voir la section 3 du CLAUDE.md.
 */
final class SubmitRegistration
{
    public function __construct(
        private readonly EvaluateFormVisibility $evaluateFormVisibility,
        private readonly BuildFormValidationRules $buildFormValidationRules,
        private readonly NormalizeFieldAnswer $normalizeFieldAnswer,
        private readonly ReserveCapacity $reserveCapacity,
        private readonly IsRegistrationWindowOpen $isRegistrationWindowOpen,
        private readonly ValidateCompanions $validateCompanions,
        private readonly SyncSubEventRegistrations $syncSubEventRegistrations,
        private readonly ValidateRegistrationFiles $validateRegistrationFiles,
        private readonly SyncRegistrationFiles $syncRegistrationFiles,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  FormVisibilityContext|null  $visibilityContext  réponse de l'invité (vient ou non) et tags de son contact
     * @param  list<CompanionData>  $companions  personnes qui accompagnent le titulaire (T-032)
     */
    public function handle(
        EventRegistrationContext $context,
        FormVersion $formVersion,
        AttendeeIdentity $identity,
        array $answers,
        RegistrationSubmissionMetadata $metadata,
        string $idempotencyKey,
        ?FormVisibilityContext $visibilityContext = null,
        array $companions = [],
    ): SubmitRegistrationResult {
        app(CurrentOrganization::class)->set($context->organizationId);
        $formVersion->loadMissing(['fields.options', 'conditionalRules.targetField']);

        $existingByKey = Registration::query()->where('reservation_key', $idempotencyKey)->first();

        if ($existingByKey !== null) {
            return SubmitRegistrationResult::created($existingByKey);
        }

        $this->assertRegistrationWindowOpen($context);

        $email = mb_strtolower(trim($identity->email));

        // Sans e-mail (invité connu par WhatsApp), le doublon se reconnaît
        // par le contact : deux réponses sans adresse ne se confondent pas.
        $duplicate = ($email !== '' ? Registration::query()->where('event_id', $context->eventId)->where('email', $email)->first() : null)
            ?? $this->answeredByContact($context, $identity->contactId);

        if ($duplicate !== null) {
            return SubmitRegistrationResult::duplicateFound($duplicate);
        }

        // « Je ne peux pas venir » : la réponse est gardée comme refus et ne
        // tient aucune place, ni dans la capacité de l'événement ni dans le
        // quota d'une option (décision produit). Un refus ne vient avec personne.
        $declined = $visibilityContext !== null && ! $visibilityContext->attending;
        $companions = $declined ? [] : $companions;

        $visibility = $this->evaluateFormVisibility->handle($formVersion, $answers, $visibilityContext);
        $rules = $this->buildFormValidationRules->handle($formVersion, $answers, $visibilityContext);
        Validator::make($answers, $rules)->validate();
        $this->validateCompanions->handle($formVersion, $answers, $companions, $visibilityContext);
        $this->validateRegistrationFiles->handle($formVersion, $answers, $visibility);

        $subEvents = $declined ? [] : $this->syncSubEventRegistrations->selected($formVersion, $answers, $visibility, $context->subEvents);
        $this->syncSubEventRegistrations->assertNoScheduleConflict($formVersion, $subEvents);

        $registration = DB::transaction(function () use ($context, $formVersion, $identity, $email, $answers, $metadata, $idempotencyKey, $visibility, $declined, $companions, $visibilityContext, $subEvents): Registration {
            // Une place par personne : le titulaire et chacun de ses accompagnants.
            $status = $declined ? RegistrationStatus::Declined : $this->reserveEventPlace($context, $idempotencyKey, 1 + count($companions));

            $registration = Registration::query()->create([
                'organization_id' => $context->organizationId,
                'event_id' => $context->eventId,
                'form_version_id' => $formVersion->id,
                'status' => $status,
                'reservation_key' => $idempotencyKey,
                'email' => $email,
                'contact_id' => $identity->contactId,
                'first_name' => $identity->firstName,
                'last_name' => $identity->lastName,
                'phone_e164' => $identity->phone,
                'source' => $metadata->source,
                'utm' => $metadata->utm,
                'referrer' => $metadata->referrer,
                'ip_address' => $metadata->ipAddress,
                'user_agent' => $metadata->userAgent,
                'locale' => $metadata->locale,
                'registered_at' => CarbonImmutable::now(),
            ]);

            Attendee::query()->create([
                'organization_id' => $context->organizationId,
                'registration_id' => $registration->id,
                'contact_id' => $identity->contactId,
                'first_name' => $identity->firstName,
                'last_name' => $identity->lastName,
                'email' => $email !== '' ? $email : null,
                'is_primary' => true,
                'position' => 0,
            ]);

            $this->writeAnswers($context, $formVersion, $registration, $answers, $visibility, $metadata->ipAddress, $idempotencyKey, ! $declined);

            foreach ($companions as $index => $companion) {
                $attendee = Attendee::query()->create([
                    'organization_id' => $context->organizationId,
                    'registration_id' => $registration->id,
                    'contact_id' => $companion->contactId,
                    'first_name' => trim($companion->firstName),
                    'last_name' => $companion->lastName !== null ? trim($companion->lastName) : null,
                    'is_primary' => false,
                    'position' => $index + 1,
                ]);

                $companionAnswers = AskScope::answersFor($formVersion, $answers, $companion->answers);
                $companionVisibility = $this->evaluateFormVisibility->handle($formVersion, $companionAnswers, $visibilityContext);

                $this->writeAnswers($context, $formVersion, $registration, $companionAnswers, $companionVisibility, $metadata->ipAddress, $idempotencyKey, true, $attendee->id);
            }

            $this->syncSubEventRegistrations->handle($registration, $subEvents);
            $this->syncRegistrationFiles->handle($registration, $formVersion);

            return $registration;
        });

        RegistrationCreated::dispatch($registration);

        return SubmitRegistrationResult::created($registration);
    }

    /**
     * Un invité de la liste a déjà répondu s'il a sa propre inscription, ou
     * s'il vient avec un autre membre de son groupe.
     */
    private function answeredByContact(EventRegistrationContext $context, ?int $contactId): ?Registration
    {
        if ($contactId === null) {
            return null;
        }

        return Registration::query()
            ->where('event_id', $context->eventId)
            ->where(fn ($query) => $query->where('contact_id', $contactId)->orWhereHas('attendees', fn ($attendees) => $attendees->where('contact_id', $contactId)))
            ->first();
    }

    private function reserveEventPlace(EventRegistrationContext $context, string $idempotencyKey, int $people): RegistrationStatus
    {
        $outcome = $this->reserveCapacity->handle(
            organizationId: $context->organizationId,
            holderType: 'event',
            holderId: (string) $context->eventId,
            capacityLimit: $context->capacity,
            reservationKey: $idempotencyKey,
            quantity: $people,
            allowWaitlist: $context->allowWaitlist,
        );

        if ($outcome->outcome === ReservationOutcome::Rejected) {
            throw EventFullException::forEvent($context->eventId);
        }

        return $outcome->outcome === ReservationOutcome::Accepted && ! $this->organizationOverMonthlyQuota($context)
            ? RegistrationStatus::Confirmed
            : RegistrationStatus::Waitlisted;
    }

    /**
     * Dépassement de quota mensuel du plan (T-074, AC : « les nouvelles
     * inscriptions passent en attente ») : jamais un rejet, seulement une
     * bascule en liste d'attente — l'inscription est toujours créée, aucune
     * donnée n'est perdue, elle sera confirmable manuellement ou dès le
     * mois suivant. Un refus n'est pas une inscription : il ne compte pas.
     */
    private function organizationOverMonthlyQuota(EventRegistrationContext $context): bool
    {
        if ($context->organizationMonthlyRegistrationQuota === null) {
            return false;
        }

        // Une session d'événement secondaire n'est pas une inscription de plus.
        $count = Registration::query()
            ->where('organization_id', $context->organizationId)
            ->whereNull('parent_registration_id')
            ->where('status', '!=', RegistrationStatus::Declined->value)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->count();

        return $count >= $context->organizationMonthlyRegistrationQuota;
    }

    private function assertRegistrationWindowOpen(EventRegistrationContext $context): void
    {
        if (! $this->isRegistrationWindowOpen->handle($context)) {
            throw RegistrationClosedException::outsideWindow($context->registrationClosedMessage);
        }
    }

    /**
     * Réponses du titulaire ($attendeeId null) ou d'un accompagnant, qui ne
     * répond qu'aux questions posées à chaque personne.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, array{visible: bool, required: bool}>  $visibility
     */
    private function writeAnswers(
        EventRegistrationContext $context,
        FormVersion $formVersion,
        Registration $registration,
        array $answers,
        array $visibility,
        ?string $ip,
        string $idempotencyKey,
        bool $reserveOptions,
        ?int $attendeeId = null,
    ): void {
        foreach ($formVersion->fields as $field) {
            if ($attendeeId !== null && ! AskScope::isPerPerson($field)) {
                continue;
            }

            if (! $visibility[$field->key]['visible'] || ! array_key_exists($field->key, $answers)) {
                continue;
            }

            $rawValue = $answers[$field->key];

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $normalized = $this->normalizeFieldAnswer->handle($field, $rawValue, $ip);

            // Adresse laissée vide ou « pas de don » : rien à enregistrer.
            if ($normalized === []) {
                continue;
            }

            RegistrationAnswer::query()->create([
                'organization_id' => $context->organizationId,
                'registration_id' => $registration->id,
                'attendee_id' => $attendeeId,
                'form_field_id' => $field->id,
                'value' => $normalized,
            ]);

            if ($reserveOptions && $field->type->supportsOptions()) {
                $this->reserveSelectedOptions($context, $field, $rawValue, $idempotencyKey, $attendeeId);
            }
        }
    }

    private function reserveSelectedOptions(EventRegistrationContext $context, FormField $field, mixed $rawValue, string $idempotencyKey, ?int $attendeeId): void
    {
        $selectedValues = is_array($rawValue) ? $rawValue : [$rawValue];

        foreach ($selectedValues as $selectedValue) {
            $option = $field->options->firstWhere('value', $selectedValue);

            if ($option === null || $option->quota === null) {
                continue;
            }

            $outcome = $this->reserveCapacity->handle(
                organizationId: $context->organizationId,
                holderType: 'form_field_option',
                holderId: (string) $option->id,
                capacityLimit: $option->quota,
                reservationKey: OptionReservationKey::for($idempotencyKey, $option->id, $attendeeId),
                allowWaitlist: false,
            );

            if ($outcome->outcome === ReservationOutcome::Rejected) {
                throw OptionFullException::forOption($field->label, $option->label);
            }
        }
    }
}
