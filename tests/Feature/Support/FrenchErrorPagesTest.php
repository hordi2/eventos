<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

// Rendu de production : en mode debug, Laravel affiche le détail de
// l'exception à la place des pages d'erreur que voient les utilisateurs.
beforeEach(function (): void {
    config(['app.debug' => false]);
});

it('affiche le refus d\'une capacité en français, jamais le message par défaut de Laravel', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/settings/api');

    $response->assertForbidden();
    $response->assertSee("Vous n'avez pas le droit d'effectuer cette action.");
    $response->assertSee('Accès refusé');
    $response->assertDontSee('This action is unauthorized.');
});

it('affiche une page introuvable en français', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get('/events/999999/edit');

    $response->assertNotFound();
    $response->assertSee('Page introuvable');
    $response->assertDontSee('Not Found');
});

it('affiche le message français d\'une erreur 4xx sans page dédiée', function (): void {
    ['organization' => $organization] = makeCheckInEvent(MembershipRole::Owner);
    $invitee = User::factory()->create(['email' => 'tardif@example.test']);

    app(CurrentOrganization::class)->set($organization);
    Collaborator::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'tardif@example.test',
        'invitation_token_hash' => Collaborator::hashToken('jeton-expire'),
    ]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($invitee)->post("/invitations/{$organization->slug}/jeton-expire/accepter");

    $response->assertStatus(410);
    $response->assertSee("Cette invitation a expiré : demandez qu'on vous la renvoie.");
});
