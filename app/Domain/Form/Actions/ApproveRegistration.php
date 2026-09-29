<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Events\RegistrationApproved;
use App\Domain\Form\InvalidRegistrationDecisionException;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Validation manuelle (M1.2) : l'organisateur accepte une inscription en
 * attente. La place était déjà retenue depuis la réponse de l'invité, rien
 * n'est donc réservé ici ; les sessions choisies suivent.
 */
final class ApproveRegistration
{
    public function handle(Registration $registration, User $decider): Registration
    {
        Gate::forUser($decider)->authorize('updateGuests', $registration->organization);

        if ($registration->status !== RegistrationStatus::Pending) {
            throw InvalidRegistrationDecisionException::notPending($registration->status->label());
        }

        DB::transaction(function () use ($registration): void {
            $registration->update([
                'status' => RegistrationStatus::Confirmed,
            ]);

            Registration::query()
                ->where('parent_registration_id', $registration->id)
                ->where('status', RegistrationStatus::Pending)
                ->update(['status' => RegistrationStatus::Confirmed]);
        });

        $registration = $registration->fresh();
        RegistrationApproved::dispatch($registration);

        return $registration;
    }
}
