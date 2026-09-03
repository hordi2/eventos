<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'order_id' => OrderFactory::new(),
            'provider' => 'stripe',
            'provider_payment_id' => (string) Str::uuid(),
            'status' => PaymentStatus::Succeeded,
            'amount' => Money::fromMinorUnits(2000, 'EUR'),
            'attempted_at' => now(),
            'succeeded_at' => now(),
        ];
    }
}
