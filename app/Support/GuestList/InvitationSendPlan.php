<?php

declare(strict_types=1);

namespace App\Support\GuestList;

/**
 * Ce qu'un envoi groupé ferait, montré avant de confirmer : qui recevra le
 * message, et combien d'invités n'en recevront pas, avec la raison.
 */
final class InvitationSendPlan
{
    /**
     * @param  list<int>  $recipientIds  invités à qui le message partira
     * @param  int  $missing  sans e-mail, ou sans numéro WhatsApp
     * @param  int  $excluded  désabonnés, adresse invalide, ou sans accord WhatsApp
     * @param  int  $coveredByGroup  membres d'un groupe dont un autre membre reçoit le message
     */
    public function __construct(
        public readonly array $recipientIds,
        public readonly int $missing,
        public readonly int $excluded,
        public readonly int $coveredByGroup,
    ) {}

    /**
     * @return array{recipients: int, missing: int, excluded: int, coveredByGroup: int}
     */
    public function summary(): array
    {
        return [
            'recipients' => count($this->recipientIds),
            'missing' => $this->missing,
            'excluded' => $this->excluded,
            'coveredByGroup' => $this->coveredByGroup,
        ];
    }
}
