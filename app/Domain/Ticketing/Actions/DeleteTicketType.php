<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteTicketType
{
    public function handle(TicketType $ticketType, User $deleter): void
    {
        Gate::forUser($deleter)->authorize('delete', $ticketType);

        $ticketType->delete();
    }
}
