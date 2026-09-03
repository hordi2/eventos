<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ticketing\Actions\ReconcileMobileMoneyPayments;
use Illuminate\Console\Command;

final class ReconcileFlutterwavePayments extends Command
{
    protected $signature = 'payments:reconcile-flutterwave';

    protected $description = 'Réconcilie les paiements Mobile Money Flutterwave encore en attente avec le prestataire (T-053)';

    public function handle(ReconcileMobileMoneyPayments $reconcile): int
    {
        $reconcile->handle();

        $this->info('Réconciliation Flutterwave terminée.');

        return self::SUCCESS;
    }
}
