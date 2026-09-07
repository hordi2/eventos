<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;

it('pose data-theme="light" par défaut, y compris avant toute organisation résolue', function (): void {
    $this->get('/login')->assertSee('data-theme="light"', false);
});

it('pose data-theme="light" pour une organisation sans préférence explicite', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->get('/dashboard')->assertSee('data-theme="light"', false);
});

it('pose data-theme="dark" quand l\'organisation a choisi le mode sombre', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    Organization::query()->where('id', $organization->id)->update(['theme_mode' => 'dark']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->get('/dashboard')->assertSee('data-theme="dark"', false);
});
