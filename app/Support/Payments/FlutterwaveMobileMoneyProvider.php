<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Intégration Flutterwave (T-053), flux "charges" à 3 étapes documenté sur
 * developer.flutterwave.com/docs/mobile-money (environnement sandbox par
 * défaut, cf. services.flutterwave.base_url) : créer un client, créer un
 * moyen de paiement mobile money, créer la charge qui référence les deux.
 *
 * Les montants Flutterwave sont en unité majeure (ex. 5000 = 5000 XOF),
 * contrairement à Stripe qui attend toujours des centimes — d'où la
 * conversion locale ci-dessous plutôt qu'une méthode publique sur Money
 * (Money reste toujours en unité mineure en interne, §4.2 CLAUDE.md).
 *
 * L'adresse exigée par la création du client n'est pas collectée par le
 * parcours d'achat actuel (T-051 : nom, e-mail, téléphone seulement) — les
 * champs d'adresse sont donc renseignés au mieux (pays déduit de
 * l'indicatif) en attendant un vrai compte Flutterwave pour vérifier ce
 * qui est réellement obligatoire.
 */
final class FlutterwaveMobileMoneyProvider implements MobileMoneyProvider
{
    /**
     * @var array<string, int>
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'XOF' => 0,
        'XAF' => 0,
    ];

    /**
     * Clés numériques : PHP normalise automatiquement une clé de tableau
     * qui ressemble à un entier ("243") en entier — sans conséquence ici,
     * countryCode (string) fonctionne quand même comme clé de lookup.
     *
     * @var array<int, string>
     */
    private const COUNTRY_CODE_TO_ISO2 = [
        '243' => 'CD',
        '242' => 'CG',
        '237' => 'CM',
        '225' => 'CI',
        '221' => 'SN',
    ];

    public function initiateCharge(MobileMoneyChargeRequest $request): string
    {
        try {
            $customerId = $this->createCustomer($request);
            $paymentMethodId = $this->createPaymentMethod($request);

            $response = $this->client()->post('/charges', [
                'currency' => $request->amount->currency(),
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'amount' => $this->toMajorUnits($request->amount),
                'reference' => "order-{$request->orderId}-".Str::uuid(),
            ]);

            if ($response->failed()) {
                throw MobileMoneyProviderUnavailableException::forProvider('flutterwave');
            }

            return (string) $response->json('data.id');
        } catch (ConnectionException) {
            throw MobileMoneyProviderUnavailableException::forProvider('flutterwave');
        }
    }

    public function getChargeStatus(string $chargeId): MobileMoneyChargeStatus
    {
        $response = $this->client()->get("/charges/{$chargeId}");

        if ($response->failed()) {
            throw MobileMoneyProviderUnavailableException::forProvider('flutterwave');
        }

        return match ($response->json('data.status')) {
            'succeeded' => MobileMoneyChargeStatus::Succeeded,
            'failed' => MobileMoneyChargeStatus::Failed,
            default => MobileMoneyChargeStatus::Pending,
        };
    }

    private function createCustomer(MobileMoneyChargeRequest $request): string
    {
        $response = $this->client()->post('/customers', [
            'email' => $request->buyerEmail,
            'name' => ['first' => $request->buyerFirstName, 'last' => $request->buyerLastName],
            'phone' => ['country_code' => $request->countryCode, 'number' => $request->phoneNumber],
            'address' => [
                'country' => self::COUNTRY_CODE_TO_ISO2[$request->countryCode] ?? '',
                'city' => '',
                'line1' => '',
                'postal_code' => '',
                'state' => '',
            ],
        ]);

        if ($response->failed()) {
            throw MobileMoneyProviderUnavailableException::forProvider('flutterwave');
        }

        return (string) $response->json('data.id');
    }

    private function createPaymentMethod(MobileMoneyChargeRequest $request): string
    {
        $response = $this->client()->post('/payment-methods', [
            'type' => 'mobile_money',
            'mobile_money' => [
                'country_code' => $request->countryCode,
                'network' => $request->network,
                'phone_number' => $request->phoneNumber,
            ],
        ]);

        if ($response->failed()) {
            throw MobileMoneyProviderUnavailableException::forProvider('flutterwave');
        }

        return (string) $response->json('data.id');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.flutterwave.base_url'))
            ->withToken((string) config('services.flutterwave.secret'))
            ->acceptJson();
    }

    private function toMajorUnits(Money $amount): int
    {
        $exponent = self::ZERO_DECIMAL_CURRENCIES[$amount->currency()] ?? 2;

        return intdiv($amount->amountMinor(), 10 ** $exponent);
    }
}
