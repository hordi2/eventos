<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Events\RegistrationUpdated;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\OptionFullException;
use App\Domain\Form\RegistrationEditLockedException;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Support\Capacity\Actions\ReleaseCapacity;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationOutcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Modifie les réponses (et l'identité) d'une inscription existante — jamais
 * la version du formulaire utilisée : toujours celle de la Registration
 * elle-même (§4.7 du CLAUDE.md), pas la version actuellement publiée du
 * formulaire, qui a pu changer depuis. Ne touche jamais la capacité de
 * l'événement (la place déjà tenue reste tenue) — seulement les quotas
 * d'option dont la valeur choisie change réellement.
 */
final class UpdateRegistration
{
    public function __construct(
        private readonly EvaluateFormVisibility $evaluateFormVisibility,
        private readonly BuildFormValidationRules $buildFormValidationRules,
        private readonly NormalizeFieldAnswer $normalizeFieldAnswer,
        private readonly ReserveCapacity $reserveCapacity,
        private readonly ReleaseCapacity $releaseCapacity,
        private readonly SnapshotRegistration $snapshotRegistration,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     */
    public function handle(Registration $registration, EventEditPolicy $policy, AttendeeIdentity $identity, array $answers): Registration
    {
        if ($policy->isLocked()) {
            throw RegistrationEditLockedException::locked();
        }

        $version = $registration->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        $visibility = $this->evaluateFormVisibility->handle($version, $answers);
        $rules = $this->buildFormValidationRules->handle($version, $answers);
        Validator::make($answers, $rules)->validate();

        DB::transaction(function () use ($registration, $version, $identity, $answers, $visibility): void {
            $this->snapshotRegistration->handle($registration);

            $email = mb_strtolower(trim($identity->email));

            $registration->update([
                'email' => $email,
                'first_name' => $identity->firstName,
                'last_name' => $identity->lastName,
                'phone_e164' => $identity->phone,
            ]);

            $registration->attendees()->where('is_primary', true)->update([
                'first_name' => $identity->firstName,
                'last_name' => $identity->lastName,
                'email' => $email,
            ]);

            $existingAnswers = $registration->answers()->with('formField')->get()->keyBy(fn (RegistrationAnswer $a): string => $a->formField->key);

            foreach ($version->fields as $field) {
                $this->reconcileField($registration, $field, $visibility[$field->key]['visible'], $answers, $existingAnswers->get($field->key));
            }
        });

        RegistrationUpdated::dispatch($registration->fresh());

        return $registration->fresh();
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function reconcileField(Registration $registration, FormField $field, bool $isVisible, array $answers, ?RegistrationAnswer $existing): void
    {
        $hasNewValue = $isVisible && array_key_exists($field->key, $answers) && $answers[$field->key] !== null && $answers[$field->key] !== '';

        if (! $hasNewValue) {
            if ($existing !== null) {
                $this->releaseOptions($registration, $field, $existing->value, []);
                $existing->delete();
            }

            return;
        }

        $normalized = $this->normalizeFieldAnswer->handle($field, $answers[$field->key], null);

        if ($field->type->supportsOptions()) {
            $this->releaseOptions($registration, $field, $existing?->value, is_array($normalized) ? $normalized : [$normalized]);
        }

        if ($existing !== null) {
            $existing->update(['value' => $normalized]);
        } else {
            RegistrationAnswer::query()->create([
                'organization_id' => $registration->organization_id,
                'registration_id' => $registration->id,
                'form_field_id' => $field->id,
                'value' => $normalized,
            ]);
        }
    }

    /**
     * Libère les options qui ne sont plus sélectionnées et réserve celles
     * qui viennent de l'être ; ne touche jamais celles inchangées.
     *
     * @param  list<string>  $newSelected
     */
    private function releaseOptions(Registration $registration, FormField $field, mixed $oldValue, array $newSelected): void
    {
        $oldSelected = is_array($oldValue) ? $oldValue : ($oldValue !== null ? [$oldValue] : []);

        foreach (array_diff($oldSelected, $newSelected) as $value) {
            $option = $field->options->firstWhere('value', $value);

            if ($option !== null && $option->quota !== null) {
                $this->releaseCapacity->handle('form_field_option', (string) $option->id, "{$registration->reservation_key}:option:{$option->id}");
            }
        }

        foreach (array_diff($newSelected, $oldSelected) as $value) {
            $option = $field->options->firstWhere('value', $value);

            if ($option === null || $option->quota === null) {
                continue;
            }

            $outcome = $this->reserveCapacity->handle(
                organizationId: $registration->organization_id,
                holderType: 'form_field_option',
                holderId: (string) $option->id,
                capacityLimit: $option->quota,
                reservationKey: "{$registration->reservation_key}:option:{$option->id}",
                allowWaitlist: false,
            );

            if ($outcome->outcome === ReservationOutcome::Rejected) {
                throw OptionFullException::forOption($field->label, $option->label);
            }
        }
    }
}
