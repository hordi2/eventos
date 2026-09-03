<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
final class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'order_item_id' => OrderItemFactory::new(),
            'ticket_type_id' => TicketTypeFactory::new(),
            'status' => TicketStatus::Valid,
        ];
    }
}
