<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\FailOrderPayment;
use App\Domain\Ticketing\Actions\RecordOnSitePayment;
use App\Domain\Ticketing\Models\Donation;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Mail\DonationReceiptMail;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * Événement dont le formulaire propose un don (5 000 ou 10 000 FCFA, ou un
 * montant libre) au profit des bourses, et les informations du donateur.
 *
 * @return array{organization: Organization, event: Event, base: string}
 */
function donationGuestEvent(bool $donationRequired = false): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([
        [
            'key' => 'don',
            'type' => 'donation',
            'label' => 'Souhaitez-vous soutenir le projet ?',
            'is_required' => $donationRequired,
            'config' => ['show_if' => 'always', 'currency' => 'XAF', 'amounts' => [5000, 10000], 'allow_custom' => true, 'cause' => 'Bourses étudiantes'],
        ],
        ['key' => 'donateur', 'type' => 'donor_info', 'label' => 'Vos informations de donateur', 'is_required' => true, 'config' => ['show_if' => 'always']],
    ], ['type' => EventType::Conference, 'allow_guest_edit' => true]);

    return ['organization' => $organization, 'event' => $event, 'base' => "/r/{$organization->slug}/{$event->slug}"];
}

/**
 * Commence l'inscription de Marie jusqu'à l'étape des réponses.
 */
function startDonationRegistration(TestCase $test, Event $event, string $base): string
{
    $test->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $test->post("{$base}/{$token}/identite", ['email' => 'marie@example.com', 'first_name' => 'Marie', 'last_name' => 'Lusala']);

    return $token;
}

/**
 * @return array<string, mixed>
 */
function donorDetails(): array
{
    return ['name' => 'Marie Lusala', 'company' => 'Itaza SARL', 'line1' => '12 avenue du Port', 'city' => 'Pointe-Noire', 'country' => 'Congo', 'anonymous' => '1'];
}

function marieRegistration(): Registration
{
    return Registration::withoutGlobalScopes()->where('email', 'marie@example.com')->firstOrFail();
}

it('propose le don, puis ouvre après l\'inscription une commande à régler liée à l\'inscription', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base] = donationGuestEvent();
    $token = startDonationRegistration($this, $event, $base);

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('name="don[choice]"', false)
        ->assertSee(Money::fromMinorUnits(5000, 'XAF')->format(), false)
        ->assertSee('Bourses étudiantes')
        ->assertSee('value="Marie Lusala"', false);

    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => 'autre', 'custom' => '12 500'], 'donateur' => donorDetails()])
        ->assertSessionHasNoErrors()
        ->assertRedirect("{$base}/{$token}/recap");

    $this->get("{$base}/{$token}/recap")->assertSee(Money::fromMinorUnits(12500, 'XAF')->format(), false);
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    $order = Order::withoutGlobalScopes()->where('registration_id', marieRegistration()->id)->firstOrFail();
    $donation = Donation::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Pending);
    // La clé figure dans l'adresse publique de paiement : jamais devinable.
    expect($order->reservation_key)->not->toContain('registration')->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    expect($order->total->equals(Money::fromMinorUnits(12500, 'XAF')))->toBeTrue();
    expect($order->reserved_until->isAfter(now()->addDay()))->toBeTrue();
    expect($donation->cause)->toBe('Bourses étudiantes');
    expect($donation->donor_company)->toBe('Itaza SARL');
    expect($donation->donor_address)->toEqual(['line1' => '12 avenue du Port', 'city' => 'Pointe-Noire', 'country' => 'Congo']);
    expect($donation->is_anonymous)->toBeTrue();

    $paymentPath = "/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/paiement";

    $this->get("{$base}/{$token}/confirmation")->assertSee('Finaliser mon don')->assertSee($paymentPath, false);
    $this->get($paymentPath)->assertOk()->assertSee('Régler votre don')->assertSee("Régler à l'accueil le jour de l'événement");

    // Confirmer une seconde fois n'ouvre pas une seconde commande.
    $this->post("{$base}/{$token}/recap");
    expect(Order::withoutGlobalScopes()->where('registration_id', marieRegistration()->id)->count())->toBe(1);
});

it('n\'exige pas les informations du donateur d\'un invité qui ne donne pas, et n\'ouvre aucune commande', function (): void {
    ['event' => $event, 'base' => $base] = donationGuestEvent();
    $token = startDonationRegistration($this, $event, $base);

    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => '', 'custom' => ''], 'donateur' => ['name' => '']])
        ->assertSessionHasNoErrors()
        ->assertRedirect("{$base}/{$token}/recap");
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    expect(Order::withoutGlobalScopes()->where('registration_id', marieRegistration()->id)->exists())->toBeFalse();
    $this->get("{$base}/{$token}/confirmation")->assertOk()->assertDontSee('Votre don');
});

it('refuse un montant libre illisible, un don obligatoire sans choix et un donateur incomplet', function (): void {
    ['event' => $event, 'base' => $base] = donationGuestEvent(donationRequired: true);
    $token = startDonationRegistration($this, $event, $base);

    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => 'autre', 'custom' => '12,5']])
        ->assertSessionHasErrors('don.custom')
        ->assertSessionDoesntHaveErrors('donateur.name');

    $this->post("{$base}/{$token}/reponses", ['don' => ['custom' => '']])->assertSessionHasErrors('don.choice');

    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => '5000'], 'donateur' => ['name' => '']])
        ->assertSessionHasErrors(['donateur.name', 'donateur.line1', 'donateur.city']);
});

it('enregistre une promesse de don réglée à l\'accueil, puis envoie le reçu à l\'encaissement', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'base' => $base] = donationGuestEvent();
    $token = startDonationRegistration($this, $event, $base);
    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => '10000'], 'donateur' => donorDetails()]);
    $this->post("{$base}/{$token}/recap");

    $order = Order::withoutGlobalScopes()->where('registration_id', marieRegistration()->id)->firstOrFail();
    $this->post("/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}/paiement/arrivee")->assertRedirect();

    $this->get("{$base}/{$token}/confirmation")->assertSee('Promesse enregistrée');
    Mail::assertNotQueued(DonationReceiptMail::class);

    $doorStaff = User::factory()->create();
    Membership::factory()->for($organization)->for($doorStaff)->create(['role' => MembershipRole::DoorStaff]);
    app(CurrentOrganization::class)->set($organization);
    app(RecordOnSitePayment::class)->handle(Order::query()->findOrFail($order->id), $doorStaff, Money::fromMinorUnits(10000, 'XAF'));

    Mail::assertQueued(DonationReceiptMail::class, fn (DonationReceiptMail $mail): bool => $mail->hasTo('marie@example.com')
        && $mail->receipt['amount'] === Money::fromMinorUnits(10000, 'XAF')->format()
        && $mail->receipt['method'] === "Espèces, à l'accueil"
        && $mail->receipt['donorCompany'] === 'Itaza SARL');
});

it('propose de réessayer le paiement d\'un don refusé depuis la confirmation d\'inscription', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base] = donationGuestEvent();
    $token = startDonationRegistration($this, $event, $base);
    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => '5000'], 'donateur' => donorDetails()]);
    $this->post("{$base}/{$token}/recap");

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('registration_id', marieRegistration()->id)->firstOrFail();
    app(FailOrderPayment::class)->handle($order, 'flutterwave', null, 'Paiement Mobile Money refusé.');
    app(CurrentOrganization::class)->clear();

    $orderBase = "/billets/{$organization->slug}/{$event->slug}/{$order->reservation_key}";

    $this->get("{$base}/{$token}/confirmation")
        ->assertSee('Le paiement de votre don n\'a pas abouti', false)
        ->assertSee("{$orderBase}/statut", false);

    $this->post("{$orderBase}/paiement/reessayer")->assertRedirect("{$orderBase}/paiement");

    $reopened = Order::withoutGlobalScopes()->findOrFail($order->id);
    expect($reopened->status)->toBe(OrderStatus::Pending);
    expect($reopened->reserved_until->isAfter(now()->addDay()))->toBeTrue();
    $this->get("{$base}/{$token}/confirmation")->assertSee('Finaliser mon don');
});

it('garde le don tel quel quand l\'invité modifie son inscription', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base] = donationGuestEvent();
    $token = startDonationRegistration($this, $event, $base);
    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => '5000'], 'donateur' => donorDetails()]);
    $this->post("{$base}/{$token}/recap");

    $registration = marieRegistration();
    $editUrl = URL::temporarySignedRoute('guest.registration.edit', now()->addDay(), [$organization->slug, $event->slug, $registration->id]);

    $this->get($editUrl)->assertOk()->assertSee('ne se modifie pas ici')->assertDontSee('name="don[choice]"', false);
    $this->post($editUrl, ['email' => 'marie@example.com', 'first_name' => 'Marie', 'last_name' => 'Kalala'])->assertOk();

    expect(RegistrationAnswer::withoutGlobalScopes()->where('registration_id', $registration->id)->count())->toBe(2);
    expect(marieRegistration()->last_name)->toBe('Kalala');
});
