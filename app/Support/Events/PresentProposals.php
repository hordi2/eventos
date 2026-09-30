<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Proposal;
use App\Domain\Event\Models\ProposalCall;
use App\Domain\Event\Models\ProposalStatus;

/**
 * Sujets proposés pour un événement (D6), tels que les voit
 * l'organisateur : les plus récents d'abord, ceux qui attendent en tête.
 */
final class PresentProposals
{
    /**
     * @return list<array{id: int, proposerName: string, proposerEmail: string, proposerRole: ?string, proposerCompany: ?string, proposerBio: ?string, title: string, summary: string, format: string, duration: ?int, status: string, reviewNote: ?string, decisionMessage: ?string, submittedAt: string, speakerId: ?int}>
     */
    public function handle(Event $event): array
    {
        return Proposal::query()
            ->where('event_id', $event->id)
            // Ce qui attend une décision passe devant.
            ->orderByRaw('case when status = ? then 0 else 1 end', [ProposalStatus::Pending->value])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'proposerName' => $proposal->proposer_name,
                'proposerEmail' => $proposal->proposer_email,
                'proposerRole' => $proposal->proposer_role,
                'proposerCompany' => $proposal->proposer_company,
                'proposerBio' => $proposal->proposer_bio,
                'title' => $proposal->title,
                'summary' => $proposal->summary,
                'format' => $proposal->format->label(),
                'duration' => $proposal->duration_minutes,
                'status' => $proposal->status->value,
                'reviewNote' => $proposal->review_note,
                'decisionMessage' => $proposal->decision_message,
                'submittedAt' => $proposal->created_at?->setTimezone($event->timezone)->translatedFormat('j F Y') ?? '',
                'speakerId' => $proposal->speaker_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Réglages de l'appel, pour l'écran de l'organisateur. Un événement
     * sans appel enregistré en reçoit un fermé, jamais ouvert par surprise.
     *
     * @return array{isOpen: bool, intro: ?string, closesAt: ?string, acceptsProposals: bool}
     */
    public function call(?ProposalCall $call, Event $event): array
    {
        return [
            'isOpen' => $call !== null && $call->is_open,
            'intro' => $call?->intro,
            // Le champ de formulaire attend une date locale, dans le fuseau
            // de l'événement (règle 4.3).
            'closesAt' => $call?->closes_at?->setTimezone($event->timezone)->format('Y-m-d\TH:i'),
            'acceptsProposals' => $call !== null && $call->acceptsProposals(),
        ];
    }
}
