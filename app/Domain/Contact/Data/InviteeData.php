<?php

declare(strict_types=1);

namespace App\Domain\Contact\Data;

/**
 * Invité tel que l'organisateur le saisit dans la liste d'un événement.
 * Un nom complet et/ou une adresse e-mail suffisent à l'identifier.
 * companionsAllowed nul = illimité (CompanionAllowance). whatsappConsent :
 * l'invité a accepté de recevoir des messages WhatsApp (sans cet accord,
 * aucune invitation ne lui part par ce canal).
 */
final class InviteeData
{
    /**
     * @param  list<string>  $tags  noms des tags
     */
    public function __construct(
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $groupKey,
        public readonly ?int $companionsAllowed,
        public readonly ?string $ccEmail,
        public readonly array $tags = [],
        public readonly bool $whatsappConsent = false,
    ) {}
}
