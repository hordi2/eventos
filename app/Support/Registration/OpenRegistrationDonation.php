<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Support\DonationAnswer;
use App\Domain\Ticketing\Actions\CreateDonationOrder;
use App\Domain\Ticketing\Actions\DetermineDonationPayableUntil;
use App\Domain\Ticketing\Data\DonationPledge;
use App\Domain\Ticketing\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Don promis dans le formulaire → commande à régler (T-056, décision
 * produit : « paiement après l'inscription »). Traverse Form (réponses) et
 * Ticketing (commande), d'où Support. Une seule commande par inscription,
 * même si l'appel se répète.
 */
final class OpenRegistrationDonation
{
    public function __construct(
        private readonly CreateDonationOrder $createDonationOrder,
        private readonly DetermineDonationPayableUntil $determineDonationPayableUntil,
    ) {}

    public function handle(Event $event, Registration $registration): ?Order
    {
        $existing = Order::query()->where('registration_id', $registration->id)->with('donations')->first();

        if ($existing !== null) {
            return $existing;
        }

        $answers = $registration->answers()->with('formField')->get();

        foreach ($answers as $answer) {
            $amount = $answer->formField->type === FieldType::Donation ? DonationAnswer::stored($answer->value) : null;

            if ($amount !== null) {
                $donor = $answers->first(fn (RegistrationAnswer $candidate): bool => $candidate->formField->type === FieldType::DonorInfo);

                return $this->createDonationOrder->handle(
                    $registration->organization_id,
                    $event->id,
                    $this->pledge($registration, $answer, $amount, $donor),
                    $this->reservationKey($registration),
                    $this->payableUntil($event),
                );
            }
        }

        return null;
    }

    private function pledge(Registration $registration, RegistrationAnswer $donation, Money $amount, ?RegistrationAnswer $donor): DonationPledge
    {
        $details = is_array($donor?->value) ? $donor->value : [];
        $cause = ($donation->formField->config ?? [])['cause'] ?? null;
        $holderName = trim("{$registration->first_name} {$registration->last_name}");
        $donorName = is_string($details['name'] ?? null) ? $details['name'] : '';

        /** @var array<string, string> $address */
        $address = is_array($details['address'] ?? null) ? $details['address'] : [];

        return new DonationPledge(
            registrationId: $registration->id,
            amount: $amount,
            cause: is_string($cause) && $cause !== '' ? $cause : null,
            donorName: $donorName !== '' ? $donorName : ($holderName !== '' ? $holderName : $registration->email),
            email: $registration->email,
            phone: $registration->phone_e164,
            company: is_string($details['company'] ?? null) ? $details['company'] : null,
            address: $address,
            isAnonymous: ($details['anonymous'] ?? false) === true,
        );
    }

    /**
     * La clé figure dans l'adresse publique de paiement, qui montre le nom et
     * l'e-mail du donateur : jamais devinable depuis l'identifiant de
     * l'inscription (même règle que TicketOrderController), mais toujours la
     * même pour une inscription, pour qu'une double confirmation simultanée
     * ne crée qu'une commande.
     */
    private function reservationKey(Registration $registration): string
    {
        $hash = hash_hmac('sha256', "registration:{$registration->id}:donation", (string) config('app.key'));

        return implode('-', [substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12)]);
    }

    private function payableUntil(Event $event): CarbonImmutable
    {
        return $this->determineDonationPayableUntil->handle(CarbonImmutable::parse($event->end_at ?? $event->start_at));
    }
}
