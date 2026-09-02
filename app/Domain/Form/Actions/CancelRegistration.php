<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Events\RegistrationCancelled;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\RegistrationEditLockedException;
use App\Support\Capacity\Actions\ReleaseCapacity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Annulation par l'invité (M2.4, T-033) : libère la place tenue sur
 * l'événement — ce qui promeut automatiquement le premier de la liste
 * d'attente (T-024) — et tous les quotas d'option encore tenus.
 */
final class CancelRegistration
{
    public function __construct(
        private readonly ReleaseCapacity $releaseCapacity,
        private readonly SnapshotRegistration $snapshotRegistration,
    ) {}

    public function handle(Registration $registration, EventEditPolicy $policy, ?string $reason = null): Registration
    {
        if ($policy->isLocked()) {
            throw RegistrationEditLockedException::locked();
        }

        DB::transaction(function () use ($registration, $reason): void {
            $this->snapshotRegistration->handle($registration);

            $registration->update([
                'status' => RegistrationStatus::Cancelled,
                'cancelled_at' => CarbonImmutable::now(),
                'cancellation_reason' => $reason,
            ]);

            $this->releaseCapacity->handle('event', (string) $registration->event_id, $registration->reservation_key);

            $registration->answers()->with('formField.options')->get()
                ->each(function (RegistrationAnswer $answer) use ($registration): void {
                    $this->releaseSelectedOptions($registration, $answer);
                });
        });

        RegistrationCancelled::dispatch($registration->fresh());

        return $registration->fresh();
    }

    private function releaseSelectedOptions(Registration $registration, RegistrationAnswer $answer): void
    {
        $field = $answer->formField;

        if (! $field->type->supportsOptions()) {
            return;
        }

        $selected = is_array($answer->value) ? $answer->value : [$answer->value];

        foreach ($selected as $value) {
            $option = $field->options->firstWhere('value', $value);

            if ($option !== null && $option->quota !== null) {
                $this->releaseCapacity->handle('form_field_option', (string) $option->id, "{$registration->reservation_key}:option:{$option->id}");
            }
        }
    }
}
