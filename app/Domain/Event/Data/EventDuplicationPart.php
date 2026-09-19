<?php

declare(strict_types=1);

namespace App\Domain\Event\Data;

/**
 * Ce qu'un organisateur choisit d'emporter en dupliquant un événement
 * (M1.1.3 du CDC). Chaque module recopie sa propre partie en écoutant
 * EventDuplicated.
 */
enum EventDuplicationPart: string
{
    case Forms = 'forms';
    case Page = 'page';
    case Tickets = 'tickets';
    case GuestList = 'guest_list';
    case Messages = 'messages';

    public function label(): string
    {
        return match ($this) {
            self::Forms => 'Formulaires',
            self::Page => "Page de l'événement",
            self::Tickets => 'Billets et tarifs',
            self::GuestList => "Liste d'invités",
            self::Messages => 'E-mails et WhatsApp automatiques',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Forms => "Questions, écrans et thème, publiés comme l'original.",
            self::Page => 'Bannière, programme et questions fréquentes.',
            self::Tickets => 'Types de billets et paliers de prix, dates décalées.',
            self::GuestList => 'Mêmes invités et groupes, avec de nouveaux liens personnels, sans leurs réponses.',
            self::Messages => 'Reprogrammés aux dates décalées ; ceux dont la date serait déjà passée sont ignorés.',
        };
    }

    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $part): array => ['value' => $part->value, 'label' => $part->label(), 'hint' => $part->hint()],
            self::cases(),
        );
    }
}
