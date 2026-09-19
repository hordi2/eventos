<?php

declare(strict_types=1);

namespace App\Domain\Contact\Support;

use Carbon\CarbonImmutable;

/**
 * Accord WhatsApp saisi par l'organisateur depuis la liste d'invités : sa
 * source et sa date sont gardées avec lui, comme pour tout consentement
 * (preuve exigée par WhatsApp et par la protection des données). Un retrait
 * efface la date d'accord et garde la trace de la source.
 */
final class RecordWhatsappConsent
{
    public const SOURCE = 'guest_list';

    /**
     * @return array{whatsapp_consent: bool, whatsapp_consent_source: string, whatsapp_consent_at: CarbonImmutable|null}
     */
    public static function attributes(bool $granted): array
    {
        return [
            'whatsapp_consent' => $granted,
            'whatsapp_consent_source' => self::SOURCE,
            'whatsapp_consent_at' => $granted ? CarbonImmutable::now() : null,
        ];
    }
}
