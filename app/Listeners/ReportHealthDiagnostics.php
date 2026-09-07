<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Status\CheckSystemHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use RuntimeException;

/**
 * Fait échouer la route /up intégrée à Laravel (bootstrap/app.php,
 * `health: '/up'`) quand la base de données ou Redis ne répond pas — sans
 * ça, /up ne vérifiait que le démarrage du framework lui-même (T-076).
 */
final class ReportHealthDiagnostics
{
    public function __construct(
        private readonly CheckSystemHealth $checkSystemHealth,
    ) {}

    public function handle(DiagnosingHealth $event): void
    {
        $result = $this->checkSystemHealth->handle();

        if (! $result->isHealthy()) {
            throw new RuntimeException('Composant(s) en échec : '.implode(', ', array_keys(array_filter(
                $result->components,
                fn (bool $ok): bool => ! $ok,
            ))));
        }
    }
}
