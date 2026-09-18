<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\EventInvitee;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Retrouve l'invitation d'un invité quand l'événement est réservé à sa liste
 * (liste fermée) : par son lien personnel, ou à défaut par son e-mail ou son
 * numéro WhatsApp — jamais par son nom, trop facile à deviner.
 */
final class IdentifyGuestInvitee
{
    public function byToken(int $eventId, string $token): ?EventInvitee
    {
        return EventInvitee::query()
            ->where('event_id', $eventId)
            ->where('invitation_token', $token)
            ->whereHas('contact')
            ->first();
    }

    /**
     * Une adresse (avec « @ ») est cherchée parmi les e-mails, sinon la
     * saisie est lue comme un numéro international.
     */
    public function byEmailOrPhone(int $eventId, string $input): ?EventInvitee
    {
        $input = trim($input);

        if (str_contains($input, '@')) {
            return $this->firstWhereContact($eventId, 'email', mb_strtolower($input));
        }

        $phone = self::normalizePhone($input);

        return $phone === null ? null : $this->firstWhereContact($eventId, 'phone_e164', $phone);
    }

    /**
     * « +243 81 234 5678 » ou « 243812345678 » : sans indicatif de pays, un
     * numéro local ne peut pas être rattaché à coup sûr.
     */
    public static function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse(str_starts_with($digits, '+') ? $digits : '+'.ltrim($digits, '0'), null);
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($number) ? $util->format($number, PhoneNumberFormat::E164) : null;
    }

    private function firstWhereContact(int $eventId, string $column, string $value): ?EventInvitee
    {
        return EventInvitee::query()
            ->where('event_id', $eventId)
            ->whereHas('contact', fn ($contact) => $contact->where($column, $value))
            ->orderBy('id')
            ->first();
    }
}
