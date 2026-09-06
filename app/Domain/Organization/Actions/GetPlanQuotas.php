<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Data\PlanQuotas;
use App\Domain\Organization\Models\PlanTier;

final class GetPlanQuotas
{
    public function handle(PlanTier $plan): PlanQuotas
    {
        /** @var array{registrations_per_month: ?int, emails_per_month: ?int, active_events: ?int} $config */
        $config = config("plans.{$plan->value}");

        return new PlanQuotas(
            registrationsPerMonth: $config['registrations_per_month'],
            emailsPerMonth: $config['emails_per_month'],
            activeEvents: $config['active_events'],
        );
    }
}
