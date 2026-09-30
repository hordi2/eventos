<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\BudgetLine;
use App\Domain\Event\Models\Event;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;

/**
 * Poste de budget d'un événement (D7). Les montants arrivent tels qu'ils
 * ont été écrits (« 1 500 », « 12,50 ») et deviennent des entiers en unité
 * mineure, dans la devise de l'événement — jamais autre chose (règle 4.2).
 */
final class SaveBudgetLine
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, User $editor, array $data): BudgetLine
    {
        Gate::forUser($editor)->authorize('viewFinancials', $event->organization);

        return BudgetLine::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            ...$this->attributes($event, $data),
            'position' => BudgetLine::query()->where('event_id', $event->id)->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BudgetLine $line, Event $event, User $editor, array $data): BudgetLine
    {
        Gate::forUser($editor)->authorize('viewFinancials', $event->organization);

        $line->update($this->attributes($event, $data));

        return $line->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Event $event, array $data): array
    {
        $actual = $data['actual'] ?? null;

        return [
            'kind' => $data['kind'],
            'category' => $data['category'],
            'label' => $data['label'],
            'supplier' => $data['supplier'] ?? null,
            'note' => $data['note'] ?? null,
            'planned' => Money::parse((string) $data['planned'], $event->currency),
            // Rien de saisi : la dépense n'est pas engagée, ce qui ne se dit
            // pas comme « engagée pour zéro ».
            'actual' => is_string($actual) && $actual !== '' ? Money::parse($actual, $event->currency) : null,
        ];
    }
}
