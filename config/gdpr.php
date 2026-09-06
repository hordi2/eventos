<?php

declare(strict_types=1);

/**
 * Durées de conservation par type de donnée (T-075, RGPD §10.2 P-07/P-08 :
 * « durées de conservation paramétrables », « suppression automatique
 * après la durée définie »). Décomptées depuis la dernière activité
 * connue du contact (sa plus récente inscription, ou sa création si
 * aucune) — un contact resté actif ne doit jamais être anonymisé au seul
 * motif de son ancienneté.
 */
return [

    // 36 mois par défaut : assez long pour couvrir un cycle d'événements
    // annuels récurrents, conforme au principe de minimisation RGPD sans
    // effacer un contact encore pertinent pour l'organisation.
    'contact_retention_months' => env('GDPR_CONTACT_RETENTION_MONTHS', 36),

];
