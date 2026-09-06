<?php

declare(strict_types=1);

use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Actions\ProcessDunning;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Mail\PaymentFailedMail;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

it('envoie les relances J+1, J+3 et J+7 puis restreint le plan effectif (T-074)', function (): void {
    Mail::fake();
    ['organization' => $organization] = makeCheckInEvent(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($organization);
    $organization->update([
        'plan' => PlanTier::Pro,
        'stripe_subscription_id' => 'sub_test',
        'subscription_status' => 'past_due',
        'payment_failed_at' => now()->subDays(1),
    ]);
    app(CurrentOrganization::class)->clear();

    app(ProcessDunning::class)->handle();

    app(CurrentOrganization::class)->set($organization);
    expect($organization->fresh()->dunning_stage)->toBe(1);
    expect(app(GetEffectivePlan::class)->handle($organization->fresh()))->toBe(PlanTier::Pro);
    app(CurrentOrganization::class)->clear();

    Mail::assertSent(PaymentFailedMail::class, 1);

    // J+7 : troisième relance, restriction appliquée.
    app(CurrentOrganization::class)->set($organization);
    $organization->update(['payment_failed_at' => now()->subDays(7), 'dunning_stage' => 2]);
    app(CurrentOrganization::class)->clear();

    app(ProcessDunning::class)->handle();

    app(CurrentOrganization::class)->set($organization);
    $fresh = Organization::query()->findOrFail($organization->id);
    expect($fresh->dunning_stage)->toBe(3);
    expect(app(GetEffectivePlan::class)->handle($fresh))->toBe(PlanTier::Free);
    app(CurrentOrganization::class)->clear();

    Mail::assertSent(PaymentFailedMail::class, 2);
});

it('ne relance pas une organisation dont le paiement est à jour', function (): void {
    Mail::fake();
    ['organization' => $organization] = makeCheckInEvent(MembershipRole::Owner);

    app(ProcessDunning::class)->handle();

    Mail::assertNothingSent();
});
