<?php

declare(strict_types=1);

namespace App\Support\Status;

use App\Mail\StatusAlertMail;
use App\Support\Status\Models\SystemStatusCheck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Enregistre une vérification de santé et alerte STATUS_ALERT_EMAIL
 * uniquement lors d'un changement d'état par rapport à la dernière
 * vérification connue (T-076) — sans quoi un incident prolongé enverrait
 * un e-mail à chaque exécution planifiée.
 */
final class RecordSystemStatus
{
    public function __construct(
        private readonly CheckSystemHealth $checkSystemHealth,
    ) {}

    public function handle(): SystemStatusCheck
    {
        $result = $this->checkSystemHealth->handle();
        $previous = SystemStatusCheck::query()->latest('checked_at')->first();

        $check = SystemStatusCheck::query()->create([
            'checked_at' => CarbonImmutable::now(),
            'is_healthy' => $result->isHealthy(),
            'components' => $result->components,
        ]);

        $shouldAlert = $previous === null
            ? ! $result->isHealthy()
            : $previous->is_healthy !== $result->isHealthy();

        if ($shouldAlert) {
            $this->sendAlert($result->isHealthy(), $result->components);
        }

        return $check;
    }

    /**
     * @param  array<string, bool>  $components
     */
    private function sendAlert(bool $isHealthy, array $components): void
    {
        $recipient = config('services.status.alert_email');

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        Mail::to($recipient)->queue(new StatusAlertMail(
            $isHealthy,
            array_filter($components, fn (bool $ok): bool => ! $ok),
        ));
    }
}
