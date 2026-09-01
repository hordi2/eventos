<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\OrganizationScope;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, User $creator, array $data): Event
    {
        Gate::forUser($creator)->authorize('create', [Event::class, $organization]);

        $this->assertValidTimezone($data['timezone']);

        return Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'status' => EventStatus::Draft,
            'slug' => $this->resolveSlug($organization, $data['slug'] ?? $data['title']),
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'timezone' => $data['timezone'],
            'is_online' => $data['is_online'] ?? false,
            'online_url' => $data['online_url'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'registration_opens_at' => $data['registration_opens_at'] ?? null,
            'registration_closes_at' => $data['registration_closes_at'] ?? null,
            'access_mode' => $data['access_mode'] ?? EventAccessMode::Public,
            'requires_approval' => $data['requires_approval'] ?? false,
            'allow_waitlist' => $data['allow_waitlist'] ?? false,
            'allow_guest_edit' => $data['allow_guest_edit'] ?? false,
            'edit_deadline' => $data['edit_deadline'] ?? null,
            'currency' => $data['currency'] ?? null,
        ]);
    }

    private function assertValidTimezone(string $timezone): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("\"{$timezone}\" n'est pas un fuseau horaire IANA valide.");
        }
    }

    private function resolveSlug(Organization $organization, string $base, ?int $ignoreEventId = null): string
    {
        $slug = Str::slug($base);
        $original = $slug;
        $suffix = 1;

        while (
            Event::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organization->id)
                ->where('slug', $slug)
                ->when($ignoreEventId !== null, fn ($query) => $query->where('id', '!=', $ignoreEventId))
                ->exists()
        ) {
            $slug = "{$original}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
