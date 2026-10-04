<?php

declare(strict_types=1);

use App\Domain\Organization\Actions\CreateClientAccount;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use App\Support\Agency\ResolvePortalBrand;
use App\Support\MultiTenancy\CurrentOrganization;

/**
 * Marque de l'agence sur le portail de ses comptes clients (D10,
 * white-label du §M6.3).
 *
 * @return array{0: Organization, 1: User, 2: Organization}
 */
function brandedAgency(array $overrides = []): array
{
    $agency = Organization::factory()->create([
        'name' => 'Agence Lumière',
        'is_agency' => true,
        'plan' => PlanTier::ProfessionalPro,
        'logo_path' => 'organization-logos/1/agence.png',
        'primary_color' => '#8B6F3A',
        'brands_client_portals' => true,
        ...$overrides,
    ]);

    app(CurrentOrganization::class)->set($agency);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $agency->id, 'role' => MembershipRole::Admin]);
    $client = app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');
    app(CurrentOrganization::class)->clear();

    return [$agency, $admin, $client];
}

it('donne au portail du client la marque de son agence', function (): void {
    [, , $client] = brandedAgency();

    $brand = app(ResolvePortalBrand::class)->handle($client);

    expect($brand)->not->toBeNull()
        ->and($brand['name'])->toBe('Agence Lumière')
        ->and($brand['logoUrl'])->toContain('agence.png')
        ->and($brand['primaryColor'])->toBe('#8B6F3A');
});

it('laisse au portail sa marque d\'origine quand l\'agence ne le demande pas', function (): void {
    [, , $client] = brandedAgency(['brands_client_portals' => false]);

    expect(app(ResolvePortalBrand::class)->handle($client))->toBeNull();
});

it('laisse au portail sa marque d\'origine quand l\'agence n\'a pas de logo', function (): void {
    [, , $client] = brandedAgency(['logo_path' => null]);

    expect(app(ResolvePortalBrand::class)->handle($client))->toBeNull();
});

it('ne marque pas le portail d\'une organisation sans agence', function (): void {
    $alone = Organization::factory()->create();

    expect(app(ResolvePortalBrand::class)->handle($alone))->toBeNull()
        ->and(app(ResolvePortalBrand::class)->handle(null))->toBeNull();
});

it('porte la marque jusqu\'aux pages du portail client', function (): void {
    [$agency, $admin, $client] = brandedAgency();

    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $client->id])
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('portalBrand.name', 'Agence Lumière'));

    // L'agence, elle, garde la marque d'Itaza chez elle.
    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $agency->id])
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('portalBrand', null));
});
