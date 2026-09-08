<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;

it('affiche la page étiquetage blanc pour un rôle autorisé', function (): void {
    ['doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->get('/settings/white-label');

    $response->assertOk();
});

it('refuse l\'accès à l\'étiquetage blanc à un rôle sans manageBranding', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/settings/white-label');

    $response->assertForbidden();
});

it('enregistre le nom d\'expéditeur et l\'adresse de réponse personnalisés', function (): void {
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $this->actingAs($admin)->patch('/settings/white-label', [
        'email_from_name' => 'Gala Annuel Itaza',
        'email_reply_to' => 'contact@gala-itaza.example',
    ])->assertRedirect();

    $fresh = $organization->fresh();
    expect($fresh->email_from_name)->toBe('Gala Annuel Itaza');
    expect($fresh->email_reply_to)->toBe('contact@gala-itaza.example');
});
