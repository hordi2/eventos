<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contact\Actions\FindOrCreateContact;
use App\Domain\Ticketing\Events\OrderPlaced;

/**
 * Pont entre Domain/Ticketing et Domain/Contact, hors des deux (section 3 du
 * CLAUDE.md), comme LinkRegistrationToContact : l'acheteur rejoint la base
 * de contacts de l'organisation et sa commande lui est reliée, ce qui permet
 * à l'effacement RGPD de l'atteindre.
 */
final class LinkOrderToContact
{
    public function __construct(
        private readonly FindOrCreateContact $findOrCreateContact,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        if ($order->contact_id !== null) {
            return;
        }

        // L'achat ne demande qu'un nom complet : le premier mot devient le
        // prénom. Cela ne sert qu'à créer une fiche absente — un contact déjà
        // connu garde ses noms.
        $parts = preg_split('/\s+/', trim($order->buyer_name), 2) ?: [];

        $contact = $this->findOrCreateContact->handle(
            $order->organization_id,
            $order->buyer_email,
            $parts[0] ?? null,
            $parts[1] ?? null,
            $order->buyer_phone_e164,
            consentSource: 'ticket_order',
        );

        $order->update(['contact_id' => $contact->id]);
    }
}
