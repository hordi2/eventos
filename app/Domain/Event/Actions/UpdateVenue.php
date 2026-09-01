<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Venue;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateVenue
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Venue $venue, User $editor, array $data): Venue
    {
        Gate::forUser($editor)->authorize('update', $venue);

        $venue->update($data);

        return $venue->refresh();
    }
}
