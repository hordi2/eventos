<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Models\User;
use App\Support\MultiTenancy\OrganizationScope;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UpdateEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Event $event, User $editor, array $data): Event
    {
        Gate::forUser($editor)->authorize('update', $event);

        if (array_key_exists('timezone', $data)) {
            $this->assertValidTimezone($data['timezone']);
        }

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->resolveSlug($event, $data['slug']);
        }

        $event->update($data);

        return $event->refresh();
    }

    private function assertValidTimezone(string $timezone): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("\"{$timezone}\" n'est pas un fuseau horaire IANA valide.");
        }
    }

    private function resolveSlug(Event $event, string $base): string
    {
        $slug = Str::slug($base);
        $original = $slug;
        $suffix = 1;

        while (
            Event::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $event->organization_id)
                ->where('slug', $slug)
                ->where('id', '!=', $event->id)
                ->exists()
        ) {
            $slug = "{$original}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
