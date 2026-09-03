<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * is_free et currency ne sont volontairement pas modifiables ici : changer
 * l'un ou l'autre après création casserait l'invariant vérifié à la
 * création (billet gratuit sans palier / billet payant avec au moins un
 * palier, CreateTicketType) sans re-valider les paliers existants — un
 * organisateur qui veut ce changement supprime et recrée le type de billet.
 */
final class UpdateTicketType
{
    use ValidatesTicketTypeRules;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(TicketType $ticketType, User $updater, array $data): TicketType
    {
        Gate::forUser($updater)->authorize('update', $ticketType);

        $minPerOrder = (int) ($data['min_per_order'] ?? $ticketType->min_per_order);
        $maxPerOrder = array_key_exists('max_per_order', $data) ? $data['max_per_order'] : $ticketType->max_per_order;
        $vatMode = $data['vat_mode'] ?? $ticketType->vat_mode;
        $vatRateBp = (int) ($data['vat_rate_bp'] ?? $ticketType->vat_rate_bp);

        $this->assertValidOrderBounds($minPerOrder, $maxPerOrder);
        $this->assertValidVat($vatMode, $vatRateBp);

        $ticketType->update([
            'name' => $data['name'] ?? $ticketType->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $ticketType->description,
            'min_per_order' => $minPerOrder,
            'max_per_order' => $maxPerOrder,
            'total_quantity' => array_key_exists('total_quantity', $data) ? $data['total_quantity'] : $ticketType->total_quantity,
            'vat_mode' => $vatMode,
            'vat_rate_bp' => $vatRateBp,
            'fees_absorbed' => array_key_exists('fees_absorbed', $data) ? (bool) $data['fees_absorbed'] : $ticketType->fees_absorbed,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $ticketType->is_active,
        ]);

        return $ticketType->fresh();
    }
}
