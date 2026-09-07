<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Livre un événement à un webhook sortant (page Paramètres → Intégrations
 * & API). `deliveryId` est généré une seule fois au premier essai et reste
 * identique sur les réessais (règle 4.4 : rejouable sans dupliquer côté
 * destinataire, qui peut dédupliquer sur X-Itaza-Delivery-Id).
 *
 * Signature HMAC-SHA256 du corps brut avec le secret du webhook — même
 * principe que les webhooks entrants Stripe/Twilio (PostmarkWebhookController
 * etc.), dans l'autre sens.
 */
final class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 600, 1800];

    public int $timeout = 15;

    private readonly string $deliveryId;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly int $webhookId,
        public readonly string $eventName,
        public readonly array $payload,
    ) {
        $this->deliveryId = (string) Str::uuid();
    }

    public function handle(CurrentOrganization $currentOrganization): void
    {
        $currentOrganization->set($this->organizationId);

        $webhook = Webhook::query()->find($this->webhookId);

        if ($webhook === null || ! $webhook->is_active) {
            return;
        }

        $body = json_encode([
            'event' => $this->eventName,
            'delivery_id' => $this->deliveryId,
            'data' => $this->payload,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $body, $webhook->secret);

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'X-Itaza-Event' => $this->eventName,
                    'X-Itaza-Delivery-Id' => $this->deliveryId,
                    'X-Itaza-Signature' => "sha256={$signature}",
                ])
                ->timeout(10)
                ->post($webhook->url);

            $webhook->update([
                'last_delivery_at' => now(),
                'last_delivery_status' => $response->successful() ? 'success' : "http_{$response->status()}",
            ]);

            $response->throw();
        } catch (Throwable $exception) {
            $webhook->update([
                'last_delivery_at' => now(),
                'last_delivery_status' => 'failed',
            ]);

            throw $exception;
        }
    }
}
