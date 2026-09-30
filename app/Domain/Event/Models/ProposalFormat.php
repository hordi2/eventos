<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Forme du sujet proposé (D6). Le proposant choisit dans cette liste :
 * l'organisateur compare ainsi des propositions comparables.
 */
enum ProposalFormat: string
{
    case Talk = 'talk';
    case Workshop = 'workshop';
    case Panel = 'panel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Talk => 'Conférence',
            self::Workshop => 'Atelier',
            self::Panel => 'Table ronde',
            self::Other => 'Autre',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $format): array => ['value' => $format->value, 'label' => $format->label()], self::cases());
    }
}
