<?php

declare(strict_types=1);

namespace App\Support\Webhooks;

/**
 * Événements métier réellement souscriptibles par un webhook sortant (v1) :
 * limités à ceux qui correspondent à un événement Laravel déjà émis dans le
 * code (App\Domain\Form\Events, App\Support\Capacity\Events) — pas de
 * paiement ni de check-in pour l'instant, ces domaines n'émettent encore
 * aucun événement Laravel à écouter (limite connue, voir le backlog).
 */
enum WebhookEvent: string
{
    case RegistrationCreated = 'registration.created';
    case RegistrationUpdated = 'registration.updated';
    case RegistrationCancelled = 'registration.cancelled';
    case WaitlistPromoted = 'waitlist.promoted';

    public function label(): string
    {
        return match ($this) {
            self::RegistrationCreated => 'Nouvelle inscription',
            self::RegistrationUpdated => 'Inscription modifiée',
            self::RegistrationCancelled => 'Inscription annulée',
            self::WaitlistPromoted => "Passage de la liste d'attente à une place confirmée",
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
