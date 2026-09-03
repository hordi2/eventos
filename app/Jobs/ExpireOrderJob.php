<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ticketing\Actions\ExpireOrder;
use App\Domain\Ticketing\Models\Order;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Libère la réservation de stock d'une commande à l'échéance des 15 minutes
 * (M5.4), dispatché avec un delay() par CreateOrder plutôt que scanné par
 * une tâche planifiée — pas de scheduling en place dans le projet à ce
 * stade, et un job différé par commande est le mécanisme le plus direct.
 *
 * Reçoit des ID, jamais les modèles : même piège que SendEmailAutomationJob
 * (T-045) — CurrentOrganization doit être positionné avant que le modèle
 * ne soit résolu, sans quoi son global scope échoue.
 *
 * Idempotent (§4.4 CLAUDE.md) : ExpireOrder ne fait rien si la commande
 * n'est plus "pending" (déjà payée, échouée ou déjà expirée).
 */
final class ExpireOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $orderId,
        private readonly int $organizationId,
    ) {}

    public function handle(CurrentOrganization $currentOrganization, ExpireOrder $expireOrder): void
    {
        $currentOrganization->set($this->organizationId);

        $order = Order::query()->find($this->orderId);

        if ($order === null) {
            return;
        }

        $expireOrder->handle($order);
    }
}
