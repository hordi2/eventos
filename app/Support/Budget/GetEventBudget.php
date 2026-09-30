<?php

declare(strict_types=1);

namespace App\Support\Budget;

use App\Domain\Event\Models\BudgetLine;
use App\Domain\Event\Models\BudgetLineKind;
use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Budget d'un événement (D7) : prévu contre réalisé, ce que rapporte la
 * billetterie, le résultat et le seuil de rentabilité.
 *
 * Traverse Event (les lignes de budget) et Ticketing (les commandes
 * payées) : ne peut vivre dans ni l'un ni l'autre (section 3 du
 * CLAUDE.md), même raisonnement que GetEventDashboardStats.
 *
 * Tous les montants sont des entiers en plus petite unité monétaire, dans
 * la devise de l'événement (règle 4.2). Une ligne saisie dans une autre
 * devise ne peut pas exister : le formulaire impose celle de l'événement.
 */
final class GetEventBudget
{
    public function handle(Event $event): EventBudgetData
    {
        $currency = $event->currency;
        $lines = BudgetLine::query()
            ->where('event_id', $event->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $expenses = $lines->where('kind', BudgetLineKind::Expense);
        $incomes = $lines->where('kind', BudgetLineKind::Income);

        $plannedExpenses = $this->sum($expenses->all(), planned: true);
        $actualExpenses = $this->sum($expenses->all(), planned: false);
        $plannedIncomes = $this->sum($incomes->all(), planned: true);
        $actualIncomes = $this->sum($incomes->all(), planned: false);
        $ticketing = $this->ticketingRevenue($event);

        // Le résultat se lit sur ce qui est acquis : les recettes déjà
        // encaissées face aux dépenses déjà engagées.
        $result = $ticketing + $actualIncomes - $actualExpenses;
        // Ce qui manque encore à couvrir, en tenant compte de tout ce qui
        // est prévu — c'est lui, le seuil de rentabilité.
        $toCover = max(0, max($plannedExpenses, $actualExpenses) - $ticketing - max($plannedIncomes, $actualIncomes));

        return new EventBudgetData(
            currency: $currency,
            plannedExpenses: Money::fromMinorUnits($plannedExpenses, $currency),
            actualExpenses: Money::fromMinorUnits($actualExpenses, $currency),
            plannedIncomes: Money::fromMinorUnits($plannedIncomes, $currency),
            actualIncomes: Money::fromMinorUnits($actualIncomes, $currency),
            ticketingRevenue: Money::fromMinorUnits($ticketing, $currency),
            result: Money::fromMinorUnits($result, $currency),
            toCover: Money::fromMinorUnits($toCover, $currency),
            ticketsToSell: $this->ticketsToSell($event, $toCover),
            overrunCount: $expenses->filter(fn (BudgetLine $line): bool => $line->isOverrun())->count(),
            lines: $lines->all(),
        );
    }

    /**
     * Somme d'un ensemble de lignes, en unité mineure. Le réalisé d'une
     * ligne jamais engagée vaut zéro, pas son prévu : c'est justement ce
     * que la comparaison prévu/réalisé doit montrer.
     *
     * @param  list<BudgetLine>  $lines
     */
    private function sum(array $lines, bool $planned): int
    {
        $total = 0;

        foreach ($lines as $line) {
            $total += $planned ? $line->planned->amountMinor() : ($line->actual?->amountMinor() ?? 0);
        }

        return $total;
    }

    /**
     * Ce que la billetterie a réellement encaissé : les commandes payées et
     * non remboursées, dons promis compris — ce sont des commandes comme
     * les autres.
     */
    private function ticketingRevenue(Event $event): int
    {
        return (int) DB::table('orders')
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->where('status', OrderStatus::Paid->value)
            ->whereNull('refunded_at')
            ->whereNull('deleted_at')
            ->where('total_currency', $event->currency)
            ->sum('total_amount_minor');
    }

    /**
     * Combien de billets il reste à vendre pour couvrir le manque. Le prix
     * retenu est celui des billets déjà vendus (leur moyenne) ; sans vente,
     * le tarif le moins cher encore proposé. Sans billetterie du tout,
     * aucun nombre n'a de sens : null.
     */
    private function ticketsToSell(Event $event, int $toCover): ?int
    {
        if ($toCover === 0) {
            return 0;
        }

        $price = $this->averageTicketPrice($event) ?? $this->cheapestTierPrice($event);

        return $price === null || $price <= 0 ? null : (int) ceil($toCover / $price);
    }

    private function averageTicketPrice(Event $event): ?int
    {
        $sold = (int) DB::table('tickets')
            ->join('order_items', 'order_items.id', '=', 'tickets.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.organization_id', $event->organization_id)
            ->where('orders.event_id', $event->id)
            ->where('orders.status', OrderStatus::Paid->value)
            ->whereNull('orders.refunded_at')
            ->whereNull('orders.deleted_at')
            ->whereNull('tickets.deleted_at')
            ->count();

        if ($sold === 0) {
            return null;
        }

        return (int) round($this->ticketingRevenue($event) / $sold);
    }

    private function cheapestTierPrice(Event $event): ?int
    {
        $amount = DB::table('ticket_price_tiers')
            ->join('ticket_types', 'ticket_types.id', '=', 'ticket_price_tiers.ticket_type_id')
            ->where('ticket_types.organization_id', $event->organization_id)
            ->where('ticket_types.event_id', $event->id)
            ->whereNull('ticket_types.deleted_at')
            ->whereNull('ticket_price_tiers.deleted_at')
            ->where('ticket_price_tiers.currency', $event->currency)
            ->where('ticket_price_tiers.amount_minor', '>', 0)
            ->min('ticket_price_tiers.amount_minor');

        return $amount === null ? null : (int) $amount;
    }
}
