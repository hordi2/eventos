<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatingConstraint;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteSeatingConstraint
{
    public function handle(SeatingConstraint $constraint, User $user): void
    {
        Gate::forUser($user)->authorize('updateGuests', $constraint->organization);

        $constraint->delete();
    }
}
