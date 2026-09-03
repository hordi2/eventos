<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Actions\CreatePriceTier;
use App\Domain\Ticketing\Actions\CreateTicketType;
use App\Domain\Ticketing\Actions\DeletePriceTier;
use App\Domain\Ticketing\Actions\DeleteTicketType;
use App\Domain\Ticketing\Actions\UpdatePriceTier;
use App\Domain\Ticketing\Actions\UpdateTicketType;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\Models\VatMode;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;

it('modifie les champs d\'un type de billet existant', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet standard', 'is_free' => true, 'currency' => 'EUR', 'fees_absorbed' => true,
    ]);

    $updated = app(UpdateTicketType::class)->handle($ticketType, $admin, [
        'name' => 'Billet renommé',
        'min_per_order' => 2,
        'max_per_order' => 5,
        'vat_mode' => VatMode::None,
        'vat_rate_bp' => 0,
        'fees_absorbed' => false,
        'is_active' => false,
    ]);

    expect($updated->name)->toBe('Billet renommé');
    expect($updated->min_per_order)->toBe(2);
    expect($updated->fees_absorbed)->toBeFalse();
    expect($updated->is_active)->toBeFalse();
    expect($updated->is_free)->toBeTrue();
});

it('refuse la modification à un rôle qui n\'a pas la capacité manageTicketing', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet', 'is_free' => true, 'currency' => 'EUR', 'fees_absorbed' => true,
    ]);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    app(UpdateTicketType::class)->handle($ticketType, $viewer, [
        'name' => 'Nouveau nom', 'min_per_order' => 1, 'vat_mode' => VatMode::None, 'vat_rate_bp' => 0, 'fees_absorbed' => true,
    ]);
})->throws(AuthorizationException::class);

it('supprime un type de billet', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet', 'is_free' => true, 'currency' => 'EUR', 'fees_absorbed' => true,
    ]);

    app(DeleteTicketType::class)->handle($ticketType, $admin);

    expect(TicketType::query()->find($ticketType->id))->toBeNull();
    expect(TicketType::withTrashed()->find($ticketType->id))->not->toBeNull();
});

it('ajoute un palier à un type de billet payant existant', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet', 'is_free' => false, 'currency' => 'EUR', 'fees_absorbed' => true,
        'tiers' => [['name' => 'Normal', 'amount' => Money::fromMinorUnits(2000, 'EUR')]],
    ]);

    $tier = app(CreatePriceTier::class)->handle($ticketType, $admin, [
        'name' => 'Tardif', 'amount' => Money::fromMinorUnits(2500, 'EUR'), 'quantity' => 20,
    ]);

    expect($ticketType->priceTiers()->count())->toBe(2);
    expect($tier->name)->toBe('Tardif');
});

it('refuse d\'ajouter un palier à un billet gratuit', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet gratuit', 'is_free' => true, 'currency' => 'EUR', 'fees_absorbed' => true,
    ]);

    app(CreatePriceTier::class)->handle($ticketType, $admin, [
        'name' => 'Normal', 'amount' => Money::fromMinorUnits(1000, 'EUR'),
    ]);
})->throws(InvalidArgumentException::class);

it('modifie un palier existant', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet', 'is_free' => false, 'currency' => 'EUR', 'fees_absorbed' => true,
        'tiers' => [['name' => 'Normal', 'amount' => Money::fromMinorUnits(2000, 'EUR')]],
    ]);
    $tier = $ticketType->priceTiers->first();

    $updated = app(UpdatePriceTier::class)->handle($tier, $admin, [
        'name' => 'Normal (révisé)', 'amount' => Money::fromMinorUnits(2200, 'EUR'), 'quantity' => 30,
    ]);

    expect($updated->name)->toBe('Normal (révisé)');
    expect($updated->amount->amountMinor())->toBe(2200);
    expect($updated->quantity)->toBe(30);
});

it('supprime un palier quand il n\'est pas le dernier', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet', 'is_free' => false, 'currency' => 'EUR', 'fees_absorbed' => true,
        'tiers' => [
            ['name' => 'Early bird', 'amount' => Money::fromMinorUnits(1500, 'EUR')],
            ['name' => 'Normal', 'amount' => Money::fromMinorUnits(2000, 'EUR')],
        ],
    ]);
    $tier = $ticketType->priceTiers->first();

    app(DeletePriceTier::class)->handle($tier, $admin);

    expect($ticketType->priceTiers()->count())->toBe(1);
});

it('refuse de supprimer le dernier palier d\'un billet payant', function (): void {
    [$organization, $admin] = ticketingOrganizationWithMember(MembershipRole::Owner);
    $event = Event::factory()->for($organization)->create();
    $ticketType = app(CreateTicketType::class)->handle($organization, $event->id, $admin, [
        'name' => 'Billet', 'is_free' => false, 'currency' => 'EUR', 'fees_absorbed' => true,
        'tiers' => [['name' => 'Normal', 'amount' => Money::fromMinorUnits(2000, 'EUR')]],
    ]);
    $tier = $ticketType->priceTiers->first();

    app(DeletePriceTier::class)->handle($tier, $admin);
})->throws(InvalidArgumentException::class);
