<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Première tâche planifiée du projet (T-053, AC : « Réconciliation
// quotidienne automatique avec le fournisseur ») — filet de sécurité pour
// les confirmations Mobile Money dont le webhook ne serait jamais arrivé.
Schedule::command('payments:reconcile-flutterwave')->daily();

// T-074 : alertes de quota (AC « 80 % et 100 % ») et relances d'échec de
// paiement (AC « J+1, J+3, J+7, puis restriction »).
Schedule::command('quota:check-alerts')->daily();
Schedule::command('billing:process-dunning')->daily();

// T-075 : purge RGPD des contacts inactifs au-delà de la durée de
// conservation configurée (config/gdpr.php, AC « suppression automatique
// après la durée définie »).
Schedule::command('gdpr:purge-expired-contacts')->daily();
