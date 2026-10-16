<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\WhatsappFollowUpTemplate;
use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateDonationOrder;
use App\Domain\Ticketing\Actions\CreateOrder;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\Data\DonationPledge;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Mail\TicketsIssuedMail;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['name' => 'Festival Amani']);
    app(CurrentOrganization::class)->set($this->organization);
    $this->event = Event::factory()->for($this->organization)->create(['title' => 'Concert de clôture']);
    $this->ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $this->event->id]);
    PriceTier::factory()->for($this->ticketType)->for($this->organization)->create(['name' => 'Normal']);
});

it('envoie les billets par e-mail, un PDF par billet, dès que la commande est payée', function (): void {
    Mail::fake();

    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Grace Mbuyi', 'email' => 'grace@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 2]],
        (string) Str::uuid(),
    );

    app(MarkOrderPaid::class)->handle($order, 'stripe', 'pi_billets_1', $order->total);

    Mail::assertQueued(TicketsIssuedMail::class, 1);

    $sent = null;
    Mail::assertQueued(TicketsIssuedMail::class, function (TicketsIssuedMail $mail) use (&$sent): bool {
        $sent = $mail;

        return $mail->hasTo('grace@example.com');
    });

    expect($sent->context)->toMatchArray([
        'organization' => 'Festival Amani',
        'event' => 'Concert de clôture',
        'buyerName' => 'Grace Mbuyi',
        'ticketCount' => 2,
    ]);
    expect($sent->tickets)->toHaveCount(2);
    expect($sent->attachments())->toHaveCount(2);
});

it('n\'envoie aucun billet pour une commande de don sans billet', function (): void {
    Mail::fake();

    $pledge = new DonationPledge(
        registrationId: null,
        amount: Money::fromMinorUnits(5000, 'XAF'),
        cause: null,
        donorName: 'Paul Kanku',
        email: 'paul@example.com',
        address: [],
        isAnonymous: false,
    );
    $order = app(CreateDonationOrder::class)->handle($this->organization->id, $this->event->id, $pledge, 'don:test', CarbonImmutable::now()->addDay());

    app(MarkOrderPaid::class)->handle($order, 'stripe', 'pi_don_1', $order->total);

    Mail::assertNotQueued(TicketsIssuedMail::class);
});

it('prévient aussi par WhatsApp quand l\'organisation a activé ce canal pour les billets', function (): void {
    Mail::fake();
    Queue::fake();

    $this->organization->update(['follow_up_channel' => 'both']);
    $template = WhatsappTemplate::factory()->create(['organization_id' => $this->organization->id]);
    WhatsappFollowUpTemplate::query()->create([
        'organization_id' => $this->organization->id,
        'purpose' => 'ticket_issued',
        'whatsapp_template_id' => $template->id,
    ]);

    $order = app(CreateOrder::class)->handle(
        $this->organization->id, $this->event->id,
        ['name' => 'Awa Diallo', 'email' => 'awa@example.com'],
        [['ticket_type_id' => $this->ticketType->id, 'quantity' => 1]],
        (string) Str::uuid(),
    );
    Contact::query()->findOrFail($order->fresh()->contact_id)->update(['phone_e164' => '+243970000009', 'whatsapp_consent' => true]);

    app(MarkOrderPaid::class)->handle($order->fresh(), 'stripe', 'pi_billets_wa', $order->total);

    expect(WhatsappMessage::query()->count())->toBe(1);
    Mail::assertQueued(TicketsIssuedMail::class);
});
