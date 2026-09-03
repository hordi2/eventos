<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Messaging\Models\EmailAutomationStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * N'annule rien côté file d'attente : le job déjà planifié
 * (SendEmailAutomationJob) relit le statut à son exécution et ne fait
 * rien s'il n'est plus "scheduled" — annuler ici suffit, jamais besoin de
 * retrouver ni de supprimer le job en attente (§4.4 du CLAUDE.md,
 * idempotence).
 */
final class CancelEmailAutomation
{
    public function handle(EmailAutomation $automation, User $actor): EmailAutomation
    {
        Gate::forUser($actor)->authorize('cancel', $automation);

        if (! in_array($automation->status, [EmailAutomationStatus::Scheduled, EmailAutomationStatus::Active], true)) {
            throw new LogicException("Cette automatisation n'est plus annulable (statut : {$automation->status->value}).");
        }

        $automation->update(['status' => EmailAutomationStatus::Cancelled]);

        return $automation;
    }
}
