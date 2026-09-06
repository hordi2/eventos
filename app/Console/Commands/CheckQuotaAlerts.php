<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Organization\Actions\CheckQuotaAlerts as CheckQuotaAlertsAction;
use Illuminate\Console\Command;

final class CheckQuotaAlerts extends Command
{
    protected $signature = 'quota:check-alerts';

    protected $description = 'Envoie les alertes de quota à 80 % et 100 % aux organisations concernées (T-074)';

    public function handle(CheckQuotaAlertsAction $action): int
    {
        $action->handle();

        $this->info('Vérification des quotas terminée.');

        return self::SUCCESS;
    }
}
