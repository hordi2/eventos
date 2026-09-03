<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Organization\Models\Organization;
use App\Jobs\SendEmailAutomationJob;
use App\Jobs\SendWhatsappAutomationJob;
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
 *
 * $templateId désigne un EmailTemplate ou un WhatsappTemplate selon
 * $channel : à l'appelant (le contrôleur, via le Form Request) de valider
 * que l'ID correspond bien à un modèle du bon type pour ce canal — cette
 * action ne charge d'ailleurs jamais le modèle, seulement son ID.
 */
final class CreateMessageAutomation
{
    public function handle(
        Organization $organization,
        User $creator,
        int $eventId,
        MessageChannel $channel,
        int $templateId,
        MessageAutomationType $type,
        ?CarbonImmutable $scheduledAt,
        ?EventSegment $segment = null,
    ): MessageAutomation {
        Gate::forUser($creator)->authorize('create', [MessageAutomation::class, $organization]);

        // Une confirmation en double enverrait le même message deux fois à
        // chaque inscription (App\Listeners\SendConfirmation*) : la règle
        // active doit être annulée avant d'en recréer une, quel que soit le
        // canal de l'ancienne comme de la nouvelle. Les autres types
        // (invitation, rappels) peuvent légitimement se répéter — une
        // relance en plusieurs vagues n'a rien d'une erreur.
        if ($type === MessageAutomationType::Confirmation) {
            $alreadyActive = MessageAutomation::query()
                ->where('event_id', $eventId)
                ->where('type', MessageAutomationType::Confirmation)
                ->where('status', MessageAutomationStatus::Active)
                ->exists();

            if ($alreadyActive) {
                throw new LogicException('Une confirmation automatique est déjà active pour cet événement : annule-la avant d\'en créer une autre.');
            }
        }

        $automation = MessageAutomation::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'channel' => $channel,
            'email_template_id' => $channel === MessageChannel::Email ? $templateId : null,
            'whatsapp_template_id' => $channel === MessageChannel::Whatsapp ? $templateId : null,
            'created_by' => $creator->id,
            'type' => $type,
            // Un segment forcé par le type (rappels, remerciement) prime
            // toujours sur celui choisi par l'organisateur, le cas échéant.
            'segment' => $type->forcedSegment() ?? $segment,
            'scheduled_at' => $scheduledAt,
            'status' => $type->isScheduledByDate() ? MessageAutomationStatus::Scheduled : MessageAutomationStatus::Active,
        ]);

        if ($type->isScheduledByDate()) {
            match ($channel) {
                MessageChannel::Email => SendEmailAutomationJob::dispatch($automation->id, $organization->id)->delay($scheduledAt),
                MessageChannel::Whatsapp => SendWhatsappAutomationJob::dispatch($automation->id, $organization->id)->delay($scheduledAt),
            };
        }

        return $automation;
    }
}
