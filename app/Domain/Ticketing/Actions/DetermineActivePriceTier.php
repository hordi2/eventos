<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use Carbon\CarbonImmutable;

/**
 * Bascule automatique de palier par date ou par quota (M5.1 : « Tarification
 * par paliers dans le temps : early bird -> normal -> tardif »). Les paliers
 * sont évalués dans l'ordre de position ; le premier dont la fenêtre de
 * dates couvre l'instant donné et dont le quota n'est pas atteint est actif.
 * Un palier épuisé ou hors fenêtre est simplement ignoré, jamais mis en
 * attente (contrairement à la capacité d'un événement).
 */
final class DetermineActivePriceTier
{
    public function __construct(private readonly GetRemainingCapacity $getRemainingCapacity) {}

    public function handle(TicketType $ticketType, ?CarbonImmutable $at = null): ?PriceTier
    {
        $at ??= CarbonImmutable::now();

        $tiers = PriceTier::query()
            ->where('ticket_type_id', $ticketType->id)
            ->orderBy('position')
            ->get();

        foreach ($tiers as $tier) {
            if ($this->isWithinWindow($tier, $at) && ! $this->isExhausted($tier)) {
                return $tier;
            }
        }

        return null;
    }

    private function isWithinWindow(PriceTier $tier, CarbonImmutable $at): bool
    {
        if ($tier->starts_at !== null && $at->lessThan($tier->starts_at)) {
            return false;
        }

        if ($tier->ends_at !== null && $at->greaterThan($tier->ends_at)) {
            return false;
        }

        return true;
    }

    private function isExhausted(PriceTier $tier): bool
    {
        return $this->getRemainingCapacity->isFull('price_tier', (string) $tier->id, $tier->quantity);
    }
}
