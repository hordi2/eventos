<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

/**
 * Choix par automatisation (accord explicite, T-045bis) : l'organisateur
 * choisit le canal au moment de configurer chaque automatisation — jamais
 * une préférence par contact qui déciderait à sa place.
 */
enum MessageChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
