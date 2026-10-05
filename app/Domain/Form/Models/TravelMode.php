<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Comment un participant est venu (D12).
 *
 * Les facteurs sont des ordres de grandeur en kilogrammes d'équivalent CO₂
 * par kilomètre et par personne, du même registre que la Base Carbone de
 * l'ADEME. Ils servent à donner une échelle, pas à produire un bilan
 * réglementaire : le rapport le dit lui-même.
 */
enum TravelMode: string
{
    case Foot = 'foot';
    case Bicycle = 'bicycle';
    case PublicTransport = 'public_transport';
    case Carpool = 'carpool';
    case Car = 'car';
    case Plane = 'plane';

    public function label(): string
    {
        return match ($this) {
            self::Foot => 'À pied',
            self::Bicycle => 'À vélo',
            self::PublicTransport => 'Transport en commun',
            self::Carpool => 'En covoiturage',
            self::Car => 'En voiture, seul',
            self::Plane => 'En avion',
        };
    }

    /**
     * Kilogrammes d'équivalent CO₂ par kilomètre et par personne.
     */
    public function kilogramsPerKilometre(): float
    {
        return match ($this) {
            self::Foot, self::Bicycle => 0.0,
            self::PublicTransport => 0.03,
            // Une voiture partagée à trois, c'est le tiers de l'empreinte.
            self::Carpool => 0.064,
            self::Car => 0.192,
            self::Plane => 0.258,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode): array => ['value' => $mode->value, 'label' => $mode->label()],
            self::cases(),
        );
    }
}
