<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Data\EventRegistrationContext;
use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Actions\GetPlanQuotas;

/**
 * Extrait de RegistrationController (parcours web) pour être réutilisé par
 * tout autre canal de réponse — WhatsApp entrant notamment — sans dupliquer
 * la résolution du plan, des quotas et des sessions secondaires.
 */
final class BuildEventRegistrationContext
{
    public function __construct(
        private readonly GetEffectivePlan $getEffectivePlan,
        private readonly GetPlanQuotas $getPlanQuotas,
        private readonly BuildSubEventContexts $buildSubEventContexts,
    ) {}

    public function handle(Event $event): EventRegistrationContext
    {
        $event->loadMissing('organization');
        $plan = $this->getEffectivePlan->handle($event->organization);
        $quotas = $this->getPlanQuotas->handle($plan);

        return new EventRegistrationContext(
            eventId: $event->id,
            organizationId: $event->organization_id,
            capacity: $event->capacity,
            allowWaitlist: $event->allow_waitlist,
            registrationOpensAt: $event->registration_opens_at,
            registrationClosesAt: $event->registration_closes_at,
            timezone: $event->timezone,
            registrationClosedMessage: $event->registration_closed_message,
            organizationMonthlyRegistrationQuota: $quotas->registrationsPerMonth,
            subEvents: $this->buildSubEventContexts->handle($event),
            requiresApproval: $event->requires_approval,
        );
    }
}
