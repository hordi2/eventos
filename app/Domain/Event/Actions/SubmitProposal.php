<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Events\ProposalSubmitted;
use App\Domain\Event\Models\Proposal;
use App\Domain\Event\Models\ProposalCall;
use App\Domain\Event\Models\ProposalStatus;

/**
 * Sujet déposé depuis la page publique de l'appel (D6). Rien n'est décidé
 * ici : la proposition attend l'évaluation de l'organisateur.
 */
final class SubmitProposal
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ProposalCall $call, array $data): Proposal
    {
        $proposal = Proposal::query()->create([
            'organization_id' => $call->organization_id,
            'event_id' => $call->event_id,
            'proposal_call_id' => $call->id,
            'proposer_name' => $data['proposer_name'],
            'proposer_email' => $data['proposer_email'],
            'proposer_role' => $data['proposer_role'] ?? null,
            'proposer_company' => $data['proposer_company'] ?? null,
            'proposer_bio' => $data['proposer_bio'] ?? null,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'format' => $data['format'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'status' => ProposalStatus::Pending,
        ]);

        ProposalSubmitted::dispatch($proposal);

        return $proposal;
    }
}
