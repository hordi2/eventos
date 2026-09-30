<?php

declare(strict_types=1);

use App\Domain\Event\Models\BudgetCategory;
use App\Domain\Event\Models\BudgetLine;
use App\Domain\Event\Models\BudgetLineKind;
use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use App\Support\Budget\EventBudgetData;
use App\Support\Budget\GetEventBudget;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

/**
 * Événement en euros et l'administrateur qui tient son budget.
 *
 * @return array{organization: Organization, event: Event, admin: User}
 */
function eventWithBudget(): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['title' => 'Conférence Itaza', 'currency' => 'EUR']);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'admin' => $admin];
}

function budgetOf(Organization $organization, Event $event): EventBudgetData
{
    app(CurrentOrganization::class)->set($organization);
    $budget = app(GetEventBudget::class)->handle($event);
    app(CurrentOrganization::class)->clear();

    return $budget;
}

it('enregistre un poste de dépense avec un montant écrit à la main', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = eventWithBudget();

    $this->actingAs($admin)->post("/events/{$event->id}/budget", [
        'kind' => 'expense',
        'category' => 'venue',
        'label' => 'Location de la salle',
        'supplier' => 'Palais du Peuple',
        'planned' => '1 500',
        'actual' => '1 620,50',
    ])->assertSessionHas('status', 'budget-line-saved');

    app(CurrentOrganization::class)->set($organization);
    $line = BudgetLine::query()->sole();
    expect($line->planned->amountMinor())->toBe(150_000)
        ->and($line->planned->currency())->toBe('EUR')
        ->and($line->actual->amountMinor())->toBe(162_050)
        ->and($line->category)->toBe(BudgetCategory::Venue)
        ->and($line->isOverrun())->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('refuse un montant illisible', function (): void {
    ['event' => $event, 'admin' => $admin] = eventWithBudget();

    $this->actingAs($admin)->post("/events/{$event->id}/budget", [
        'kind' => 'expense',
        'category' => 'venue',
        'label' => 'Salle',
        'planned' => 'beaucoup',
    ])->assertSessionHasErrors('planned');
});

it('distingue le prévu du réalisé, et ne compte jamais un poste non engagé', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    $shared = ['organization_id' => $organization->id, 'event_id' => $event->id];
    BudgetLine::factory()->create([...$shared, 'planned' => Money::fromMinorUnits(100_000, 'EUR')]);
    BudgetLine::factory()->spent(80_000)->create([...$shared, 'planned' => Money::fromMinorUnits(50_000, 'EUR')]);
    app(CurrentOrganization::class)->clear();

    $budget = budgetOf($organization, $event);

    expect($budget->plannedExpenses->amountMinor())->toBe(150_000)
        // Seule la dépense engagée compte dans le réalisé.
        ->and($budget->actualExpenses->amountMinor())->toBe(80_000)
        ->and($budget->overrunCount)->toBe(1);
});

it('compte les recettes de la billetterie sans les faire ressaisir', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    Order::factory()->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'status' => OrderStatus::Paid, 'total' => Money::fromMinorUnits(60_000, 'EUR'), 'paid_at' => CarbonImmutable::now(),
    ]);
    // Une commande remboursée ne rapporte rien, une commande en attente non plus.
    Order::factory()->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'status' => OrderStatus::Paid, 'total' => Money::fromMinorUnits(20_000, 'EUR'),
        'paid_at' => CarbonImmutable::now(), 'refunded_at' => CarbonImmutable::now(),
    ]);
    Order::factory()->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'status' => OrderStatus::Pending, 'total' => Money::fromMinorUnits(90_000, 'EUR'),
    ]);
    BudgetLine::factory()->spent(100_000)->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'planned' => Money::fromMinorUnits(100_000, 'EUR'),
    ]);
    app(CurrentOrganization::class)->clear();

    $budget = budgetOf($organization, $event);

    expect($budget->ticketingRevenue->amountMinor())->toBe(60_000)
        // 600 € encaissés contre 1000 € dépensés.
        ->and($budget->result->amountMinor())->toBe(-40_000)
        ->and($budget->toCover->amountMinor())->toBe(40_000);
});

it('couvre le budget avec les recettes hors billetterie', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    $shared = ['organization_id' => $organization->id, 'event_id' => $event->id];
    BudgetLine::factory()->create([...$shared, 'planned' => Money::fromMinorUnits(100_000, 'EUR')]);
    BudgetLine::factory()->income()->spent(120_000)->create([...$shared, 'planned' => Money::fromMinorUnits(120_000, 'EUR')]);
    app(CurrentOrganization::class)->clear();

    $budget = budgetOf($organization, $event);

    expect($budget->toCover->isZero())->toBeTrue()
        ->and($budget->ticketsToSell)->toBe(0)
        ->and($budget->result->amountMinor())->toBe(120_000);
});

it('chiffre le seuil de rentabilité en billets à vendre', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    BudgetLine::factory()->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'planned' => Money::fromMinorUnits(100_000, 'EUR'),
    ]);
    $ticketType = TicketType::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id]);
    PriceTier::factory()->create([
        'organization_id' => $organization->id, 'ticket_type_id' => $ticketType->id,
        'amount' => Money::fromMinorUnits(2_500, 'EUR'),
    ]);
    app(CurrentOrganization::class)->clear();

    // 1000 € à couvrir, des billets à 25 € : 40 billets.
    expect(budgetOf($organization, $event)->ticketsToSell)->toBe(40);
});

it('ne chiffre aucun billet quand l\'événement n\'en vend pas', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    BudgetLine::factory()->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'planned' => Money::fromMinorUnits(100_000, 'EUR'),
    ]);
    app(CurrentOrganization::class)->clear();

    $budget = budgetOf($organization, $event);
    expect($budget->ticketsToSell)->toBeNull()
        ->and($budget->toCover->amountMinor())->toBe(100_000);
});

it('affiche le budget et retire un poste', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    $line = BudgetLine::factory()->spent(162_050)->create([
        'organization_id' => $organization->id, 'event_id' => $event->id,
        'label' => 'Location de la salle', 'planned' => Money::fromMinorUnits(150_000, 'EUR'),
    ]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/budget")->assertInertia(fn ($page) => $page
        ->component('Events/Budget')
        ->where('budget.lines.0.label', 'Location de la salle')
        ->where('budget.lines.0.isOverrun', true)
        ->where('budget.lines.0.plannedInput', '1500,00')
        ->where('budget.overrunCount', 1));

    $this->actingAs($admin)->delete("/events/{$event->id}/budget/{$line->id}")->assertSessionHas('status', 'budget-line-removed');

    app(CurrentOrganization::class)->set($organization);
    expect(BudgetLine::query()->count())->toBe(0)
        ->and(BudgetLine::withTrashed()->count())->toBe(1);
});

it('réserve le budget aux membres qui peuvent voir les chiffres', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->get("/events/{$event->id}/budget")->assertForbidden();
    $this->actingAs($viewer)->post("/events/{$event->id}/budget", [
        'kind' => 'expense', 'category' => 'venue', 'label' => 'Salle', 'planned' => '100',
    ])->assertForbidden();
});

it('garde le budget d\'une organisation hors de portée d\'une autre', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithBudget();
    ['admin' => $intruder] = eventWithBudget();

    app(CurrentOrganization::class)->set($organization);
    BudgetLine::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($intruder)->get("/events/{$event->id}/budget")->assertNotFound();
});

it('additionne des montants dans une devise sans décimale', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['currency' => 'XOF']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/events/{$event->id}/budget", [
        'kind' => 'expense', 'category' => 'catering', 'label' => 'Traiteur', 'planned' => '250 000',
    ])->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($organization);
    // XOF n'a pas de subdivision : 250 000 francs, c'est 250 000 unités.
    expect(BudgetLine::query()->sole()->planned->amountMinor())->toBe(250_000);
    app(CurrentOrganization::class)->clear();

    expect(budgetOf($organization, $event)->plannedExpenses->currency())->toBe('XOF');
});

it('n\'accepte pas un poste dans une autre nature que dépense ou recette', function (): void {
    ['event' => $event, 'admin' => $admin] = eventWithBudget();

    $this->actingAs($admin)->post("/events/{$event->id}/budget", [
        'kind' => 'fumée', 'category' => 'venue', 'label' => 'Salle', 'planned' => '100',
    ])->assertSessionHasErrors('kind');

    expect(BudgetLineKind::tryFrom('fumée'))->toBeNull();
});
