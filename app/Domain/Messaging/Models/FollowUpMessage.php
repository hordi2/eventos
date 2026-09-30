<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

/**
 * Messages que l'application envoie d'elle-même à un invité, en dehors des
 * campagnes de l'organisateur : ils partent par e-mail, par WhatsApp, ou
 * par les deux, selon le réglage de l'organisation.
 */
enum FollowUpMessage: string
{
    case RegistrationApproved = 'registration_approved';
    case RegistrationRejected = 'registration_rejected';
    case DonationReceipt = 'donation_receipt';
    case RejectedFile = 'rejected_file';

    public function label(): string
    {
        return match ($this) {
            self::RegistrationApproved => 'Inscription acceptée',
            self::RegistrationRejected => 'Demande refusée',
            self::DonationReceipt => 'Reçu de don',
            self::RejectedFile => 'Fichier joint refusé',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::RegistrationApproved => "Quand vous acceptez une demande d'inscription.",
            self::RegistrationRejected => 'Quand vous refusez une demande.',
            self::DonationReceipt => 'Quand un don est réglé, avec son reçu.',
            self::RejectedFile => "Quand l'antivirus refuse un fichier envoyé par un invité.",
        };
    }

    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $message): array => ['value' => $message->value, 'label' => $message->label(), 'hint' => $message->hint()],
            self::cases(),
        );
    }
}
