<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Models\User;
use App\Support\MultiTenancy\OrganizationScope;
use Carbon\CarbonImmutable;
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

        $timezone = $data['timezone'] ?? $event->timezone;

        // La conversion explicite en UTC est nécessaire car Eloquent formate une date
        // castée en utilisant le fuseau *courant* de l'objet Carbon fourni, pas UTC :
        // sans ce ->utc(), la colonne recevrait l'heure locale telle quelle.
        if (array_key_exists('start_at', $data)) {
            $data['start_at'] = CarbonImmutable::parse($data['start_at'], $timezone)->utc();
        }

        if (array_key_exists('end_at', $data)) {
            // Un end_at explicitement vide (mais présent) signifie « pas de date de fin
            // saisie » : on retombe sur la même valeur par défaut que CreateEvent
            // (début + 3h), plutôt que de laisser Carbon::parse(null) interpréter ça
            // comme « maintenant ».
            $data['end_at'] = $data['end_at'] !== null
                ? CarbonImmutable::parse($data['end_at'], $timezone)->utc()
                : ($data['start_at'] ?? $event->start_at)->addHours(3);
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
