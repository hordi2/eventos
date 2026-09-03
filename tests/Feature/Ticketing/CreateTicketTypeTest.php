<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateTicketType;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\Models\VatMode;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

function ticketingOrganizationWithMember(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('crée un billet gratuit sans palier de tarification', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Entrée gratuite',
        'is_free' => true,
        'currency' => 'EUR',
        'fees_absorbed' => true,
    ]);

    expect($ticketType->is_free)->toBeTrue();
    expect($ticketType->event_id)->toBe($event->id);
    expect($ticketType->priceTiers)->toHaveCount(0);
});

it('crée un billet payant avec ses paliers de tarification', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();

    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet standard',
        'is_free' => false,
        'currency' => 'EUR',
        'fees_absorbed' => false,
        'tiers' => [
            ['name' => 'Early bird', 'amount' => Money::fromMinorUnits(1500, 'EUR'), 'quantity' => 50],
            ['name' => 'Normal', 'amount' => Money::fromMinorUnits(2000, 'EUR')],
        ],
    ]);

    expect($ticketType->priceTiers)->toHaveCount(2);
    expect($ticketType->priceTiers->first()->name)->toBe('Early bird');
    expect($ticketType->priceTiers->first()->amount->amountMinor())->toBe(1500);
    expect($ticketType->priceTiers->first()->quantity)->toBe(50);
});

it('refuse un billet gratuit auquel on fournit des paliers', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();

    app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Invalide',
        'is_free' => true,
        'currency' => 'EUR',
        'fees_absorbed' => true,
        'tiers' => [['name' => 'Palier', 'amount' => Money::fromMinorUnits(1000, 'EUR')]],
    ]);
})->throws(InvalidArgumentException::class);

it('refuse un billet payant sans aucun palier', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();

    app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Invalide',
        'is_free' => false,
        'currency' => 'EUR',
        'fees_absorbed' => true,
    ]);
})->throws(InvalidArgumentException::class);

it('exige un choix explicite d\'absorption des frais', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();

    app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Sans choix de frais',
        'is_free' => true,
        'currency' => 'EUR',
    ]);
})->throws(InvalidArgumentException::class);

it('refuse une quantité maximale par commande inférieure au minimum', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();

    app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Bornes invalides',
        'is_free' => true,
        'currency' => 'EUR',
        'fees_absorbed' => true,
        'min_per_order' => 5,
        'max_per_order' => 2,
    ]);
})->throws(InvalidArgumentException::class);

it('refuse un taux de TVA renseigné sans régime de TVA', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();

    app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'TVA incohérente',
        'is_free' => true,
        'currency' => 'EUR',
        'fees_absorbed' => true,
        'vat_mode' => VatMode::None,
        'vat_rate_bp' => 2000,
    ]);
})->throws(InvalidArgumentException::class);

it('refuse la création à un rôle qui n\'a pas la capacité manageTicketing', function (): void {
    [$organization, $viewer] = ticketingOrganizationWithMember(MembershipRole::Viewer);
    $event = Event::factory()->for($organization)->create();

    app(CreateTicketType::class)->handle($organization, $event->id, $viewer, [
        'name' => 'Test',
        'is_free' => true,
        'currency' => 'EUR',
        'fees_absorbed' => true,
    ]);
})->throws(AuthorizationException::class);

it('cloisonne les types de billets par organisation, y compris au niveau de la RLS PostgreSQL', function (): void {
    [$organizationA] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $eventA = Event::factory()->for($organizationA)->create();
    TicketType::factory()->for($organizationA)->create(['event_id' => $eventA->id]);

    [$organizationB] = ticketingOrganizationWithMember(MembershipRole::Owner);

    // withoutGlobalScopes() lève la barrière Eloquent, mais la RLS
    // PostgreSQL (§4.1 CLAUDE.md, seconde barrière) reste active sur la
    // connexion : le billet de l'organisation A reste invisible.
    expect(TicketType::withoutGlobalScopes()->count())->toBe(0);

    app(CurrentOrganization::class)->set($organizationA);

    expect(TicketType::query()->count())->toBe(1);
});
