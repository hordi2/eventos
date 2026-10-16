<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * L'agence change le palier de gros d'un compte client (D10) : ce que ce
 * compte lui coûte, jamais ce qu'elle lui facture (resale_price_minor, sans
 * lien avec Stripe — voir SetClientResalePrice). L'article Stripe
 * correspondant vit dans l'abonnement de l'agence, jamais dans celui du
 * client (ManageClientSubscriptionItems).
 */
final class ChangeClientPlan
{
    public function __construct(
        private readonly ManageClientSubscriptionItems $manageClientSubscriptionItems,
    ) {}

    public function handle(Organization $agency, Organization $client, PlanTier $newPlan, User $user): Organization
    {
        Gate::forUser($user)->authorize('manageClients', $agency);
        abort_unless($client->managed_by_organization_id === $agency->id, 404);

        $this->manageClientSubscriptionItems->syncForPlanChange($agency, $client, $newPlan);

        $client->update(['plan' => $newPlan]);

        return $client->fresh();
    }
}
