<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\EventRegistrationContext;
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
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\EvaluateFormVisibility;
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
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     */
    public function handle(
        EventRegistrationContext $context,
        FormVersion $formVersion,
        AttendeeIdentity $identity,
        array $answers,
        RegistrationSubmissionMetadata $metadata,
        string $idempotencyKey,
    ): SubmitRegistrationResult {
        app(CurrentOrganization::class)->set($context->organizationId);
        $formVersion->loadMissing(['fields.options', 'conditionalRules.targetField']);

        $existingByKey = Registration::query()->where('reservation_key', $idempotencyKey)->first();

        if ($existingByKey !== null) {
            return SubmitRegistrationResult::created($existingByKey);
        }

        $this->assertRegistrationWindowOpen($context);

        $email = mb_strtolower(trim($identity->email));

        $duplicate = Registration::query()
            ->where('event_id', $context->eventId)
            ->where('email', $email)
            ->first();

        if ($duplicate !== null) {
            return SubmitRegistrationResult::duplicateFound($duplicate);
        }

        $visibility = $this->evaluateFormVisibility->handle($formVersion, $answers);
        $rules = $this->buildFormValidationRules->handle($formVersion, $answers);
        Validator::make($answers, $rules)->validate();

        $registration = DB::transaction(function () use ($context, $formVersion, $identity, $email, $answers, $metadata, $idempotencyKey, $visibility): Registration {
            $outcome = $this->reserveCapacity->handle(
                organizationId: $context->organizationId,
                holderType: 'event',
                holderId: (string) $context->eventId,
                capacityLimit: $context->capacity,
                reservationKey: $idempotencyKey,
                allowWaitlist: $context->allowWaitlist,
            );

            if ($outcome->outcome === ReservationOutcome::Rejected) {
                throw EventFullException::forEvent($context->eventId);
            }

            $registration = Registration::query()->create([
                'organization_id' => $context->organizationId,
                'event_id' => $context->eventId,
                'form_version_id' => $formVersion->id,
                'status' => $outcome->outcome === ReservationOutcome::Accepted
                    ? RegistrationStatus::Confirmed
                    : RegistrationStatus::Waitlisted,
                'reservation_key' => $idempotencyKey,
                'email' => $email,
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
                'first_name' => $identity->firstName,
                'last_name' => $identity->lastName,
                'email' => $email,
                'is_primary' => true,
            ]);

            $this->writeAnswers($context, $formVersion, $registration, $answers, $visibility, $metadata->ipAddress, $idempotencyKey);

            return $registration;
        });

        RegistrationCreated::dispatch($registration);

        return SubmitRegistrationResult::created($registration);
    }

    private function assertRegistrationWindowOpen(EventRegistrationContext $context): void
    {
        $now = CarbonImmutable::now($context->timezone);

        $opensAt = $context->registrationOpensAt?->setTimezone($context->timezone);
        $closesAt = $context->registrationClosesAt?->setTimezone($context->timezone);

        if (($opensAt !== null && $now->lessThan($opensAt)) || ($closesAt !== null && $now->greaterThan($closesAt))) {
            throw RegistrationClosedException::outsideWindow($context->registrationClosedMessage);
        }
    }

    /**
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
    ): void {
        foreach ($formVersion->fields as $field) {
            if (! $visibility[$field->key]['visible'] || ! array_key_exists($field->key, $answers)) {
                continue;
            }

            $rawValue = $answers[$field->key];

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $normalized = $this->normalizeFieldAnswer->handle($field, $rawValue, $ip);

            RegistrationAnswer::query()->create([
                'organization_id' => $context->organizationId,
                'registration_id' => $registration->id,
                'form_field_id' => $field->id,
                'value' => $normalized,
            ]);

            if ($field->type->supportsOptions()) {
                $this->reserveSelectedOptions($context, $field, $rawValue, $idempotencyKey);
            }
        }
    }

    private function reserveSelectedOptions(EventRegistrationContext $context, FormField $field, mixed $rawValue, string $idempotencyKey): void
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
                reservationKey: "{$idempotencyKey}:option:{$option->id}",
                allowWaitlist: false,
            );

            if ($outcome->outcome === ReservationOutcome::Rejected) {
                throw OptionFullException::forOption($field->label, $option->label);
            }
        }
    }
}
