<?php

declare(strict_types=1);

namespace App\Support\Capacity\Actions;

use App\Support\Capacity\Data\ReservationResult;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\Capacity\Models\WaitlistEntryStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Moteur générique de réservation de capacité (§4.4 du CLAUDE.md :
 * idempotence). Utilisable pour n'importe quel « holder » — événement,
 * sous-événement, option de champ de formulaire, et plus tard billet — sans
 * que Domain/Event et Domain/Form aient à se connaître : chacun appelle ce
 * moteur via sa propre action (ReserveEventCapacity, ReserveFieldOptionCapacity...)
 * en lui fournissant holder_type/holder_id et sa propre limite de capacité.
 *
 * Le verrou Redis (Cache::lock) sérialise les tentatives concurrentes sur un
 * même holder : sans lui, deux requêtes simultanées pourraient toutes deux
 * lire un compte encore sous la limite avant que l'une des deux n'écrive,
 * dépassant la capacité (condition de course classique "check-then-act").
 */
final class ReserveCapacity
{
    private const LOCK_SECONDS = 10;

    /**
     * Délai maximal d'attente du verrou avant d'abandonner (une requête HTTP
     * ne doit pas rester bloquée indéfiniment derrière un pic de contention).
     * Le test de concurrence (T-024) passe une valeur plus large : lancer
     * 100 vrais processus `php artisan` ajoute un coût de démarrage par
     * tentative qu'une requête HTTP normale, déjà servie par un worker
     * démarré, n'a pas — sans quoi le test échouerait sur ce coût artificiel
     * plutôt que sur une vraie défaillance du verrou.
     */
    private const DEFAULT_LOCK_WAIT_SECONDS = 5;

    public function handle(
        int $organizationId,
        string $holderType,
        string $holderId,
        ?int $capacityLimit,
        string $reservationKey,
        int $quantity = 1,
        bool $allowWaitlist = false,
        int $lockWaitSeconds = self::DEFAULT_LOCK_WAIT_SECONDS,
    ): ReservationResult {
        $existing = $this->existingResultFor($reservationKey);

        if ($existing !== null) {
            return $existing;
        }

        $lock = Cache::lock("capacity-hold:{$holderType}:{$holderId}", self::LOCK_SECONDS);

        return $lock->block(
            $lockWaitSeconds,
            fn (): ReservationResult => DB::transaction(fn (): ReservationResult => $this->reserve(
                $organizationId,
                $holderType,
                $holderId,
                $capacityLimit,
                $reservationKey,
                $quantity,
                $allowWaitlist,
            )),
        );
    }

    private function existingResultFor(string $reservationKey): ?ReservationResult
    {
        $hold = CapacityHold::query()->where('reservation_key', $reservationKey)->first();

        if ($hold !== null) {
            return ReservationResult::accepted();
        }

        $entry = WaitlistEntry::query()->where('reservation_key', $reservationKey)->first();

        if ($entry !== null) {
            return ReservationResult::waitlisted($entry->position);
        }

        return null;
    }

    private function reserve(
        int $organizationId,
        string $holderType,
        string $holderId,
        ?int $capacityLimit,
        string $reservationKey,
        int $quantity,
        bool $allowWaitlist,
    ): ReservationResult {
        if ($capacityLimit !== null) {
            $held = CapacityHold::query()
                ->where('holder_type', $holderType)
                ->where('holder_id', $holderId)
                ->where('status', CapacityHoldStatus::Held)
                ->sum('quantity');

            if ($held + $quantity > $capacityLimit) {
                if (! $allowWaitlist) {
                    return ReservationResult::rejected();
                }

                $position = ((int) WaitlistEntry::query()
                    ->where('holder_type', $holderType)
                    ->where('holder_id', $holderId)
                    ->where('status', WaitlistEntryStatus::Waiting)
                    ->max('position')) + 1;

                WaitlistEntry::query()->create([
                    'organization_id' => $organizationId,
                    'holder_type' => $holderType,
                    'holder_id' => $holderId,
                    'reservation_key' => $reservationKey,
                    'quantity' => $quantity,
                    'position' => $position,
                    'status' => WaitlistEntryStatus::Waiting,
                ]);

                return ReservationResult::waitlisted($position);
            }
        }

        CapacityHold::query()->create([
            'organization_id' => $organizationId,
            'holder_type' => $holderType,
            'holder_id' => $holderId,
            'reservation_key' => $reservationKey,
            'quantity' => $quantity,
            'status' => CapacityHoldStatus::Held,
        ]);

        return ReservationResult::accepted();
    }
}
