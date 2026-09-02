<?php

declare(strict_types=1);

namespace App\Support\Capacity\Actions;

use App\Support\Capacity\Events\WaitlistEntryPromoted;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\Capacity\Models\WaitlistEntryStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Libère une place retenue et, si la capacité libérée le permet, promeut le
 * premier de la liste d'attente (M2.4 : « Annulation → libération de la
 * place, promotion de la liste d'attente »). Ne saute jamais un rang : si la
 * quantité de la première entrée en attente excède ce qui vient d'être
 * libéré, personne n'est promu ce tour-ci — elle reste prioritaire pour la
 * prochaine libération plutôt que d'être doublée par une entrée plus petite
 * placée derrière elle.
 */
final class ReleaseCapacity
{
    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function handle(string $holderType, string $holderId, string $reservationKey): void
    {
        $lock = Cache::lock("capacity-hold:{$holderType}:{$holderId}", self::LOCK_SECONDS);

        $lock->block(
            self::LOCK_WAIT_SECONDS,
            function () use ($holderType, $holderId, $reservationKey): void {
                DB::transaction(function () use ($holderType, $holderId, $reservationKey): void {
                    $this->release($holderType, $holderId, $reservationKey);
                });
            },
        );
    }

    private function release(string $holderType, string $holderId, string $reservationKey): void
    {
        $hold = CapacityHold::query()
            ->where('reservation_key', $reservationKey)
            ->where('status', CapacityHoldStatus::Held)
            ->first();

        if ($hold === null) {
            // Idempotent : déjà relâchée, ou jamais tenue (ex. était sur liste d'attente).
            return;
        }

        $hold->update(['status' => CapacityHoldStatus::Released, 'released_at' => now()]);

        $freed = $hold->quantity;

        while ($freed > 0) {
            $next = WaitlistEntry::query()
                ->where('holder_type', $holderType)
                ->where('holder_id', $holderId)
                ->where('status', WaitlistEntryStatus::Waiting)
                ->orderBy('position')
                ->first();

            if ($next === null || $next->quantity > $freed) {
                break;
            }

            $next->update(['status' => WaitlistEntryStatus::Promoted, 'promoted_at' => now()]);

            CapacityHold::query()->create([
                'organization_id' => $next->organization_id,
                'holder_type' => $holderType,
                'holder_id' => $holderId,
                'reservation_key' => $next->reservation_key,
                'quantity' => $next->quantity,
                'status' => CapacityHoldStatus::Held,
            ]);

            WaitlistEntryPromoted::dispatch($next);

            $freed -= $next->quantity;
        }
    }
}
