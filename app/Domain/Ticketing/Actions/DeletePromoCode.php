<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\PromoCode;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Suppression logique (règle 4.5) : les commandes déjà passées gardent leur
 * réduction et le lien vers le code, même supprimé.
 */
final class DeletePromoCode
{
    public function handle(PromoCode $promoCode, User $deleter): void
    {
        Gate::forUser($deleter)->authorize('delete', $promoCode);

        $promoCode->delete();
    }
}
