<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Events\ProposalDecided;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Proposal;
use App\Domain\Event\Models\ProposalStatus;
use App\Domain\Event\Models\Speaker;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Décision de l'organisateur sur un sujet proposé (D6). Retenir un sujet
 * crée la fiche intervenant du proposant, avec son lien de portail : il n'y
 * a rien à recopier. Reprendre la décision ne crée jamais une seconde
 * fiche — c'est speaker_id qui en fait foi.
 */
final class DecideOnProposal
{
    public function handle(Proposal $proposal, Event $event, User $decider, ProposalStatus $status, ?string $reviewNote = null, ?string $decisionMessage = null): Proposal
    {
        Gate::forUser($decider)->authorize('update', $event);

        DB::transaction(function () use ($proposal, $event, $decider, $status, $reviewNote, $decisionMessage): void {
            $proposal->update([
                'status' => $status,
                'review_note' => $reviewNote,
                'decision_message' => $decisionMessage,
                'decided_at' => CarbonImmutable::now(),
                'decided_by' => $decider->id,
                'speaker_id' => $status === ProposalStatus::Accepted
                    ? ($proposal->speaker_id ?? $this->createSpeaker($proposal, $event)->id)
                    : $proposal->speaker_id,
            ]);
        });

        ProposalDecided::dispatch($proposal->refresh());

        return $proposal;
    }

    private function createSpeaker(Proposal $proposal, Event $event): Speaker
    {
        return Speaker::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'name' => $proposal->proposer_name,
            'role' => $proposal->proposer_role,
            'company' => $proposal->proposer_company,
            'email' => $proposal->proposer_email,
            'bio' => $proposal->proposer_bio,
            'position' => Speaker::query()->where('event_id', $event->id)->count(),
        ]);
    }
}
