<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\PriceTier;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class DeletePriceTier
{
    public function handle(PriceTier $tier, User $deleter): void
    {
        Gate::forUser($deleter)->authorize('update', $tier->ticketType);

        // Garde-fou symétrique à CreateTicketType : un billet payant doit
        // toujours conserver au moins un palier de tarification.
        if ($tier->ticketType->priceTiers()->count() <= 1) {
            throw new InvalidArgumentException('Un billet payant doit conserver au moins un palier de tarification.');
        }

        $tier->delete();
    }
}
