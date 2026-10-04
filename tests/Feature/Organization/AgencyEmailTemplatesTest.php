<?php

declare(strict_types=1);

use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Actions\CreateClientAccount;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use App\Support\Agency\AgencyEmailTemplates;
use App\Support\MultiTenancy\CurrentOrganization;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Modèles d'e-mail partagés par une agence avec ses comptes clients (D10) :
 * elle écrit sa lettre une fois, chaque client la reprend chez lui.
 *
 * @return array{0: Organization, 1: User, 2: Organization}
 */
function agencyWithClient(): array
{
    $agency = Organization::factory()->create(['name' => 'Agence Lumière', 'is_agency' => true, 'plan' => PlanTier::ProfessionalPro]);
    app(CurrentOrganization::class)->set($agency);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $agency->id, 'role' => MembershipRole::Admin]);
    $client = app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');
    app(CurrentOrganization::class)->clear();

    return [$agency, $admin, $client];
}

it('montre au compte client les seuls modèles que son agence partage', function (): void {
    [$agency, $admin, $client] = agencyWithClient();

    app(CurrentOrganization::class)->set($agency);
    EmailTemplate::factory()->create([
        'organization_id' => $agency->id,
        'created_by' => $admin->id,
        'name' => 'Invitation au gala',
        'is_shared_with_clients' => true,
    ]);
    EmailTemplate::factory()->create([
        'organization_id' => $agency->id,
        'created_by' => $admin->id,
        'name' => 'Note interne',
        'is_shared_with_clients' => false,
    ]);
    app(CurrentOrganization::class)->set($client);

    $shared = app(AgencyEmailTemplates::class)->shared($client);

    expect($shared)->toHaveCount(1)
        ->and($shared[0]['name'])->toBe('Invitation au gala')
        ->and($shared[0]['agency'])->toBe('Agence Lumière')
        // La lecture chez l'agence n'a pas laissé son contexte derrière elle.
        ->and(app(CurrentOrganization::class)->id())->toBe($client->id);

    app(CurrentOrganization::class)->clear();
});

it('ne partage rien avec une organisation sans agence', function (): void {
    $alone = Organization::factory()->create();
    app(CurrentOrganization::class)->set($alone);

    expect(app(AgencyEmailTemplates::class)->shared($alone))->toBe([]);

    app(CurrentOrganization::class)->clear();
});

it('reprend chez le client une copie qui lui appartient', function (): void {
    [$agency, $admin, $client] = agencyWithClient();

    app(CurrentOrganization::class)->set($agency);
    $original = EmailTemplate::factory()->create([
        'organization_id' => $agency->id,
        'created_by' => $admin->id,
        'name' => 'Invitation au gala',
        'subject' => 'Vous êtes invité',
        'is_shared_with_clients' => true,
    ]);
    app(CurrentOrganization::class)->set($client);

    $copy = app(AgencyEmailTemplates::class)->import($client, $admin, $original->id);

    expect($copy->organization_id)->toBe($client->id)
        ->and($copy->name)->toBe('Invitation au gala')
        ->and($copy->subject)->toBe('Vous êtes invité')
        // toEqual : les blocs reviennent de la base, leur ordre de clés
        // n'a pas à être identique à celui du tableau d'origine.
        ->and($copy->blocks)->toEqual($original->blocks)
        // La copie ne se repartage pas d'elle-même.
        ->and($copy->is_shared_with_clients)->toBeFalse();

    // L'original n'a pas bougé.
    app(CurrentOrganization::class)->set($agency);
    expect($original->refresh()->name)->toBe('Invitation au gala');
    app(CurrentOrganization::class)->clear();
});

it('refuse de reprendre un modèle que l\'agence ne partage pas', function (): void {
    [$agency, $admin, $client] = agencyWithClient();

    app(CurrentOrganization::class)->set($agency);
    $private = EmailTemplate::factory()->create([
        'organization_id' => $agency->id,
        'created_by' => $admin->id,
        'is_shared_with_clients' => false,
    ]);
    app(CurrentOrganization::class)->set($client);

    expect(fn () => app(AgencyEmailTemplates::class)->import($client, $admin, $private->id))
        ->toThrow(NotFoundHttpException::class);

    app(CurrentOrganization::class)->clear();
});

it('propose le partage à une agence, et le garde à l\'enregistrement', function (): void {
    [$agency, $admin] = agencyWithClient();

    app(CurrentOrganization::class)->set($agency);
    $template = EmailTemplate::factory()->create(['organization_id' => $agency->id, 'created_by' => $admin->id]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $agency->id])
        ->get("/email-templates/{$template->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('isAgency', true)->where('template.is_shared_with_clients', false));

    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $agency->id])
        ->patch("/email-templates/{$template->id}", [
            'name' => $template->name,
            'subject' => $template->subject,
            'blocks' => $template->blocks,
            'is_shared_with_clients' => true,
        ])
        ->assertRedirect();

    app(CurrentOrganization::class)->set($agency);
    expect($template->refresh()->is_shared_with_clients)->toBeTrue();
    app(CurrentOrganization::class)->clear();
});
