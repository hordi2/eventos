<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateVenue
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, User $creator, array $data): Venue
    {
        Gate::forUser($creator)->authorize('create', [Venue::class, $organization]);

        return Venue::query()->create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'access_instructions' => $data['access_instructions'] ?? null,
            'parking_info' => $data['parking_info'] ?? null,
        ]);
    }
}
