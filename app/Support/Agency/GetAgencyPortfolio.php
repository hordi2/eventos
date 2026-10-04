<?php

declare(strict_types=1);

namespace App\Support\Agency;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Le portefeuille d'une agence événementielle (D10) : ses comptes clients,
 * ce que chacun porte, et le total consolidé.
 *
 * Chaque compte est lu dans son propre contexte : la row-level security de
 * PostgreSQL ne laisse voir les événements d'une organisation que lorsque
 * c'est elle qui est posée (règle 4.1). Le contexte de départ est remis en
 * place à la fin, quoi qu'il arrive.
 *
 * Traverse Organization, Event, Form et Ticketing : sa place est dans
 * Support (section 3 du CLAUDE.md).
 */
final class GetAgencyPortfolio
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Organization $agency, string $currency = 'EUR'): AgencyPortfolioData
    {
        $clients = Organization::query()
            ->where('managed_by_organization_id', $agency->id)
            ->orderBy('name')
            ->get();

        $previous = $this->currentOrganization->id();
        $rows = [];

        try {
            foreach ($clients as $client) {
                $this->currentOrganization->set($client);
                $rows[] = $this->client($client, $currency);
            }
        } finally {
            $previous === null ? $this->currentOrganization->clear() : $this->currentOrganization->set($previous);
        }

        $revenue = Money::zero($currency);

        foreach ($rows as $row) {
            $revenue = $revenue->add($row->revenue);
        }

        return new AgencyPortfolioData(
            clients: $rows,
            eventCount: array_sum(array_map(fn (ClientAccountData $row): int => $row->eventCount, $rows)),
            registrationCount: array_sum(array_map(fn (ClientAccountData $row): int => $row->registrationCount, $rows)),
            revenue: $revenue,
        );
    }

    private function client(Organization $client, string $currency): ClientAccountData
    {
        $next = Event::query()
            ->where('organization_id', $client->id)
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->first();

        return new ClientAccountData(
            id: $client->id,
            name: $client->name,
            slug: $client->slug,
            eventCount: Event::query()->where('organization_id', $client->id)->count(),
            nextEventTitle: $next?->title,
            nextEventDate: $next === null
                ? null
                : $next->start_at->setTimezone($next->timezone)->translatedFormat('j F Y'),
            registrationCount: (int) DB::table('registrations')
                ->where('organization_id', $client->id)
                ->where('status', RegistrationStatus::Confirmed->value)
                ->whereNull('deleted_at')
                ->count(),
            revenue: Money::fromMinorUnits($this->revenue($client, $currency), $currency),
            managedSince: $client->managed_since?->translatedFormat('j F Y'),
        );
    }

    /**
     * Ce que la billetterie du client a encaissé : les commandes payées et
     * non remboursées, dans la devise du portefeuille — une commande dans
     * une autre devise ne s'additionne pas (règle 4.2).
     */
    private function revenue(Organization $client, string $currency): int
    {
        return (int) DB::table('orders')
            ->where('organization_id', $client->id)
            ->where('status', OrderStatus::Paid->value)
            ->whereNull('refunded_at')
            ->whereNull('deleted_at')
            ->where('total_currency', $currency)
            ->sum('total_amount_minor');
    }
}
