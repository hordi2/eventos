<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'buyer_name' => $this->faker->name(),
            'buyer_email' => $this->faker->safeEmail(),
            'buyer_phone_e164' => null,
            'status' => OrderStatus::Pending,
            'reservation_key' => (string) Str::uuid(),
            'total' => Money::fromMinorUnits(2000, 'EUR'),
            'reserved_until' => now()->addMinutes(15),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'reserved_until' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Pending,
            'reserved_until' => now()->subMinute(),
        ]);
    }
}
