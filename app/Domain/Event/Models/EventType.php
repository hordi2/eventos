<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Taxonomie reprise de la bibliothèque de modèles (M1.4 du CDC), groupée
 * par catégorie : entreprise, associatif, éducation, religieux, personnel,
 * agence.
 */
enum EventType: string
{
    // Entreprise
    case Conference = 'conference';
    case ProductLaunch = 'product_launch';
    case Seminar = 'seminar';
    case GeneralAssembly = 'general_assembly';
    case Kickoff = 'kickoff';

    // Associatif
    case Gala = 'gala';
    case Fundraiser = 'fundraiser';

    // Éducation
    case Graduation = 'graduation';
    case OpenHouse = 'open_house';
    case ParentsMeeting = 'parents_meeting';

    // Religieux
    case Religious = 'religious';

    // Personnel
    case Wedding = 'wedding';
    case Birthday = 'birthday';
    case Baptism = 'baptism';
    case Memorial = 'memorial';

    // Agence / autre
    case Agency = 'agency';
    case Other = 'other';

    /**
     * Regroupement en deux catégories (accord explicite) : personnel couvre
     * les événements de vie individuels/familiaux (mariage, anniversaire,
     * baptême, deuil) et religieux (communautaire/familial, plus proche de
     * "personnel" que d'"entreprise" dans ce contexte) — tout le reste
     * (entreprise, associatif, éducation, agence/autre) reste "corporate".
     */
    public function label(): string
    {
        return match ($this) {
            self::Conference => 'Conférence',
            self::ProductLaunch => 'Lancement de produit',
            self::Seminar => 'Séminaire',
            self::GeneralAssembly => 'Assemblée générale',
            self::Kickoff => 'Kick-off',
            self::Gala => 'Gala',
            self::Fundraiser => 'Collecte de fonds',
            self::Graduation => 'Remise de diplômes',
            self::OpenHouse => 'Journée portes ouvertes',
            self::ParentsMeeting => 'Réunion de parents',
            self::Religious => 'Événement religieux',
            self::Wedding => 'Mariage',
            self::Birthday => 'Anniversaire',
            self::Baptism => 'Baptême',
            self::Memorial => 'Deuil ou commémoration',
            self::Agency => "Événement d'agence",
            self::Other => 'Autre',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }

    public function category(): EventCategory
    {
        return match ($this) {
            self::Wedding, self::Birthday, self::Baptism, self::Memorial, self::Religious => EventCategory::Personal,
            default => EventCategory::Corporate,
        };
    }
}
