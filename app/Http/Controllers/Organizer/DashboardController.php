<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Services\CollaboratorAccess;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Dashboard\GetOrganizationEventSummaries;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page d'accueil de l'organisateur : la galerie de ses événements, avec un
 * aperçu de fréquentation par événement — jamais les invités eux-mêmes
 * (chargement en masse, §M8.1 hors périmètre de cette vue). Un collaborateur
 * y retrouve seulement les événements partagés avec lui.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly GetOrganizationEventSummaries $getOrganizationEventSummaries,
        private readonly CollaboratorAccess $collaboratorAccess,
    ) {}

    public function __invoke(Request $request): Response
    {
        $organization = Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
        /** @var User $user */
        $user = $request->user();
        $gate = Gate::forUser($user);

        return Inertia::render('Dashboard', [
            'events' => $gate->allows('updateEvents', $organization)
                ? $this->allEvents($organization)
                : $this->sharedEvents($user, $organization),
            'canCreateEvents' => $gate->allows('createEvents', $organization),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allEvents(Organization $organization): array
    {
        return array_map(
            fn (array $summary): array => [...$summary, 'href' => route('events.edit', $summary['id'])],
            $this->getOrganizationEventSummaries->handle($organization),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sharedEvents(User $user, Organization $organization): array
    {
        $sharedEvents = $this->collaboratorAccess->sharedEvents($user, $organization->id);

        if ($sharedEvents === []) {
            return [];
        }

        return array_map(
            fn (array $summary): array => [...$summary, 'href' => $sharedEvents[$summary['id']]->eventUrl($summary['id'])],
            $this->getOrganizationEventSummaries->handle($organization, array_keys($sharedEvents)),
        );
    }
}
