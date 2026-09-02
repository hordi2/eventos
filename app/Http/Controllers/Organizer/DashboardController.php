<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vue d'ensemble de l'organisation courante : au-delà du menu, cette page
 * doit permettre de « se situer » — quels services existent déjà, combien
 * de données ils contiennent, quoi de récent. Chaque section n'apparaît que
 * si l'utilisateur a la capacité d'y accéder (mêmes policies que les pages
 * qu'elle pointe).
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $organizationId = app(CurrentOrganization::class)->requireId();
        $organization = Organization::query()->findOrFail($organizationId);
        $gate = Gate::forUser($request->user());

        return Inertia::render('Dashboard', [
            'services' => [
                'contacts' => $this->contactsService($gate, $organization),
                'events' => $this->eventsService($gate, $organization),
                'imports' => $this->importsService($gate, $organization),
                'auditLog' => $this->auditLogService($gate, $organization, $organizationId),
            ],
        ]);
    }

    /**
     * @return array{canView: bool, canCreate: bool, count: int}
     */
    private function contactsService(GateContract $gate, Organization $organization): array
    {
        $canView = $gate->allows('viewGuests', $organization);

        return [
            'canView' => $canView,
            'canCreate' => $gate->allows('updateGuests', $organization),
            'count' => $canView ? Contact::query()->count() : 0,
        ];
    }

    /**
     * @return array{canView: bool, canCreate: bool, count: int, recent: list<array{id: int, title: string, status: string, start_at_formatted: string}>}
     */
    private function eventsService(GateContract $gate, Organization $organization): array
    {
        $canView = $gate->allows('updateEvents', $organization);

        return [
            'canView' => $canView,
            'canCreate' => $gate->allows('createEvents', $organization),
            'count' => $canView ? Event::query()->count() : 0,
            'recent' => $canView
                ? Event::query()
                    ->latest('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (Event $event): array => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'status' => $event->status->value,
                        // Mis en forme ici, dans le fuseau de l'événement — jamais
                        // celui du serveur (règle 4.3 du CLAUDE.md) — pour que le
                        // front n'ait pas à réinterpréter la date dans le fuseau
                        // du navigateur, ce qu'un format ISO nu l'inciterait à faire.
                        'start_at_formatted' => $event->start_at->setTimezone($event->timezone)->format('d/m/Y à H:i'),
                    ])
                    ->all()
                : [],
        ];
    }

    /**
     * @return array{canView: bool, count: int, recent: list<array{id: int, filename: string, status: string}>}
     */
    private function importsService(GateContract $gate, Organization $organization): array
    {
        $canView = $gate->allows('updateGuests', $organization);

        return [
            'canView' => $canView,
            'count' => $canView ? ContactImport::query()->count() : 0,
            'recent' => $canView
                ? ContactImport::query()
                    ->latest('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (ContactImport $import): array => [
                        'id' => $import->id,
                        'filename' => $import->original_filename,
                        'status' => $import->status->value,
                    ])
                    ->all()
                : [],
        ];
    }

    /**
     * @return array{canView: bool, count: int}
     */
    private function auditLogService(GateContract $gate, Organization $organization, int $organizationId): array
    {
        $canView = $gate->allows('viewAuditLog', $organization);

        return [
            'canView' => $canView,
            'count' => $canView ? AuditLog::query()->where('organization_id', $organizationId)->count() : 0,
        ];
    }
}
