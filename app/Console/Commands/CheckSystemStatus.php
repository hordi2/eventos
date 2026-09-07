<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Status\RecordSystemStatus;
use Illuminate\Console\Command;

final class CheckSystemStatus extends Command
{
    protected $signature = 'status:check-health';

    protected $description = 'Vérifie la santé de la plateforme et alerte en cas de changement d\'état (T-076)';

    public function handle(RecordSystemStatus $action): int
    {
        $check = $action->handle();

        $this->info($check->is_healthy ? 'Plateforme opérationnelle.' : 'Plateforme dégradée.');

        return self::SUCCESS;
    }
}
