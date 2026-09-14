<?php

declare(strict_types=1);

namespace App\Support\Webhooks;

/**
 * Charge utile d'exemple renvoyée à Zapier/n8n pendant la configuration
 * d'un déclencheur, quand aucun événement réel n'a encore été livré : ces
 * outils ont besoin d'un échantillon pour proposer les champs à mapper.
 *
 * Doit rester strictement aligné sur ce qu'envoient réellement les
 * listeners (App\Listeners\Webhooks\*) et sur l'enveloppe construite par
 * DeliverWebhookJob — un exemple qui divergerait produirait des mappings
 * cassés chez l'utilisateur, sans erreur visible de notre côté.
 */
final class WebhookSamplePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function for(WebhookEvent $event): array
    {
        return [
            'event' => $event->value,
            'delivery_id' => '00000000-0000-4000-8000-000000000000',
            'data' => self::data($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(WebhookEvent $event): array
    {
        return match ($event) {
            WebhookEvent::RegistrationCreated,
            WebhookEvent::RegistrationUpdated,
            WebhookEvent::RegistrationCancelled => [
                'registration_id' => 1,
                'event_id' => 1,
                'status' => $event === WebhookEvent::RegistrationCancelled ? 'cancelled' : 'confirmed',
                'email' => 'invite@example.com',
                'first_name' => 'Grace',
                'last_name' => 'Mukendi',
                'submitted_at' => '2026-09-08T14:30:00+00:00',
            ],
            WebhookEvent::WaitlistPromoted => [
                'waitlist_entry_id' => 1,
                'holder_type' => 'registration',
                'holder_id' => 1,
                'reservation_key' => 'evt-1-registration-1',
            ],
        };
    }
}
