<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Events\RegistrationUpdated;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\OptionFullException;
use App\Domain\Form\RegistrationEditLockedException;
use App\Domain\Form\Support\AskScope;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Form\Support\OptionReservationKey;
use App\Domain\Form\Support\ValidateCompanions;
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
 * l'événement (les places déjà tenues restent tenues, le nombre
 * d'accompagnants ne change pas ici) — seulement les quotas d'option dont
 * la valeur choisie change réellement.
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
        private readonly ValidateCompanions $validateCompanions,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  list<CompanionData>  $companions  accompagnants déjà inscrits (attendeeId renseigné) : noms et réponses à jour
     */
    public function handle(
        Registration $registration,
        EventEditPolicy $policy,
        AttendeeIdentity $identity,
        array $answers,
        ?FormVisibilityContext $visibilityContext = null,
        array $companions = [],
    ): Registration {
        if ($policy->isLocked()) {
            throw RegistrationEditLockedException::locked();
        }

        $version = $registration->formVersion()->with(['fields.options', 'conditionalRules.targetField'])->firstOrFail();
        $visibility = $this->evaluateFormVisibility->handle($version, $answers, $visibilityContext);
        $rules = $this->buildFormValidationRules->handle($version, $answers, $visibilityContext);
        Validator::make($answers, $rules)->validate();

        $companions = $this->knownCompanions($registration, $companions);
        $this->validateCompanions->handle($version, $answers, $companions, $visibilityContext);

        DB::transaction(function () use ($registration, $version, $identity, $answers, $visibility, $companions, $visibilityContext): void {
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

            foreach ($companions as $companion) {
                $this->updateCompanion($registration, $version, $answers, $companion, $visibilityContext);
            }
        });

        RegistrationUpdated::dispatch($registration->fresh());

        return $registration->fresh();
    }

    /**
     * Seuls les accompagnants de cette inscription se modifient : un
     * identifiant d'une autre inscription est ignoré.
     *
     * @param  list<CompanionData>  $companions
     * @return list<CompanionData>
     */
    private function knownCompanions(Registration $registration, array $companions): array
    {
        $ids = $registration->companions()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_filter(
            $companions,
            fn (CompanionData $companion): bool => in_array($companion->attendeeId, $ids, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $holderAnswers
     */
    private function updateCompanion(Registration $registration, FormVersion $version, array $holderAnswers, CompanionData $companion, ?FormVisibilityContext $visibilityContext): void
    {
        $attendee = $registration->companions()->whereKey($companion->attendeeId)->firstOrFail();

        $attendee->update([
            'first_name' => trim($companion->firstName),
            'last_name' => $companion->lastName !== null ? trim($companion->lastName) : null,
        ]);

        $answers = AskScope::answersFor($version, $holderAnswers, $companion->answers);
        $visibility = $this->evaluateFormVisibility->handle($version, $answers, $visibilityContext);
        $existingAnswers = RegistrationAnswer::query()
            ->where('attendee_id', $attendee->id)
            ->with('formField')
            ->get()
            ->keyBy(fn (RegistrationAnswer $a): string => $a->formField->key);

        foreach ($version->fields as $field) {
            if (AskScope::isPerPerson($field)) {
                $this->reconcileField($registration, $field, $visibility[$field->key]['visible'], $answers, $existingAnswers->get($field->key), $attendee->id);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function reconcileField(Registration $registration, FormField $field, bool $isVisible, array $answers, ?RegistrationAnswer $existing, ?int $attendeeId = null): void
    {
        $hasNewValue = $isVisible && array_key_exists($field->key, $answers) && $answers[$field->key] !== null && $answers[$field->key] !== '';

        if (! $hasNewValue) {
            if ($existing !== null) {
                $this->releaseOptions($registration, $field, $existing->value, [], $attendeeId);
                $existing->delete();
            }

            return;
        }

        $normalized = $this->normalizeFieldAnswer->handle($field, $answers[$field->key], null);

        if ($field->type->supportsOptions()) {
            $this->releaseOptions($registration, $field, $existing?->value, is_array($normalized) ? $normalized : [$normalized], $attendeeId);
        }

        if ($existing !== null) {
            $existing->update(['value' => $normalized]);
        } else {
            RegistrationAnswer::query()->create([
                'organization_id' => $registration->organization_id,
                'registration_id' => $registration->id,
                'attendee_id' => $attendeeId,
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
    private function releaseOptions(Registration $registration, FormField $field, mixed $oldValue, array $newSelected, ?int $attendeeId): void
    {
        $oldSelected = is_array($oldValue) ? $oldValue : ($oldValue !== null ? [$oldValue] : []);

        foreach (array_diff($oldSelected, $newSelected) as $value) {
            $option = $field->options->firstWhere('value', $value);

            if ($option !== null && $option->quota !== null) {
                $this->releaseCapacity->handle('form_field_option', (string) $option->id, OptionReservationKey::for($registration->reservation_key, $option->id, $attendeeId));
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
                reservationKey: OptionReservationKey::for($registration->reservation_key, $option->id, $attendeeId),
                allowWaitlist: false,
            );

            if ($outcome->outcome === ReservationOutcome::Rejected) {
                throw OptionFullException::forOption($field->label, $option->label);
            }
        }
    }
}
