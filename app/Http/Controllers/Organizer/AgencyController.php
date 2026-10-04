<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\CreateClientAccount;
use App\Domain\Organization\Actions\ReleaseClientAccount;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Agency\CreateClientAccountRequest;
use App\Models\User;
use App\Support\Agency\ClientAccountData;
use App\Support\Agency\GetAgencyPortfolio;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portail agence (D10) : les comptes clients d'une agence événementielle,
 * ce que chacun porte, et le total du portefeuille. Un compte client reste
 * une organisation à part entière — l'agence peut la lui rendre.
 */
final class AgencyController extends Controller
{
    public function index(GetAgencyPortfolio $getAgencyPortfolio): Response
    {
        $agency = $this->agency();
        Gate::authorize('manageClients', $agency);

        $portfolio = $getAgencyPortfolio->handle($agency);

        return Inertia::render('Agency/Index', [
            'agency' => ['name' => $agency->name],
            'portfolio' => [
                'eventCount' => $portfolio->eventCount,
                'registrationCount' => $portfolio->registrationCount,
                'revenue' => $portfolio->revenue->format(),
                'clients' => array_map(fn (ClientAccountData $client): array => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'slug' => $client->slug,
                    'eventCount' => $client->eventCount,
                    'nextEventTitle' => $client->nextEventTitle,
                    'nextEventDate' => $client->nextEventDate,
                    'registrationCount' => $client->registrationCount,
                    'revenue' => $client->revenue->format(),
                    'managedSince' => $client->managedSince,
                ], $portfolio->clients),
            ],
        ]);
    }

    public function store(CreateClientAccountRequest $request, CreateClientAccount $createClientAccount): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $client = $createClientAccount->handle($this->agency(), $user, $request->string('name')->toString());

        return redirect()
            ->route('agency.index')
            ->with('success', "Le compte « {$client->name} » est ouvert.");
    }

    public function destroy(Request $request, int $client, ReleaseClientAccount $releaseClientAccount): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $agency = $this->agency();
        $clientAccount = Organization::query()->findOrFail($client);

        $releaseClientAccount->handle($agency, $clientAccount, $user);

        return redirect()
            ->route('agency.index')
            ->with('success', "Le compte « {$clientAccount->name} » a été rendu à son client.");
    }

    private function agency(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
