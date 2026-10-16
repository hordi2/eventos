<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;

/**
 * Ce que l'agence facture à ce client pour son abonnement (D10, décision
 * prise avec l'utilisateur) : un montant qu'Itaza se contente de retenir
 * pour calculer la marge de l'agence (prix de revente − coût de gros, voir
 * config/plans.php) — jamais traité ni encaissé par Itaza, l'agence le
 * règle avec son client hors de la plateforme (facture, virement...).
 */
final class SetClientResalePrice
{
    public function handle(Organization $agency, Organization $client, User $user, ?Money $resalePrice): Organization
    {
        Gate::forUser($user)->authorize('manageClients', $agency);
        abort_unless($client->managed_by_organization_id === $agency->id, 404);

        $client->update([
            'resale_price_minor' => $resalePrice?->amountMinor(),
            'resale_currency' => $resalePrice?->currency(),
        ]);

        return $client->fresh();
    }
}
