<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Messaging\Models\PushSubscription;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\WebPush\SendWebPushNotification;
use App\Support\WebPush\VapidKeyPair;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Diffuse une notification push à tous les abonnés d'un événement, hors de
 * la requête qui la compose. Reçoit des identifiants, jamais de modèles
 * (même piège que SendGuestListInvitationsJob : un modèle cloisonné serait
 * relu avant que l'organisation courante soit posée).
 *
 * Une seule tentative : rejouer l'envoi notifierait deux fois les abonnés
 * déjà servis (la notification elle-même est sans charge utile, §4.4 ne
 * s'applique pas littéralement, mais le doublon reste une mauvaise
 * expérience à éviter).
 */
final class SendPushNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $eventId,
        public readonly int $pushNotificationId,
    ) {}

    public function handle(CurrentOrganization $currentOrganization): void
    {
        $currentOrganization->set($this->organizationId);

        $vapid = VapidKeyPair::fromConfig();

        if ($vapid === null) {
            Log::warning("Notification push {$this->pushNotificationId} non envoyée : VAPID n'est pas configuré.");

            return;
        }

        $sender = new SendWebPushNotification($vapid);

        PushSubscription::query()
            ->where('event_id', $this->eventId)
            ->each(function (PushSubscription $subscription) use ($sender): void {
                try {
                    if (! $sender->handle($subscription->endpoint)) {
                        $subscription->delete();
                    }
                } catch (RuntimeException $exception) {
                    Log::warning("Échec de l'envoi d'une notification push.", [
                        'push_subscription_id' => $subscription->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });
    }
}
