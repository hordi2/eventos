<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Postes de budget du cahier des charges (D7). « Autre » reste ouvert :
 * mieux vaut une ligne bien nommée dans « Autre » qu'un poste rangé de
 * force dans une catégorie qui ne lui va pas.
 */
enum BudgetCategory: string
{
    case Venue = 'venue';
    case Catering = 'catering';
    case Technical = 'technical';
    case Communication = 'communication';
    case Staff = 'staff';
    case Sponsoring = 'sponsoring';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Venue => 'Lieu',
            self::Catering => 'Traiteur',
            self::Technical => 'Technique',
            self::Communication => 'Communication',
            self::Staff => 'Personnel',
            self::Sponsoring => 'Sponsors et subventions',
            self::Other => 'Autre',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $category): array => ['value' => $category->value, 'label' => $category->label()], self::cases());
    }
}
