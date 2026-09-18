<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Tag;

/**
 * Tags donnés par leur nom (colonne « Tags » d'un import, champ de la liste
 * d'invités) : un tag existant est repris sans tenir compte de la casse, un
 * nouveau est créé. Appelée depuis un import en file d'attente, sans
 * utilisateur : l'autorisation a été vérifiée en amont.
 */
final class ResolveTagsByName
{
    /**
     * Même couleur par défaut que l'écran des tags.
     */
    private const DEFAULT_COLOR = '#1a6e42';

    private const MAX_NAME_LENGTH = 50;

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    public function handle(int $organizationId, array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = mb_substr(trim($name), 0, self::MAX_NAME_LENGTH);

            if ($name === '') {
                continue;
            }

            $tag = Tag::query()
                ->where('organization_id', $organizationId)
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->first()
                ?? Tag::query()->create(['organization_id' => $organizationId, 'name' => $name, 'color' => self::DEFAULT_COLOR]);

            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * « VIP, Brunch » → ['VIP', 'Brunch'] : la virgule sépare les tags.
     *
     * @return list<string>
     */
    public static function split(?string $raw): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', (string) $raw)), fn (string $name): bool => $name !== ''));
    }
}
