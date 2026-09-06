<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;

it('affiche la page de facturation avec le plan actuel et les 3 plans disponibles', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get('/billing');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Billing/Show')
        ->where('organization.plan', 'free')
        ->has('plans', 3));
});

it('échoue proprement au moment du paiement quand le Price ID Stripe du plan n\'est pas configuré', function (): void {
    config(['plans.pro.stripe_price' => null]);
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/billing/checkout/pro');

    $response->assertSessionHasErrors('plan');
});

it('refuse un changement de plan sans abonnement Stripe actif', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->post('/billing/change/business')
        ->assertStatus(500);
});
