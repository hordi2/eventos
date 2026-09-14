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
                ? $this->withChecklistLinks($this->getOrganizationEventSummaries->handle($organization))
                : $this->sharedEvents($user, $organization),
            'canCreateEvents' => $gate->allows('createEvents', $organization),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sharedEvents(User $user, Organization $organization): array
    {
        $sharedEventIds = array_keys($this->collaboratorAccess->sharedEvents($user, $organization->id));

        if ($sharedEventIds === []) {
            return [];
        }

        return $this->withChecklistLinks($this->getOrganizationEventSummaries->handle($organization, $sharedEventIds));
    }

    /**
     * Chaque carte ouvre la liste de contrôle, page d'arrivée d'un événement.
     *
     * @param  list<array<string, mixed>>  $summaries
     * @return list<array<string, mixed>>
     */
    private function withChecklistLinks(array $summaries): array
    {
        return array_map(
            fn (array $summary): array => [...$summary, 'href' => route('events.show', $summary['id'])],
            $summaries,
        );
    }
}
