<?php

declare(strict_types=1);

namespace App\Support\Agency;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
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

    /**
     * $from et $to bornent la période retenue — celle que l'agence
     * refacture. Sans bornes, tout l'historique du portefeuille.
     */
    public function handle(
        Organization $agency,
        string $currency = 'EUR',
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): AgencyPortfolioData {
        $clients = Organization::query()
            ->where('managed_by_organization_id', $agency->id)
            ->orderBy('name')
            ->get();

        $previous = $this->currentOrganization->id();
        $rows = [];

        try {
            foreach ($clients as $client) {
                $this->currentOrganization->set($client);
                $rows[] = $this->client($client, $currency, $from, $to);
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

    private function client(Organization $client, string $currency, ?CarbonImmutable $from, ?CarbonImmutable $to): ClientAccountData
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
            eventCount: $this->within(
                Event::query()->where('organization_id', $client->id),
                'start_at',
                $from,
                $to,
            )->count(),
            nextEventTitle: $next?->title,
            nextEventDate: $next === null
                ? null
                : $next->start_at->setTimezone($next->timezone)->translatedFormat('j F Y'),
            registrationCount: (int) $this->within(
                DB::table('registrations')
                    ->where('organization_id', $client->id)
                    ->where('status', RegistrationStatus::Confirmed->value)
                    ->whereNull('deleted_at'),
                'created_at',
                $from,
                $to,
            )->count(),
            revenue: Money::fromMinorUnits($this->revenue($client, $currency, $from, $to), $currency),
            managedSince: $client->managed_since?->translatedFormat('j F Y'),
        );
    }

    /**
     * Ce que la billetterie du client a encaissé : les commandes payées et
     * non remboursées, dans la devise du portefeuille — une commande dans
     * une autre devise ne s'additionne pas (règle 4.2).
     */
    private function revenue(Organization $client, string $currency, ?CarbonImmutable $from, ?CarbonImmutable $to): int
    {
        return (int) $this->within(
            DB::table('orders')
                ->where('organization_id', $client->id)
                ->where('status', OrderStatus::Paid->value)
                ->whereNull('refunded_at')
                ->whereNull('deleted_at')
                ->where('total_currency', $currency),
            'paid_at',
            $from,
            $to,
        )->sum('total_amount_minor');
    }

    /**
     * Borne une requête sur une période. Les dates sont comparées en UTC,
     * comme elles sont stockées (règle 4.3).
     *
     * @template TQuery of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function within(mixed $query, string $column, ?CarbonImmutable $from, ?CarbonImmutable $to): mixed
    {
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }

        if ($to !== null) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }
}
