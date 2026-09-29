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
     * @param  list<array<string, mixed>>  $blocks
     */
    public function handle(
        Organization $organization,
        int $eventId,
        ?string $metaDescription,
        array $blocks,
        User $user,
    ): Page {
        Gate::forUser($user)->authorize('updateEvents', $organization);

        $blocks = PageBlocks::normalize($blocks);

        return Page::query()->updateOrCreate(
            ['event_id' => $eventId],
            [
                'organization_id' => $organization->id,
                'meta_description' => $metaDescription,
                'blocks' => $blocks,
                'program_items' => $this->itemsOf($blocks, PageBlockType::Program),
                'faq_items' => $this->itemsOf($blocks, PageBlockType::Faq),
            ],
        );
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
