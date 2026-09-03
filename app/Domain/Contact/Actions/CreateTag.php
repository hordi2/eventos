<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Tag;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateTag
{
    public function handle(Organization $organization, User $creator, string $name, string $color): Tag
    {
        Gate::forUser($creator)->authorize('create', [Tag::class, $organization]);

        return Tag::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'color' => $color,
        ]);
    }
}
