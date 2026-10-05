<?php

declare(strict_types=1);

namespace App\Support\Sustainability;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Models\TravelMode;

/**
 * Empreinte carbone d'un événement (D12) : les déplacements déclarés par les
 * participants, les repas servis et les impressions.
 *
 * Ce que l'on ne sait pas, on ne l'invente pas : seuls les déplacements
 * réellement déclarés comptent, et le rapport dit toujours quelle part des
 * participants a répondu. Un total calculé sur un tiers des présents ne vaut
 * que pour ce tiers.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class GetEventCarbonFootprint
{
    /**
     * Kilogrammes d'équivalent CO₂ par repas servi. Ordre de grandeur d'un
     * repas avec viande ; un buffet végétarien pèse environ trois fois moins.
     */
    public const KILOGRAMS_PER_MEAL = 2.0;

    /**
     * Kilogrammes d'équivalent CO₂ par page imprimée (papier et impression).
     */
    public const KILOGRAMS_PER_PAGE = 0.005;

    public function handle(Event $event): CarbonFootprintData
    {
        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->get(['travel_mode', 'travel_distance_km', 'travel_declared_at', 'carpool_role']);

        $byMode = [];
        $travelKilograms = 0.0;
        $declared = 0;

        foreach ($registrations as $registration) {
            if ($registration->travel_declared_at === null || $registration->travel_mode === null) {
                continue;
            }

            $declared++;
            $mode = $registration->travel_mode;
            // Aller-retour : on ne vient pas à un événement sans en repartir.
            $kilometres = ($registration->travel_distance_km ?? 0) * 2;
            $kilograms = $kilometres * $mode->kilogramsPerKilometre();
            $travelKilograms += $kilograms;

            $byMode[$mode->value] ??= [
                'mode' => $mode->value,
                'label' => $mode->label(),
                'people' => 0,
                'kilometres' => 0,
                'kilograms' => 0.0,
            ];
            $byMode[$mode->value]['people']++;
            $byMode[$mode->value]['kilometres'] += $kilometres;
            $byMode[$mode->value]['kilograms'] += $kilograms;
        }

        $meals = $event->meals_served ?? 0;
        $pages = $event->printed_pages ?? 0;
        $mealKilograms = $meals * self::KILOGRAMS_PER_MEAL;
        $printKilograms = $pages * self::KILOGRAMS_PER_PAGE;

        usort($byMode, fn (array $a, array $b): int => $b['kilograms'] <=> $a['kilograms']);

        return new CarbonFootprintData(
            travelKilograms: round($travelKilograms, 1),
            mealKilograms: round($mealKilograms, 1),
            printKilograms: round($printKilograms, 1),
            totalKilograms: round($travelKilograms + $mealKilograms + $printKilograms, 1),
            attendeeCount: $registrations->count(),
            declaredCount: $declared,
            mealsServed: $meals,
            printedPages: $pages,
            travelByMode: $byMode,
            carpoolOffers: $registrations->where('carpool_role', 'offers')->count(),
            carpoolSeekers: $registrations->where('carpool_role', 'seeks')->count(),
        );
    }

    /**
     * Ce que le covoiturage ferait gagner si tous ceux qui viennent seuls en
     * voiture partageaient leur trajet : c'est l'argument à leur montrer.
     */
    public function carpoolSaving(CarbonFootprintData $footprint): float
    {
        foreach ($footprint->travelByMode as $row) {
            if ($row['mode'] === TravelMode::Car->value) {
                $shared = $row['kilometres'] * TravelMode::Carpool->kilogramsPerKilometre();

                return round($row['kilograms'] - $shared, 1);
            }
        }

        return 0.0;
    }
}
