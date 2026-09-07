<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use Illuminate\Support\Facades\Storage;

/**
 * Traverse Domain/Event, Domain/Form et Domain/Page : vit hors de ces
 * modules pour la même raison que GetEventDashboardStats (voir son
 * docblock) — la page d'accueil de l'organisateur (tableau de bord) montre
 * chaque événement avec sa bannière et la répartition confirmé/liste
 * d'attente/annulé de ses inscriptions, jamais un détail ligne à ligne.
 */
final class GetOrganizationEventSummaries
{
    /**
     * @return list<array{
     *     id: int, title: string, banner_url: ?string, lifecycle_status: string,
     *     start_at_formatted: string, is_past: bool,
     *     stats: array{confirmed: int, waitlisted: int, cancelled: int}
     * }>
     */
    public function handle(Organization $organization): array
    {
        $events = Event::query()->orderByDesc('start_at')->get();

        if ($events->isEmpty()) {
            return [];
        }

        $eventIds = $events->pluck('id')->all();
        $banners = $this->banners($eventIds);
        $registrationCounts = $this->registrationCounts($eventIds);

        return $events->map(function (Event $event) use ($banners, $registrationCounts): array {
            $lifecycle = $event->computedStatus();
            $counts = $registrationCounts[$event->id] ?? [];

            return [
                'id' => $event->id,
                'title' => $event->title,
                'banner_url' => $banners[$event->id] ?? null,
                'lifecycle_status' => $lifecycle->value,
                'start_at_formatted' => $event->start_at->setTimezone($event->timezone)->format('d/m/Y à H:i'),
                'is_past' => in_array($lifecycle->value, ['ended', 'archived'], true),
                'stats' => [
                    'confirmed' => $counts[RegistrationStatus::Confirmed->value] ?? 0,
                    'waitlisted' => $counts[RegistrationStatus::Waitlisted->value] ?? 0,
                    'cancelled' => $counts[RegistrationStatus::Cancelled->value] ?? 0,
                ],
            ];
        })->all();
    }

    /**
     * @param  list<int>  $eventIds
     * @return array<int, string>
     */
    private function banners(array $eventIds): array
    {
        return Page::query()
            ->whereIn('event_id', $eventIds)
            ->whereNotNull('banner_path')
            ->pluck('banner_path', 'event_id')
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->all();
    }

    /**
     * @param  list<int>  $eventIds
     * @return array<int, array<string, int>>
     */
    private function registrationCounts(array $eventIds): array
    {
        $counts = [];

        Registration::query()
            ->whereIn('event_id', $eventIds)
            ->selectRaw('event_id, status, count(*) as aggregate')
            ->groupBy('event_id', 'status')
            ->get()
            ->each(function (Registration $row) use (&$counts): void {
                $counts[$row->event_id][$row->status->value] = (int) $row->getAttribute('aggregate');
            });

        return $counts;
    }
}
