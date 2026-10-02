<?php

declare(strict_types=1);

namespace App\Domain\Page\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Models\PageBlockType;
use App\Domain\Page\Support\PageBlocks;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdatePage
{
    /**
     * $blocks : la page telle que l'organisateur l'a composée, dans son
     * ordre (lot 2). program_items/faq_items restent renseignés à partir
     * des blocs correspondants : ils servent encore aux pages jamais
     * recomposées et aux exports.
     *
     * $cover : les réglages d'ensemble de l'invitation — la couverture
     * (phrase d'ouverture, mot manuscrit, monogramme, voile, libellé du
     * bouton) et les trois polices. Absent, rien n'y est touché.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $cover
     */
    public function handle(
        Organization $organization,
        int $eventId,
        ?string $metaDescription,
        array $blocks,
        User $user,
        array $cover = [],
    ): Page {
        Gate::forUser($user)->authorize('updateEvents', $organization);

        $blocks = PageBlocks::normalize($blocks);

        return Page::query()->updateOrCreate(
            ['event_id' => $eventId],
            [
                'organization_id' => $organization->id,
                'meta_description' => $metaDescription,
                ...$this->coverAttributes($cover),
                'blocks' => $blocks,
                'program_items' => $this->itemsOf($blocks, PageBlockType::Program),
                'faq_items' => $this->itemsOf($blocks, PageBlockType::Faq),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $cover
     * @return array<string, mixed>
     */
    private function coverAttributes(array $cover): array
    {
        $attributes = [];

        foreach (['cover_eyebrow', 'cover_script', 'cover_monogram', 'cover_cta_label', 'heading_font', 'body_font', 'script_font'] as $key) {
            if (array_key_exists($key, $cover)) {
                $value = is_string($cover[$key]) ? trim($cover[$key]) : null;
                $attributes[$key] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('cover_overlay', $cover) && is_numeric($cover['cover_overlay'])) {
            $attributes['cover_overlay'] = max(0, min(90, (int) $cover['cover_overlay']));
        }

        return $attributes;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, string|null>>
     */
    private function itemsOf(array $blocks, PageBlockType $type): array
    {
        $items = [];

        foreach ($blocks as $block) {
            if ($block['type'] === $type->value) {
                $items = [...$items, ...$block['items']];
            }
        }

        return $items;
    }
}
