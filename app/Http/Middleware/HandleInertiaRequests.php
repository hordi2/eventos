<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Closure : résolu au moment de la construction de la réponse
            // (après le middleware resolve-organization, qui s'exécute
            // après HandleInertiaRequests dans le pipeline), jamais ici —
            // sans quoi CurrentOrganization ne serait pas encore positionné.
            'nav' => fn (): ?array => $this->buildNav($request),
            'settingsAccess' => fn (): array => $this->buildSettingsAccess($request),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
                'plainToken' => fn (): ?string => $request->session()->get('plainToken'),
                'plainSecret' => fn (): ?string => $request->session()->get('plainSecret'),
            ],
        ];
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, href: string}>}>|null
     */
    private function buildNav(Request $request): ?array
    {
        $user = $request->user();
        $organizationId = app(CurrentOrganization::class)->id();

        if ($user === null || $organizationId === null) {
            return null;
        }

        $organization = Organization::query()->find($organizationId);

        if ($organization === null) {
            return null;
        }

        // Un seul groupe déroulant au niveau supérieur (demande utilisateur) :
        // Contacts, Communications et Paramètres restent atteignables depuis
        // le menu utilisateur et les pages Paramètres elles-mêmes, pas
        // depuis ce niveau de navigation.
        $events = Event::query()->orderByDesc('start_at')->get(['id', 'title'])
            ->map(fn (Event $event): array => ['label' => $event->title, 'href' => route('events.edit', $event)])
            ->all();

        return [
            [
                'label' => 'Mes événements',
                'items' => [
                    ['label' => 'Tous les événements', 'href' => route('dashboard')],
                    ...$events,
                ],
            ],
        ];
    }

    /**
     * Sections de la page Paramètres visibles pour l'utilisateur courant
     * (SettingsLayout.tsx) — même matrice de capacités que buildNav(),
     * calculée une seule fois pour éviter d'exposer un lien vers une page
     * que can-organization:xxx refuserait ensuite.
     *
     * @return array<string, bool>
     */
    private function buildSettingsAccess(Request $request): array
    {
        $user = $request->user();
        $organizationId = app(CurrentOrganization::class)->id();
        $organization = $organizationId !== null ? Organization::query()->find($organizationId) : null;

        if ($user === null || $organization === null) {
            return ['branding' => false, 'billing' => false, 'auditLog' => false, 'integrations' => false];
        }

        $gate = Gate::forUser($user);

        return [
            'branding' => $gate->allows('manageBranding', $organization),
            'billing' => $gate->allows('manageBilling', $organization),
            'auditLog' => $gate->allows('viewAuditLog', $organization),
            'integrations' => $gate->allows('manageIntegrations', $organization),
        ];
    }
}
