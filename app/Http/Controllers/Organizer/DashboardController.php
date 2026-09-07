<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Support\Dashboard\GetOrganizationEventSummaries;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page d'accueil de l'organisateur : la galerie de ses événements, avec un
 * aperçu de fréquentation par événement — jamais les invités eux-mêmes
 * (chargement en masse, §M8.1 hors périmètre de cette vue).
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, GetOrganizationEventSummaries $getOrganizationEventSummaries): Response
    {
        $organizationId = app(CurrentOrganization::class)->requireId();
        $organization = Organization::query()->findOrFail($organizationId);
        $gate = Gate::forUser($request->user());
        $canViewEvents = $gate->allows('updateEvents', $organization);

        return Inertia::render('Dashboard', [
            'events' => $canViewEvents ? $getOrganizationEventSummaries->handle($organization) : [],
            'canCreateEvents' => $gate->allows('createEvents', $organization),
        ]);
    }
}
