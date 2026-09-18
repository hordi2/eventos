<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Models\Order;
use App\Models\User;
use App\Support\Gdpr\AnonymizeContact;
use App\Support\Gdpr\ExportContactData;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

it('relie l\'achat de billets au contact de l\'acheteur, créé au besoin', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();
    $cle = (string) Str::uuid();
    $achat = [
        'checkout_token' => $cle,
        'buyer_name' => 'Alice Kouassi Aya',
        'buyer_email' => 'Alice@Example.com',
        'items' => [$ticketType->id => 2],
    ];

    $this->post("/billets/{$organization->slug}/{$event->slug}", $achat);
    // Même clé rejouée (double clic) : ni seconde commande, ni second contact.
    $this->post("/billets/{$organization->slug}/{$event->slug}", $achat);

    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::query()->where('email', 'alice@example.com')->sole();
    expect($contact->first_name)->toBe('Alice');
    expect($contact->last_name)->toBe('Kouassi Aya');
    expect($contact->email_consent_source)->toBe('ticket_order');
    expect(Order::query()->sole()->contact_id)->toBe($contact->id);
});

it('reprend le contact déjà connu sans toucher à ses noms', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();
    app(CurrentOrganization::class)->set($organization);
    $connu = Contact::factory()->for($organization)->create(['first_name' => 'Aya', 'last_name' => 'Kouassi', 'email' => 'aya@example.com']);
    app(CurrentOrganization::class)->clear();

    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'A. Kouassi',
        'buyer_email' => 'aya@example.com',
        'items' => [$ticketType->id => 1],
    ]);

    app(CurrentOrganization::class)->set($organization);
    expect(Order::query()->sole()->contact_id)->toBe($connu->id);
    expect($connu->fresh()->first_name)->toBe('Aya');
    expect(Contact::query()->count())->toBe(1);
});

it('anonymise à l\'effacement du contact les commandes de billets qui lui sont reliées', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();
    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice Kouassi',
        'buyer_email' => 'alice@example.com',
        'buyer_phone' => '+2250700000000',
        'items' => [$ticketType->id => 2],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    $contact = Contact::query()->where('email', 'alice@example.com')->sole();

    app(AnonymizeContact::class)->handle($contact, $admin);

    $commande = Order::query()->sole();
    expect($commande->buyer_name)->toBe('Invité anonymisé');
    expect($commande->buyer_email)->toBe('');
    expect($commande->buyer_phone_e164)->toBeNull();
    // Montant et lignes conservés (règle 4.5 du CLAUDE.md).
    expect($commande->total->amountMinor())->toBe(4000);
    expect($commande->items->first()->quantity)->toBe(2);
});

it('restitue dans l\'export RGPD les commandes reliées au contact', function (): void {
    ['organization' => $organization, 'event' => $event, 'ticketType' => $ticketType] = makeGuestTicketedEvent();
    $this->post("/billets/{$organization->slug}/{$event->slug}", [
        'checkout_token' => (string) Str::uuid(),
        'buyer_name' => 'Alice Kouassi',
        'buyer_email' => 'alice@example.com',
        'items' => [$ticketType->id => 2],
    ]);

    app(CurrentOrganization::class)->set($organization);
    $export = app(ExportContactData::class)->handle(Contact::query()->where('email', 'alice@example.com')->sole());

    expect($export['commandes'])->toHaveCount(1);
    expect($export['commandes'][0]['acheteur']['email'])->toBe('alice@example.com');
    expect($export['commandes'][0]['total'])->toBe(['montant_unités_mineures' => 4000, 'devise' => 'EUR']);
    expect($export['commandes'][0]['billets'][0]->quantity)->toBe(2);
});
