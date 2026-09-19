<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\MessageChannel;
use App\Support\GuestList\SendGuestListInvitations;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Envoi groupé des invitations, hors de la requête qui le confirme. Reçoit
 * des identifiants, jamais de modèles (même piège que
 * ProcessContactImportJob : un modèle cloisonné serait relu avant que
 * l'organisation courante soit posée).
 *
 * Une seule tentative : rejouer l'envoi enverrait deux fois la même
 * invitation aux invités déjà servis.
 */
final class SendGuestListInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /**
     * @param  list<int>  $inviteeIds
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly int $eventId,
        public readonly string $channel,
        public readonly int $templateId,
        public readonly array $inviteeIds,
    ) {}

    public function handle(CurrentOrganization $currentOrganization, SendGuestListInvitations $sendGuestListInvitations): void
    {
        $currentOrganization->set($this->organizationId);

        $sendGuestListInvitations->handle(
            Event::query()->findOrFail($this->eventId),
            MessageChannel::from($this->channel),
            $this->templateId,
            $this->inviteeIds,
        );
    }
}
