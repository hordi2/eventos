<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Data\EventEditPolicy;
use App\Domain\Form\Events\RegistrationCancelled;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\RegistrationEditLockedException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Annulation par l'invité (M2.4, T-033) : libère les places tenues sur
 * l'événement — ce qui promeut automatiquement le premier de la liste
 * d'attente (T-024) —, tous les quotas d'option encore tenus, titulaire et
 * accompagnants compris, et révoque les QR de chaque personne.
 */
final class CancelRegistration
{
    public function __construct(
        private readonly SnapshotRegistration $snapshotRegistration,
        private readonly ReleaseRegistrationPlaces $releaseRegistrationPlaces,
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

            $this->releaseRegistrationPlaces->handle($registration);
        });

        RegistrationCancelled::dispatch($registration->fresh());

        return $registration->fresh();
    }
}
