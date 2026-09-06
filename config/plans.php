<?php

declare(strict_types=1);

/**
 * Définition des trois plans du MVP (T-074, M0.4) : quotas mensuels par
 * organisation et Price ID Stripe du plan payant correspondant. `null` sur
 * un quota signifie illimité. Les Price ID sont des variables
 * d'environnement (voir .env.example) : le tableau de bord Stripe n'a pas
 * encore été configuré au moment où ce fichier est écrit — toute action de
 * paiement d'abonnement échoue proprement (voir CreateBillingCheckoutSession)
 * tant qu'ils ne sont pas renseignés, plutôt que de planter au démarrage.
 *
 * `price_label` (29 $/99 $ par mois) est un espace réservé arbitraire : pas
 * de tarification définie par le produit à ce stade, à remplacer avant toute
 * mise en production.
 */
return [

    'free' => [
        'registrations_per_month' => 100,
        'emails_per_month' => 500,
        'active_events' => 1,
        'stripe_price' => null,
        'price_label' => 'Gratuit',
    ],

    'pro' => [
        'registrations_per_month' => 1000,
        'emails_per_month' => 5000,
        'active_events' => 10,
        'stripe_price' => env('STRIPE_PRICE_PRO'),
        'price_label' => '29 $/mois',
    ],

    'business' => [
        'registrations_per_month' => null,
        'emails_per_month' => null,
        'active_events' => null,
        'stripe_price' => env('STRIPE_PRICE_BUSINESS'),
        'price_label' => '99 $/mois',
    ],

];
