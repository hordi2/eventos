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
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portail agence (D10) : les comptes clients d'une agence événementielle,
 * ce que chacun porte, et le total du portefeuille. Un compte client reste
 * une organisation à part entière — l'agence peut la lui rendre.
 */
final class AgencyController extends Controller
{
    public function index(Request $request, GetAgencyPortfolio $getAgencyPortfolio): Response
    {
        $agency = $this->agency();
        Gate::authorize('manageClients', $agency);

        [$from, $to] = $this->period($request);
        $portfolio = $getAgencyPortfolio->handle($agency, from: $from, to: $to);

        return Inertia::render('Agency/Index', [
            'agency' => ['name' => $agency->name],
            'period' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
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

    /**
     * Relevé d'activité d'un compte client, à joindre à la facture que
     * l'agence lui adresse (D10).
     */
    public function statement(Request $request, int $client, GetAgencyPortfolio $getAgencyPortfolio): HttpResponse
    {
        $agency = $this->agency();
        Gate::authorize('manageClients', $agency);

        [$from, $to] = $this->period($request);
        $portfolio = $getAgencyPortfolio->handle($agency, from: $from, to: $to);
        $row = null;

        foreach ($portfolio->clients as $candidate) {
            if ($candidate->id === $client) {
                $row = $candidate;
            }
        }

        abort_if($row === null, 404);

        $pdf = Pdf::loadView('organizer.agency-statement', [
            'agency' => $agency,
            'client' => $row,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'portrait')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="releve-'.Str::slug($row->name).'.pdf"',
        ]);
    }

    /**
     * Le portefeuille en tableur : une ligne par compte client, pour la
     * comptabilité de l'agence.
     */
    public function export(Request $request, GetAgencyPortfolio $getAgencyPortfolio): StreamedResponse
    {
        $agency = $this->agency();
        Gate::authorize('manageClients', $agency);

        [$from, $to] = $this->period($request);
        $portfolio = $getAgencyPortfolio->handle($agency, from: $from, to: $to);

        return response()->streamDownload(function () use ($portfolio): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Compte client', 'Confié depuis', 'Événements', 'Inscrits', 'Recettes']);

            foreach ($portfolio->clients as $client) {
                fputcsv($handle, [
                    $client->name,
                    $client->managedSince,
                    $client->eventCount,
                    $client->registrationCount,
                    $client->revenue->format(),
                ]);
            }

            fputcsv($handle, [
                'Total',
                null,
                $portfolio->eventCount,
                $portfolio->registrationCount,
                $portfolio->revenue->format(),
            ]);

            fclose($handle);
        }, 'portefeuille-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * La période refacturée. Sans bornes données, tout l'historique.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $from = $request->string('du')->toString();
        $to = $request->string('au')->toString();

        return [
            $from === '' ? null : CarbonImmutable::parse($from)->startOfDay(),
            // Fin de journée : un relevé « au 31 » comprend le 31 entier.
            $to === '' ? null : CarbonImmutable::parse($to)->endOfDay(),
        ];
    }

    private function agency(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
