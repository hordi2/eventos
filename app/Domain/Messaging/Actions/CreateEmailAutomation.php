<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Messaging\Models\EmailAutomationStatus;
use App\Domain\Messaging\Models\EmailAutomationType;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;
use App\Jobs\SendEmailAutomationJob;
use App\Models\User;
use App\Support\Segments\EventSegment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * event_id et scheduled_at arrivent déjà résolus par l'appelant (jamais un
 * Event ici) : Domain/Messaging ne dépend d'aucun modèle de Domain/Event
 * (section 3 du CLAUDE.md) — la conversion "J-7 de l'événement" en horodatage
 * UTC se fait donc dans le contrôleur, seul endroit autorisé à traverser les
 * deux modules.
 */
final class CreateEmailAutomation
{
    public function handle(
        Organization $organization,
        User $creator,
        int $eventId,
        EmailTemplate $template,
        EmailAutomationType $type,
        ?CarbonImmutable $scheduledAt,
        ?EventSegment $segment = null,
    ): EmailAutomation {
        Gate::forUser($creator)->authorize('create', [EmailAutomation::class, $organization]);

        // Une confirmation en double enverrait le même e-mail deux fois à
        // chaque inscription (App\Listeners\SendConfirmationEmail) : la
        // règle active doit être annulée avant d'en recréer une. Les autres
        // types (invitation, rappels) peuvent légitimement se répéter —
        // une relance en plusieurs vagues n'a rien d'une erreur.
        if ($type === EmailAutomationType::Confirmation) {
            $alreadyActive = EmailAutomation::query()
                ->where('event_id', $eventId)
                ->where('type', EmailAutomationType::Confirmation)
                ->where('status', EmailAutomationStatus::Active)
                ->exists();

            if ($alreadyActive) {
                throw new LogicException('Une confirmation automatique est déjà active pour cet événement : annule-la avant d\'en créer une autre.');
            }
        }

        $automation = EmailAutomation::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'email_template_id' => $template->id,
            'created_by' => $creator->id,
            'type' => $type,
            // Un segment forcé par le type (rappels, remerciement) prime
            // toujours sur celui choisi par l'organisateur, le cas échéant.
            'segment' => $type->forcedSegment() ?? $segment,
            'scheduled_at' => $scheduledAt,
            'status' => $type->isScheduledByDate() ? EmailAutomationStatus::Scheduled : EmailAutomationStatus::Active,
        ]);

        if ($type->isScheduledByDate()) {
            SendEmailAutomationJob::dispatch($automation->id, $organization->id)->delay($scheduledAt);
        }

        return $automation;
    }
}
