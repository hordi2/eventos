<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\ChooseOnSitePayment;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\RecordOnSitePayment;
use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
    $event = Event::factory()->for($this->organization)->create();
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->create();

    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $event->id,
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
    $this->order = app(ChooseOnSitePayment::class)->handle($order);
});

it('encaisse un paiement à l\'arrivée, émet les billets et trace l\'opérateur', function (): void {
    $doorStaff = User::factory()->create();
    $doorStaff->memberships()->create(['organization_id' => $this->organization->id, 'role' => MembershipRole::DoorStaff]);

    $paid = app(RecordOnSitePayment::class)->handle($this->order, $doorStaff, $this->order->total);

    expect($paid->status->value)->toBe('paid');
    expect($paid->items->first()->tickets)->toHaveCount(1);
    expect($paid->payments->first()->provider)->toBe('cash');
    expect($paid->payments->first()->collected_by)->toBe($doorStaff->id);
});

it('refuse l\'encaissement à un rôle qui n\'a pas la capacité checkIn', function (): void {
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $this->organization->id, 'role' => MembershipRole::Viewer]);

    app(RecordOnSitePayment::class)->handle($this->order, $viewer, $this->order->total);
})->throws(AuthorizationException::class);

it('refuse l\'encaissement d\'une commande qui n\'a pas choisi le paiement à l\'arrivée', function (): void {
    $event = Event::factory()->for($this->organization)->create();
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    PriceTier::factory()->for($ticketType)->for($this->organization)->create();
    $pendingOrder = app(CreateOrder::class)->handle(
        $this->organization->id, $event->id,
        ['name' => 'Bob', 'email' => 'bob@example.com'],
        [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $this->organization->id, 'role' => MembershipRole::Admin]);

    app(RecordOnSitePayment::class)->handle($pendingOrder, $admin, $pendingOrder->total);
})->throws(InvalidOrderTransitionException::class);
