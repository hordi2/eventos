<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Actions\GetPlanQuotas;
use App\Domain\Organization\Models\Organization;
use App\Support\Billing\Data\OrganizationUsageData;
use Carbon\CarbonImmutable;

/**
 * Traverse Domain/Form, Domain/Messaging, Domain/Event et Domain/Organization :
 * ne peut vivre dans aucun d'eux (section 3 du CLAUDE.md), même
 * raisonnement que GetEventDashboardStats. Compteurs recalculés à la
 * demande plutôt que maintenus en continu (même choix que
 * ComputeEventSegmentContacts) : un mois compte peu de lignes à agréger,
 * pas besoin d'un compteur dénormalisé sujet à dérive.
 */
final class GetOrganizationUsage
{
    public function __construct(
        private readonly GetEffectivePlan $getEffectivePlan,
        private readonly GetPlanQuotas $getPlanQuotas,
    ) {}

    public function handle(Organization $organization): OrganizationUsageData
    {
        $quotas = $this->getPlanQuotas->handle($this->getEffectivePlan->handle($organization));
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();

        return new OrganizationUsageData(
            registrationsThisMonth: Registration::query()
                ->where('organization_id', $organization->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count(),
            registrationsQuota: $quotas->registrationsPerMonth,
            emailsThisMonth: EmailMessage::query()
                ->where('organization_id', $organization->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count(),
            emailsQuota: $quotas->emailsPerMonth,
            activeEvents: Event::query()
                ->where('organization_id', $organization->id)
                ->where('status', EventStatus::Published)
                ->count(),
            activeEventsQuota: $quotas->activeEvents,
        );
    }
}
