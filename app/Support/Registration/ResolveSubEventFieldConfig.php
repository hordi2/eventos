<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\FieldType;

/**
 * Bloc « Événements secondaires » du constructeur : ne garde que les
 * sessions qui appartiennent vraiment à l'événement, avec leur titre
 * actuel. Le titre envoyé par le navigateur n'est jamais repris ; celui
 * enregistré ici est figé avec la version publiée du formulaire (§4.7).
 */
final class ResolveSubEventFieldConfig
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public function handle(int $eventId, array $fields): array
    {
        $titles = Event::query()->where('parent_event_id', $eventId)->pluck('title', 'id');

        foreach ($fields as $index => $field) {
            if (($field['type'] ?? null) !== FieldType::SubEvents->value) {
                continue;
            }

            $config = is_array($field['config'] ?? null) ? $field['config'] : [];
            $submitted = is_array($config['sub_events'] ?? null) ? $config['sub_events'] : [];

            $config['sub_events'] = collect($submitted)
                ->map(fn (mixed $subEvent): int => is_array($subEvent) ? (int) ($subEvent['id'] ?? 0) : 0)
                ->filter(fn (int $id): bool => $titles->has($id))
                ->unique()
                ->values()
                ->map(fn (int $id): array => ['id' => $id, 'title' => (string) $titles->get($id)])
                ->all();

            // Le choix des sessions vaut pour tout le groupe (AskScope).
            unset($config['ask_scope']);
            $fields[$index]['config'] = $config;
        }

        return $fields;
    }
}
