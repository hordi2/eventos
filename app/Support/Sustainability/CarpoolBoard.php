<?php

declare(strict_types=1);

namespace App\Support\Sustainability;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Models\TravelMode;

/**
 * Covoiturage (D12) : qui propose des places, qui en cherche.
 *
 * N'y figure que celui qui a coché l'un ou l'autre — déclarer son
 * déplacement ne publie rien. Seuls le prénom et la ville de départ
 * paraissent : ni nom complet, ni adresse, ni téléphone. Pour se joindre,
 * les participants passent par la messagerie de l'événement quand elle est
 * ouverte.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class CarpoolBoard
{
    /**
     * @return array{offers: list<array{name: string, city: ?string}>, seekers: list<array{name: string, city: ?string}>, alone: int}
     */
    public function handle(Event $event, ?Registration $viewer = null): array
    {
        $declared = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->whereNotNull('travel_declared_at')
            ->get(['id', 'first_name', 'travel_city', 'travel_mode', 'carpool_role']);

        $present = fn (Registration $registration): array => [
            'name' => $registration->first_name ?? '',
            'city' => $registration->travel_city,
        ];

        return [
            'offers' => $declared
                ->where('carpool_role', 'offers')
                ->where('id', '!=', $viewer?->id)
                ->map($present)
                ->values()
                ->all(),
            'seekers' => $declared
                ->where('carpool_role', 'seeks')
                ->where('id', '!=', $viewer?->id)
                ->map($present)
                ->values()
                ->all(),
            // Combien viennent seuls en voiture : c'est le chiffre qui donne
            // envie de partager.
            'alone' => $declared->where('travel_mode', TravelMode::Car)->count(),
        ];
    }
}
