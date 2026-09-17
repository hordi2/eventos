<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\CreateDonationOrder;
use App\Domain\Ticketing\Actions\MarkOrderPaid;
use App\Domain\Ticketing\Data\DonationPledge;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Mail\DonationReceiptMail;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['name' => 'Fondation Lumière']);
    app(CurrentOrganization::class)->set($this->organization);
    $this->event = Event::factory()->for($this->organization)->create(['title' => 'Gala de charité']);
});

function receiptPledge(?Money $amount = null): DonationPledge
{
    return new DonationPledge(
        registrationId: null,
        amount: $amount ?? Money::fromMinorUnits(12500, 'XAF'),
        cause: 'Bourses étudiantes',
        donorName: 'Marie Lusala',
        email: 'Marie@Example.com',
        address: ['line1' => '12 avenue du Port', 'city' => 'Pointe-Noire'],
        isAnonymous: true,
    );
}

it('crée une commande de don sans billet, une seule fois par clé', function (): void {
    $payableUntil = CarbonImmutable::now()->addDays(10)->startOfSecond();

    $order = app(CreateDonationOrder::class)->handle($this->organization->id, $this->event->id, receiptPledge(), 'don:gala', $payableUntil);
    $again = app(CreateDonationOrder::class)->handle($this->organization->id, $this->event->id, receiptPledge(), 'don:gala', $payableUntil);

    expect($again->id)->toBe($order->id);
    expect($order->items()->count())->toBe(0);
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->buyer_email)->toBe('marie@example.com');
    expect($order->total->equals(Money::fromMinorUnits(12500, 'XAF')))->toBeTrue();
    expect($order->reserved_until->equalTo($payableUntil))->toBeTrue();
    expect($order->donations)->toHaveCount(1);
});

it('refuse un don nul', function (): void {
    app(CreateDonationOrder::class)->handle($this->organization->id, $this->event->id, receiptPledge(Money::zero('XAF')), 'don:nul', CarbonImmutable::now()->addDay());
})->throws(InvalidArgumentException::class);

it('envoie le reçu du don une seule fois, même si le prestataire confirme deux fois', function (): void {
    Mail::fake();
    $order = app(CreateDonationOrder::class)->handle($this->organization->id, $this->event->id, receiptPledge(), 'don:recu', CarbonImmutable::now()->addDays(10));

    app(MarkOrderPaid::class)->handle($order, 'stripe', 'pi_don_1', $order->total);
    app(MarkOrderPaid::class)->handle(Order::query()->findOrFail($order->id), 'stripe', 'pi_don_1', $order->total);

    Mail::assertQueued(DonationReceiptMail::class, 1);

    $sent = null;
    Mail::assertQueued(DonationReceiptMail::class, function (DonationReceiptMail $mail) use (&$sent): bool {
        $sent = $mail;

        return $mail->hasTo('marie@example.com');
    });

    expect($sent->receipt)->toMatchArray([
        'organization' => 'Fondation Lumière',
        'event' => 'Gala de charité',
        'amount' => Money::fromMinorUnits(12500, 'XAF')->format(),
        'method' => 'Carte bancaire',
        'cause' => 'Bourses étudiantes',
        'donorAddress' => '12 avenue du Port, Pointe-Noire',
        'anonymous' => true,
    ]);
    expect($sent->render())->toContain('Reçu n°')->toContain('Pointe-Noire')->toContain('reste anonyme');
});

it('n\'envoie aucun reçu de don pour une commande de billets sans don', function (): void {
    Mail::fake();
    $order = Order::factory()->create(['organization_id' => $this->organization->id, 'event_id' => $this->event->id]);

    app(MarkOrderPaid::class)->handle($order, 'stripe', 'pi_billet', $order->total);

    Mail::assertNotQueued(DonationReceiptMail::class);
});
