<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\TicketsUnavailableException;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Tente l'achat d'un billet puis quitte, en imprimant le résultat en JSON.
 *
 * N'existe que pour piloter le test de concurrence de CreateOrder (T-051,
 * AC "test de concurrence sur les 5 derniers billets") — même raison que
 * capacity:attempt-reservation (T-024) : PHP n'a pas pcntl dans cette
 * image, donc chaque tentative est un vrai processus `php artisan`
 * indépendant, avec sa propre connexion PostgreSQL et Redis.
 *
 * Contrairement à capacity:attempt-reservation, qui pilote directement le
 * moteur générique déjà éprouvé (T-024), celui-ci passe par CreateOrder en
 * entier : il prouve que le pipeline de plus haut niveau (création de
 * commande + réservation du quota par type de billet et par palier) ne
 * réintroduit pas de condition de course, pas seulement le primitif isolé.
 */
final class AttemptOrderPurchase extends Command
{
    protected $signature = 'orders:attempt-purchase
        {organizationId : ID de l\'organisation}
        {eventId : ID de l\'événement}
        {ticketTypeId : ID du type de billet}
        {reservationKey : Clé de réservation, unique par tentative}
        {--quantity=1 : Quantité de billets pour cette tentative}';

    protected $description = 'Tente un achat de billet et imprime le résultat en JSON (outil de test de concurrence T-051)';

    public function handle(CreateOrder $createOrder, CurrentOrganization $currentOrganization): int
    {
        $organizationId = (int) $this->argument('organizationId');
        $currentOrganization->set($organizationId);

        try {
            $order = $createOrder->handle(
                $organizationId,
                (int) $this->argument('eventId'),
                ['name' => 'Concurrence', 'email' => $this->argument('reservationKey').'@example.com'],
                [['ticket_type_id' => (int) $this->argument('ticketTypeId'), 'quantity' => (int) $this->option('quantity')]],
                (string) $this->argument('reservationKey'),
            );

            $this->output->write(json_encode(['outcome' => 'accepted', 'order_id' => $order->id]));
        } catch (TicketsUnavailableException) {
            $this->output->write(json_encode(['outcome' => 'rejected', 'order_id' => null]));
        }

        return self::SUCCESS;
    }
}
