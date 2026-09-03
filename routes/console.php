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
