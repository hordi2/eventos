<?php

declare(strict_types=1);

/**
 * Définition des 6 plans (T-074, M0.4 ; palier personnel/professionnel
 * distinct ajouté ensuite, demande utilisateur) : quotas mensuels par
 * organisation et Price ID Stripe du plan payant correspondant. `null` sur
 * un quota signifie illimité. Les Price ID sont des variables
 * d'environnement (voir .env.example) : le tableau de bord Stripe n'a pas
 * encore été configuré au moment où ce fichier est écrit — toute action de
 * paiement d'abonnement échoue proprement (voir CreateBillingCheckoutSession)
 * tant qu'ils ne sont pas renseignés, plutôt que de planter au démarrage.
 * Chaque Price ID payant reste à créer manuellement dans le tableau de bord
 * Stripe puis à coller dans `.env` — cette étape n'est pas automatisable
 * depuis le code.
 *
 * `registrations_per_month` pour les paliers personnels reprend le nombre
 * d'invités annoncé sur la carte (« jusqu'à 300/500 invités ») : la
 * plateforme ne suit pas de quota "invités par événement" séparé, seul le
 * volume d'inscriptions mensuel de l'organisation est mesuré aujourd'hui
 * (voir GetOrganizationUsage). `emails_per_month` est extrapolé à 5x le
 * volume d'inscriptions, comme sur les paliers gratuit/pro d'origine.
 */
return [

    'free' => [
        'registrations_per_month' => 100,
        'emails_per_month' => 500,
        'active_events' => 1,
        'stripe_price' => null,
        'price_label' => 'Gratuit',
    ],

    'personal_essential' => [
        'registrations_per_month' => 300,
        'emails_per_month' => 1500,
        'active_events' => 5,
        'stripe_price' => env('STRIPE_PRICE_PERSONAL_ESSENTIAL'),
        'price_label' => '8,9 $/mois',
    ],

    'personal_comfort' => [
        'registrations_per_month' => 500,
        'emails_per_month' => 2500,
        'active_events' => 10,
        'stripe_price' => env('STRIPE_PRICE_PERSONAL_COMFORT'),
        'price_label' => '15 $/mois',
    ],

    'professional_plus' => [
        'registrations_per_month' => 150,
        'emails_per_month' => 750,
        'active_events' => 5,
        'stripe_price' => env('STRIPE_PRICE_PROFESSIONAL_PLUS'),
        'price_label' => '35 $/mois',
    ],

    'professional_career' => [
        'registrations_per_month' => 500,
        'emails_per_month' => 2500,
        'active_events' => 15,
        'stripe_price' => env('STRIPE_PRICE_PROFESSIONAL_CAREER'),
        'price_label' => '99 $/mois',
    ],

    'professional_pro' => [
        'registrations_per_month' => 1500,
        'emails_per_month' => 7500,
        'active_events' => null,
        'stripe_price' => env('STRIPE_PRICE_PROFESSIONAL_PRO'),
        'price_label' => '289 $/mois',
    ],

];
