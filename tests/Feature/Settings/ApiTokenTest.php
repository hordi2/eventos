<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;

it('crée une clé API et affiche le jeton en clair une seule fois via le flash', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/api/tokens', ['name' => 'Automatisation Zapier']);

    $response->assertRedirect();
    $response->assertSessionHas('plainToken');
    expect($owner->fresh()->tokens()->where('name', 'Automatisation Zapier')->exists())->toBeTrue();
});

it('révoque une clé API appartenant à l\'utilisateur courant', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $token = $owner->createToken('À révoquer');

    $this->actingAs($owner)->delete("/settings/api/tokens/{$token->accessToken->id}");

    expect($owner->fresh()->tokens()->where('id', $token->accessToken->id)->exists())->toBeFalse();
});

it('refuse l\'accès aux clés API à un rôle sans capacité manageIntegrations', function (): void {
    ['doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);

    $this->actingAs($viewer)->get('/settings/api')->assertForbidden();
});
