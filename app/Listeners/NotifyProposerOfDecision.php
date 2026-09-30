<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Event\Events\ProposalDecided;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\ProposalStatus;
use App\Mail\ProposalDecisionMail;
use Illuminate\Support\Facades\Mail;

/**
 * Réponse au proposant une fois le sujet tranché (D6). Seul le message
 * écrit pour lui est transmis : la note d'évaluation, elle, ne quitte
 * jamais l'équipe.
 */
final class NotifyProposerOfDecision
{
    public function handle(ProposalDecided $decided): void
    {
        $proposal = $decided->proposal;

        if ($proposal->status === ProposalStatus::Pending) {
            return;
        }

        $event = Event::query()->with('organization')->findOrFail($proposal->event_id);

        Mail::to($proposal->proposer_email)->queue(new ProposalDecisionMail(
            accepted: $proposal->status === ProposalStatus::Accepted,
            proposerName: $proposal->proposer_name,
            organizationName: $event->organization->name,
            eventTitle: $event->title,
            proposalTitle: $proposal->title,
            note: $proposal->decision_message,
        ));
    }
}
