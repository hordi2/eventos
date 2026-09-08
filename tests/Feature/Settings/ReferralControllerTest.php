<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;

it('affiche le lien de parrainage, en générant un code s\'il manque', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    expect($organization->referral_code)->toBeNull();

    $response = $this->actingAs($owner)->get('/settings/referral');

    $response->assertOk();
    expect($organization->fresh()->referral_code)->not->toBeNull();
});

it('refuse le parrainage à un rôle sans manageBilling', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/settings/referral');

    $response->assertForbidden();
});
