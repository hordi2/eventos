<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Gérer les paliers d'un type de billet est une forme de modification de ce
 * type de billet : réutilise l'ability update de TicketTypePolicy plutôt
 * qu'une policy dédiée à PriceTier.
 */
final class CreatePriceTier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(TicketType $ticketType, User $creator, array $data): PriceTier
    {
        Gate::forUser($creator)->authorize('update', $ticketType);

        if ($ticketType->is_free) {
            throw new InvalidArgumentException('Un billet gratuit ne peut pas avoir de palier de tarification.');
        }

        return PriceTier::query()->create([
            'organization_id' => $ticketType->organization_id,
            'ticket_type_id' => $ticketType->id,
            'name' => $data['name'],
            'amount' => $data['amount'],
            'quantity' => $data['quantity'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'position' => $data['position'] ?? $ticketType->priceTiers()->count(),
        ]);
    }
}
