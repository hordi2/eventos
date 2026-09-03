<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateTag
{
    public function handle(Tag $tag, User $updater, string $name, string $color): Tag
    {
        Gate::forUser($updater)->authorize('update', $tag);

        $tag->update(['name' => $name, 'color' => $color]);

        return $tag;
    }
}
