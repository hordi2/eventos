<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\ChooseOnSitePayment;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\GetCashReport;
use App\Domain\Ticketing\Actions\RecordOnSitePayment;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function payOnSite(Organization $organization, Event $event, User $collector, string $buyerEmail): void
{
    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id]);
    PriceTier::factory()->for($ticketType)->for($organization)->create(['name' => 'Normal']);

    $order = app(CreateOrder::class)->handle(
        $organization->id, $event->id,
        ['name' => 'Invité', 'email' => $buyerEmail],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
    $onSite = app(ChooseOnSitePayment::class)->handle($order);
    app(RecordOnSitePayment::class)->handle($onSite, $collector, $onSite->total);
}

it('additionne les encaissements en espèces d\'un événement, avec le détail par opérateur', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();

    $doorStaff = User::factory()->create(['name' => 'Personnel Porte']);
    $doorStaff->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::DoorStaff]);

    payOnSite($organization, $event, $doorStaff, 'a@example.com');
    payOnSite($organization, $event, $doorStaff, 'b@example.com');

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $report = app(GetCashReport::class)->handle($organization, $admin, $event->id);

    expect($report->count)->toBe(2);
    expect($report->entries[0]->collectorName)->toBe('Personnel Porte');
    expect($report->total->amountMinor())->toBe($report->entries[0]->amount->amountMinor() + $report->entries[1]->amount->amountMinor());
});

it('n\'inclut pas les encaissements d\'un autre événement', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $eventA = Event::factory()->for($organization)->create();
    $eventB = Event::factory()->for($organization)->create();

    $doorStaff = User::factory()->create();
    $doorStaff->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::DoorStaff]);

    payOnSite($organization, $eventA, $doorStaff, 'a@example.com');
    payOnSite($organization, $eventB, $doorStaff, 'b@example.com');

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $report = app(GetCashReport::class)->handle($organization, $admin, $eventA->id);

    expect($report->count)->toBe(1);
});

it('refuse l\'accès au rapport à un rôle qui n\'a pas la capacité viewFinancials', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->create();

    $editor = User::factory()->create();
    $editor->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Editor]);

    app(GetCashReport::class)->handle($organization, $editor, $event->id);
})->throws(AuthorizationException::class);
