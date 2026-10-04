<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Actions\CreateClientAccount;
use App\Domain\Organization\Actions\ReleaseClientAccount;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use App\Support\Agency\GetAgencyPortfolio;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Portail agence (D10) : une agence gère les comptes de ses clients, voit
 * son portefeuille d'un coup d'œil, et peut rendre un compte à son client.
 *
 * @return array{0: Organization, 1: User}
 */
function agencyWithRole(MembershipRole $role): array
{
    $agency = Organization::factory()->create([
        'name' => 'Agence Lumière',
        'is_agency' => true,
        'plan' => PlanTier::ProfessionalPro,
    ]);
    app(CurrentOrganization::class)->set($agency);
    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $agency->id, 'role' => $role]);
    app(CurrentOrganization::class)->clear();

    return [$agency, $user];
}

it('ouvre le compte d\'un client et y fait entrer l\'agence', function (): void {
    [$agency, $admin] = agencyWithRole(MembershipRole::Admin);

    $client = app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');

    expect($client->managed_by_organization_id)->toBe($agency->id)
        ->and($client->slug)->toBe('fondation-lumiere')
        ->and($client->managed_since)->not->toBeNull()
        // Le compte client hérite du plan : c'est l'agence qui paie.
        ->and($client->plan)->toBe(PlanTier::ProfessionalPro);

    $membership = Membership::query()->where('organization_id', $client->id)->where('user_id', $admin->id)->sole();
    // Administratrice, jamais propriétaire : le compte appartient au client.
    expect($membership->role)->toBe(MembershipRole::Admin);
});

it('refuse l\'ouverture d\'un compte à un membre qui n\'administre pas l\'agence', function (): void {
    [$agency, $editor] = agencyWithRole(MembershipRole::Editor);

    expect(fn () => app(CreateClientAccount::class)->handle($agency, $editor, 'Fondation Lumière'))
        ->toThrow(AuthorizationException::class);
});

it('évite deux comptes clients au même slug', function (): void {
    [$agency, $admin] = agencyWithRole(MembershipRole::Admin);

    $first = app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');
    $second = app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');

    expect($first->slug)->toBe('fondation-lumiere')
        ->and($second->slug)->toBe('fondation-lumiere-2');
});

it('additionne le portefeuille de l\'agence, compte par compte', function (): void {
    [$agency, $admin] = agencyWithRole(MembershipRole::Admin);

    $first = app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');
    $second = app(CreateClientAccount::class)->handle($agency, $admin, 'Lycée Saint-Joseph');

    app(CurrentOrganization::class)->set($first);
    Event::factory()->for($first)->published()->create(['title' => 'Gala annuel', 'start_at' => now()->addMonth()]);
    Event::factory()->for($first)->published()->create(['start_at' => now()->addMonths(2)]);
    app(CurrentOrganization::class)->set($second);
    Event::factory()->for($second)->published()->create(['start_at' => now()->addWeek()]);
    app(CurrentOrganization::class)->clear();

    $portfolio = app(GetAgencyPortfolio::class)->handle($agency);

    expect($portfolio->clients)->toHaveCount(2)
        ->and($portfolio->eventCount)->toBe(3)
        // Le contexte de lecture est rendu propre : l'agence n'est pas
        // restée dans le dernier compte visité.
        ->and(app(CurrentOrganization::class)->id())->toBeNull();

    $lycee = $portfolio->clients[1];
    expect($lycee->name)->toBe('Lycée Saint-Joseph')
        ->and($lycee->eventCount)->toBe(1);
});

it('rend un compte à son client, qui garde tout', function (): void {
    [$agency, $admin] = agencyWithRole(MembershipRole::Admin);
    $client = app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');

    app(CurrentOrganization::class)->set($client);
    $event = Event::factory()->for($client)->published()->create(['title' => 'Gala annuel']);
    // Une personne du client, qui n'appartient pas à l'agence.
    $own = User::factory()->create();
    $own->memberships()->create(['organization_id' => $client->id, 'role' => MembershipRole::Owner]);
    app(CurrentOrganization::class)->clear();

    app(ReleaseClientAccount::class)->handle($agency, $client, $admin);

    expect($client->refresh()->managed_by_organization_id)->toBeNull()
        ->and($client->managed_since)->toBeNull()
        // L'agence n'a plus la main…
        ->and(Membership::query()->where('organization_id', $client->id)->where('user_id', $admin->id)->exists())->toBeFalse()
        // …le client garde ses gens et ses événements.
        ->and(Membership::query()->where('organization_id', $client->id)->where('user_id', $own->id)->exists())->toBeTrue();

    app(CurrentOrganization::class)->set($client);
    expect(Event::query()->whereKey($event->id)->exists())->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('ne laisse pas une agence rendre le compte d\'une autre', function (): void {
    [$agency, $admin] = agencyWithRole(MembershipRole::Admin);
    [$other, $otherAdmin] = agencyWithRole(MembershipRole::Admin);
    $client = app(CreateClientAccount::class)->handle($other, $otherAdmin, 'Fondation Lumière');

    expect(fn () => app(ReleaseClientAccount::class)->handle($agency, $client, $admin))
        ->toThrow(NotFoundHttpException::class);
});

it('ouvre le portail aux seules agences, et à qui peut les administrer', function (): void {
    [$agency, $admin] = agencyWithRole(MembershipRole::Admin);
    app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');

    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $agency->id])
        ->get('/clients')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Agency/Index')
            ->where('portfolio.clients.0.name', 'Fondation Lumière'));

    [$editorAgency, $editor] = agencyWithRole(MembershipRole::Editor);

    $this->actingAs($editor)
        ->withSession(['current_organization_id' => $editorAgency->id])
        ->get('/clients')
        ->assertForbidden();
});

it('fait de l\'organisation une agence dès son premier compte client', function (): void {
    [$agency, $admin] = agencyWithRole(MembershipRole::Admin);
    $agency->update(['is_agency' => false]);

    app(CreateClientAccount::class)->handle($agency, $admin, 'Fondation Lumière');

    expect($agency->refresh()->is_agency)->toBeTrue();
});
