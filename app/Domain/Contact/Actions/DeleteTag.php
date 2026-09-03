<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteTag
{
    public function handle(Tag $tag, User $deleter): void
    {
        Gate::forUser($deleter)->authorize('delete', $tag);

        $tag->delete();
    }
}
