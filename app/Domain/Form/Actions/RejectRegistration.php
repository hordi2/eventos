<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Events\RegistrationRejected;
use App\Domain\Form\InvalidRegistrationDecisionException;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Validation manuelle (M1.2) : l'organisateur refuse une inscription en
 * attente. La place retenue depuis la réponse est rendue, ce qui fait
 * avancer la liste d'attente, et l'invité est prévenu (listener).
 */
final class RejectRegistration
{
    public function __construct(
        private readonly SnapshotRegistration $snapshotRegistration,
        private readonly ReleaseRegistrationPlaces $releaseRegistrationPlaces,
    ) {}

    public function handle(Registration $registration, User $decider, ?string $reason = null): Registration
    {
        Gate::forUser($decider)->authorize('updateGuests', $registration->organization);

        if ($registration->status !== RegistrationStatus::Pending) {
            throw InvalidRegistrationDecisionException::notPending($registration->status->label());
        }

        DB::transaction(function () use ($registration, $reason): void {
            $this->snapshotRegistration->handle($registration);

            $registration->update([
                'status' => RegistrationStatus::Rejected,
                // Horodatage de la décision : même colonne que l'annulation,
                // le journal d'audit garde qui a décidé.
                'cancelled_at' => CarbonImmutable::now(),
                'cancellation_reason' => $reason,
            ]);

            $this->releaseRegistrationPlaces->handle($registration);
        });

        $registration = $registration->fresh();
        RegistrationRejected::dispatch($registration);

        return $registration;
    }
}
