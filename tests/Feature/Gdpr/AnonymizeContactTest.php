<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Models\Donation;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Gdpr\AnonymizeContact;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

it('anonymise un contact et ses inscriptions, en gardant le statut et la date', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'grace@example.com']);
    $event = Event::factory()->for($organization)->published()->create();
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    app(AnonymizeContact::class)->handle($contact, $admin);

    $freshContact = Contact::withTrashed()->find($contact->id);
    expect($freshContact->first_name)->toBe('Contact');
    expect($freshContact->email)->toBeNull();
    expect($freshContact->trashed())->toBeTrue();

    $freshRegistration = $registration->fresh();
    expect($freshRegistration->email)->toBe('');
    expect($freshRegistration->first_name)->toBe('Invité');
    // Ce qui doit être conservé (agrégats/statistiques, règle 4.5 du CLAUDE.md) :
    expect($freshRegistration->status->value)->toBe('confirmed');
    expect($freshRegistration->event_id)->toBe($event->id);
    app(CurrentOrganization::class)->clear();

    expect(AuditLog::query()->where('action', 'contact.anonymized')->where('subject_id', $contact->id)->exists())->toBeTrue();
});

it('refuse l\'anonymisation à un rôle sans capacité updateGuests', function (): void {
    [$organization, $viewer] = organizationWithContactRole(MembershipRole::Viewer);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    expect(fn () => app(AnonymizeContact::class)->handle($contact, $viewer))
        ->toThrow(AuthorizationException::class);
});

it('anonymise aussi les dons du contact, en gardant montants, statut et dates', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'grace@example.com']);
    $event = Event::factory()->for($organization)->published()->create();
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    $order = Order::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'buyer_name' => 'Grace Mbuyi',
        'buyer_email' => 'grace@example.com',
        'buyer_phone_e164' => '+243810000000',
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
    ]);
    $donation = Donation::factory()->create([
        'organization_id' => $organization->id,
        'order_id' => $order->id,
        'amount' => Money::fromMinorUnits(2500000, 'CDF'),
        'donor_name' => 'Grace Mbuyi',
        'donor_company' => 'Mbuyi & Fils',
        'donor_address' => ['line1' => '12 avenue du Commerce', 'city' => 'Kinshasa', 'country' => 'CD'],
    ]);

    app(AnonymizeContact::class)->handle($contact, $admin);

    $freshOrder = $order->fresh();
    expect($freshOrder->buyer_name)->toBe('Invité anonymisé');
    expect($freshOrder->buyer_email)->toBe('');
    expect($freshOrder->buyer_phone_e164)->toBeNull();
    // Lignes comptables conservées (règle 4.5 du CLAUDE.md).
    expect($freshOrder->status)->toBe(OrderStatus::Paid);
    expect($freshOrder->paid_at)->not->toBeNull();

    $freshDonation = $donation->fresh();
    expect($freshDonation->donor_name)->toBeNull();
    expect($freshDonation->donor_company)->toBeNull();
    expect($freshDonation->donor_address)->toBeNull();
    expect($freshDonation->amount->amountMinor())->toBe(2500000);
    expect($freshDonation->amount->currency())->toBe('CDF');
    app(CurrentOrganization::class)->clear();
});

it('ne touche pas au don d\'une autre personne', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    app(CurrentOrganization::class)->set($organization);
    $event = Event::factory()->for($organization)->published()->create();
    $grace = Contact::factory()->for($organization)->create(['email' => 'grace@example.com']);
    $paul = Contact::factory()->for($organization)->create(['email' => 'paul@example.com']);
    registerContactForEvent($organization, $event, $grace, RegistrationStatus::Confirmed);
    $registrationDePaul = registerContactForEvent($organization, $event, $paul, RegistrationStatus::Confirmed);
    $commandeDePaul = Order::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'registration_id' => $registrationDePaul->id,
        'buyer_name' => 'Paul Kasongo',
        'buyer_email' => 'paul@example.com',
    ]);

    app(AnonymizeContact::class)->handle($grace, $admin);

    expect($commandeDePaul->fresh()->buyer_name)->toBe('Paul Kasongo');
    app(CurrentOrganization::class)->clear();
});
