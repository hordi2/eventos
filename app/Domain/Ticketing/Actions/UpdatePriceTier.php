<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\PriceTier;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdatePriceTier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PriceTier $tier, User $updater, array $data): PriceTier
    {
        Gate::forUser($updater)->authorize('update', $tier->ticketType);

        $tier->update([
            'name' => $data['name'] ?? $tier->name,
            'amount' => $data['amount'] ?? $tier->amount,
            'quantity' => array_key_exists('quantity', $data) ? $data['quantity'] : $tier->quantity,
            'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $tier->starts_at,
            'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $tier->ends_at,
        ]);

        return $tier->fresh();
    }
}
