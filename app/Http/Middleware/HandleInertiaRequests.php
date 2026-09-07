<?php

declare(strict_types=1);

namespace App\Http\Middleware;

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
            ],
        ];
    }

    /**
     * @return list<array{label: string, href: string}|array{label: string, items: list<array{label: string, href: string}>}>|null
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

        $gate = Gate::forUser($user);

        return array_values(array_filter([
            ['label' => 'Tableau de bord', 'href' => route('dashboard')],
            ['label' => 'Aide', 'href' => route('help.index')],
            $gate->allows('viewGuests', $organization) ? [
                'label' => 'Contacts',
                'items' => array_values(array_filter([
                    ['label' => 'Tous les contacts', 'href' => route('contacts.index')],
                    $gate->allows('updateGuests', $organization) ? ['label' => 'Ajouter un contact', 'href' => route('contacts.create')] : null,
                    $gate->allows('updateGuests', $organization) ? ['label' => 'Importer des contacts', 'href' => route('contact-imports.create')] : null,
                    $gate->allows('updateGuests', $organization) ? ['label' => 'Tags', 'href' => route('tags.index')] : null,
                ])),
            ] : null,
            $gate->allows('createEvents', $organization) ? [
                'label' => 'Événements',
                'items' => [
                    ['label' => 'Créer un événement', 'href' => route('events.create')],
                ],
            ] : null,
            $gate->allows('sendCommunications', $organization) ? [
                'label' => 'Communications',
                'items' => [
                    ['label' => "Modèles d'e-mails", 'href' => route('email-templates.index')],
                    ['label' => 'Modèles WhatsApp', 'href' => route('whatsapp-templates.index')],
                ],
            ] : null,
            ['label' => 'Paramètres', 'href' => route('settings.profile.edit')],
        ]));
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
            return ['branding' => false, 'billing' => false, 'auditLog' => false];
        }

        $gate = Gate::forUser($user);

        return [
            'branding' => $gate->allows('manageBranding', $organization),
            'billing' => $gate->allows('manageBilling', $organization),
            'auditLog' => $gate->allows('viewAuditLog', $organization),
        ];
    }
}
