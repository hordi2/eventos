<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\StripeNotConfiguredException;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Stripe\StripeClient;

/**
 * Portail Stripe (facture, moyen de paiement, annulation) : évite de
 * reconstruire une interface de gestion d'abonnement déjà fournie par
 * Stripe.
 */
final class CreateBillingPortalSession
{
    public function handle(Organization $organization, string $returnUrl, User $user): string
    {
        Gate::forUser($user)->authorize('manageBilling', $organization);

        if ($organization->stripe_customer_id === null) {
            throw StripeNotConfiguredException::forPlan($organization->plan->value);
        }

        $client = new StripeClient(config('services.stripe.secret'));

        $session = $client->billingPortal->sessions->create([
            'customer' => $organization->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }
}
