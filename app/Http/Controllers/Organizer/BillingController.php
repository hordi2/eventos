<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\ChangeOrganizationPlan;
use App\Domain\Organization\Actions\CreateBillingCheckoutSession;
use App\Domain\Organization\Actions\CreateBillingPortalSession;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Domain\Organization\StripeNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Support\Billing\GetOrganizationUsage;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class BillingController extends Controller
{
    public function index(GetOrganizationUsage $getOrganizationUsage): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $usage = $getOrganizationUsage->handle($organization);

        return Inertia::render('Billing/Show', [
            'organization' => [
                'plan' => $organization->plan->value,
                'subscription_status' => $organization->subscription_status,
                'subscription_current_period_end' => $organization->subscription_current_period_end?->toIso8601String(),
                'dunning_stage' => $organization->dunning_stage,
                'restricted' => $organization->plan !== PlanTier::Free
                    && $organization->subscription_status === 'past_due'
                    && $organization->dunning_stage >= 3,
                'has_subscription' => $organization->stripe_subscription_id !== null,
            ],
            'usage' => [
                'registrations' => ['used' => $usage->registrationsThisMonth, 'quota' => $usage->registrationsQuota],
                'emails' => ['used' => $usage->emailsThisMonth, 'quota' => $usage->emailsQuota],
                'active_events' => ['used' => $usage->activeEvents, 'quota' => $usage->activeEventsQuota],
                'percentages' => $usage->percentages(),
            ],
            'plans' => array_map(fn (PlanTier $plan): array => [
                'value' => $plan->value,
                'label' => $plan->label(),
                'price_label' => config("plans.{$plan->value}.price_label"),
                'registrations_per_month' => config("plans.{$plan->value}.registrations_per_month"),
                'emails_per_month' => config("plans.{$plan->value}.emails_per_month"),
                'active_events' => config("plans.{$plan->value}.active_events"),
            ], PlanTier::cases()),
        ]);
    }

    public function checkout(Request $request, PlanTier $plan, CreateBillingCheckoutSession $action): Response
    {
        try {
            $url = $action->handle(
                organization: $this->currentOrganization(),
                plan: $plan,
                successUrl: route('billing.index'),
                cancelUrl: route('billing.index'),
                user: $request->user(),
            );
        } catch (StripeNotConfiguredException $exception) {
            return back()->withErrors(['plan' => $exception->getMessage()]);
        }

        return Inertia::location($url);
    }

    public function change(Request $request, PlanTier $plan, ChangeOrganizationPlan $action): RedirectResponse
    {
        try {
            $action->handle($this->currentOrganization(), $plan, $request->user());
        } catch (StripeNotConfiguredException $exception) {
            return back()->withErrors(['plan' => $exception->getMessage()]);
        }

        return back();
    }

    public function portal(Request $request, CreateBillingPortalSession $action): Response
    {
        try {
            $url = $action->handle($this->currentOrganization(), route('billing.index'), $request->user());
        } catch (StripeNotConfiguredException $exception) {
            return back()->withErrors(['plan' => $exception->getMessage()]);
        }

        return Inertia::location($url);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
