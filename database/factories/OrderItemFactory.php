<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\OrderItem;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'order_id' => OrderFactory::new(),
            'ticket_type_id' => TicketTypeFactory::new(),
            'price_tier_id' => null,
            'name' => 'Billet standard',
            'quantity' => 1,
            'unit_amount' => Money::fromMinorUnits(2000, 'EUR'),
        ];
    }
}
