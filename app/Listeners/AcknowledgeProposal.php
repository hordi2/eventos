<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Event\Events\ProposalSubmitted;
use App\Domain\Event\Models\Event;
use App\Mail\ProposalReceivedMail;
use Illuminate\Support\Facades\Mail;

/**
 * Accusé de réception au proposant (D6). Toujours par e-mail : il n'a pas
 * de compte, et son accord WhatsApp n'a jamais été recueilli — la règle de
 * consentement interdit de lui écrire sur ce canal.
 */
final class AcknowledgeProposal
{
    public function handle(ProposalSubmitted $submitted): void
    {
        $proposal = $submitted->proposal;
        $event = Event::query()->with('organization')->findOrFail($proposal->event_id);

        Mail::to($proposal->proposer_email)->queue(new ProposalReceivedMail(
            proposerName: $proposal->proposer_name,
            organizationName: $event->organization->name,
            eventTitle: $event->title,
            proposalTitle: $proposal->title,
        ));
    }
}
