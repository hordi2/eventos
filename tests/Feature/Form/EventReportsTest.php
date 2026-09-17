<?php

declare(strict_types=1);

use App\Domain\Analytics\Models\Export;
use App\Domain\Analytics\Models\ExportType;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Storage;

it('compte les repas à commander, accompagnants compris, avec les quotas restants', function (): void {
    ['event' => $event, 'admin' => $admin, 'base' => $base] = makeAnswersReadyEvent();
    registerGuestWithAnswers($this, $event, $base);

    $this->actingAs($admin)->get("/events/{$event->id}/meals")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Meals')
            ->where('expected', 2)
            ->has('questions', 1)
            ->where('questions.0.options', [
                ['label' => 'Poisson braisé', 'count' => 1, 'quota' => 10, 'remaining' => 9],
                ['label' => 'Poulet mayo', 'count' => 1, 'quota' => null, 'remaining' => null],
            ])
            ->has('people', 2)
            ->where('people.0.name', 'Marie Lusala')
            ->where('people.0.isCompanion', false)
            ->where('people.0.meals.menu', 'Poisson braisé')
            ->where('people.1.name', 'Paul Kalala')
            ->where('people.1.isCompanion', true)
            ->where('people.1.registrationName', 'Marie Lusala')
            ->where('people.1.meals.menu', 'Poulet mayo')
            ->where('eventNav.links.meals', route('events.meals.index', $event->id)));
});

it('liste les dons avec leur état de paiement et les totaux reçus et promis', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'base' => $base] = makeAnswersReadyEvent();
    registerGuestWithAnswers($this, $event, $base);

    $this->actingAs($admin)->get("/events/{$event->id}/donations")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Donations')
            ->has('donations', 1)
            ->where('donations.0.donor', 'Marie Lusala')
            ->where('donations.0.amount', Money::fromMinorUnits(5000, 'XAF')->format())
            ->where('donations.0.statusLabel', 'En attente de paiement')
            ->where('donations.0.guestName', 'Marie Lusala')
            ->where('promised', [Money::fromMinorUnits(5000, 'XAF')->format()])
            ->where('received', []));

    // Une fois le don réglé, il passe des promesses aux dons reçus.
    app(CurrentOrganization::class)->set($organization);
    Order::query()->firstOrFail()->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

    $this->actingAs($admin)->get("/events/{$event->id}/donations")
        ->assertInertia(fn ($page) => $page
            ->where('donations.0.statusLabel', 'Reçu')
            ->where('received', [Money::fromMinorUnits(5000, 'XAF')->format()])
            ->where('promised', []));
});

it('ajoute une colonne par question à l\'export des inscriptions', function (): void {
    Storage::fake('local');
    ['event' => $event, 'admin' => $admin, 'base' => $base] = makeAnswersReadyEvent();
    registerGuestWithAnswers($this, $event, $base);

    $this->actingAs($admin)->get("/events/{$event->id}/exports")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('types', fn ($types): bool => collect($types)
            ->firstWhere('value', ExportType::Registrations->value)['columns']['question:menu'] === 'Choix du menu'));

    $this->actingAs($admin)->postJson("/events/{$event->id}/exports", [
        'type' => ExportType::Registrations->value,
        'columns' => ['first_name', 'question:menu', 'question:mot'],
    ])->assertCreated();

    $export = Export::withoutGlobalScopes()->latest('id')->firstOrFail();
    $csv = (string) Storage::disk('local')->get((string) $export->file_path);

    expect($csv)->toContain('Choix du menu');
    expect($csv)->toContain('Poisson braisé ; Paul : Poulet mayo');
    expect($csv)->toContain("Merci pour l'invitation");
});

it('ne propose ni repas ni dons dans le menu quand le formulaire ne les demande pas', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    Membership::factory()->for($organization)->for($admin)->create(['role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/answers")
        ->assertInertia(fn ($page) => $page
            ->where('eventNav.links.meals', null)
            ->where('eventNav.links.donations', null));

    $this->actingAs($admin)->get("/events/{$event->id}/meals")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('questions', 0)->where('expected', 0));
});

it('refuse les rapports à une autre organisation', function (): void {
    ['event' => $event] = makeAnswersReadyEvent();
    [, $outsider] = organizationWithContactRole(MembershipRole::Admin);

    $this->actingAs($outsider)->get("/events/{$event->id}/meals")->assertNotFound();
    $this->actingAs($outsider)->get("/events/{$event->id}/donations")->assertNotFound();
});
