<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Organization\Actions\ProcessDunning;
use Illuminate\Console\Command;

final class ProcessBillingDunning extends Command
{
    protected $signature = 'billing:process-dunning';

    protected $description = "Envoie les relances d'échec de paiement (J+1, J+3, J+7) et applique la restriction (T-074)";

    public function handle(ProcessDunning $action): int
    {
        $action->handle();

        $this->info('Relances de facturation traitées.');

        return self::SUCCESS;
    }
}
