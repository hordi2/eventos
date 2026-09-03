<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\Models\VatMode;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * event_id est un simple entier, jamais un modèle Event : Domain/Ticketing
 * ne dépend d'aucun modèle de Domain/Event (section 3 du CLAUDE.md, test
 * d'architecture) — même règle que Registration ou MessageAutomation.
 *
 * @phpstan-type PriceTierInput array{name: string, amount: Money, quantity?: int|null, starts_at?: \DateTimeInterface|null, ends_at?: \DateTimeInterface|null, position?: int}
 */
final class CreateTicketType
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, int $eventId, User $creator, array $data): TicketType
    {
        Gate::forUser($creator)->authorize('create', [TicketType::class, $organization]);

        $isFree = (bool) ($data['is_free'] ?? false);
        $minPerOrder = (int) ($data['min_per_order'] ?? 1);
        $maxPerOrder = isset($data['max_per_order']) ? (int) $data['max_per_order'] : null;
        $vatMode = $data['vat_mode'] ?? VatMode::None;
        $vatRateBp = (int) ($data['vat_rate_bp'] ?? 0);
        $tiers = $data['tiers'] ?? [];

        $this->assertExplicitFeesChoice($data);
        $this->assertValidOrderBounds($minPerOrder, $maxPerOrder);
        $this->assertValidVat($vatMode, $vatRateBp);
        $this->assertValidTiers($isFree, $tiers);

        return DB::transaction(function () use ($organization, $eventId, $creator, $data, $isFree, $minPerOrder, $maxPerOrder, $vatMode, $vatRateBp, $tiers): TicketType {
            $ticketType = TicketType::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $eventId,
                'created_by' => $creator->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_free' => $isFree,
                'currency' => $data['currency'],
                'min_per_order' => $minPerOrder,
                'max_per_order' => $maxPerOrder,
                'total_quantity' => $data['total_quantity'] ?? null,
                'vat_mode' => $vatMode,
                'vat_rate_bp' => $vatRateBp,
                'fees_absorbed' => (bool) $data['fees_absorbed'],
                'position' => $data['position'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($tiers as $index => $tier) {
                PriceTier::query()->create([
                    'organization_id' => $organization->id,
                    'ticket_type_id' => $ticketType->id,
                    'name' => $tier['name'],
                    'amount' => $tier['amount'],
                    'quantity' => $tier['quantity'] ?? null,
                    'starts_at' => $tier['starts_at'] ?? null,
                    'ends_at' => $tier['ends_at'] ?? null,
                    'position' => $tier['position'] ?? $index,
                ]);
            }

            return $ticketType;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertExplicitFeesChoice(array $data): void
    {
        if (! array_key_exists('fees_absorbed', $data)) {
            throw new InvalidArgumentException(
                'Le choix "frais absorbés ou répercutés" doit être fait explicitement (M5.1).'
            );
        }
    }

    private function assertValidOrderBounds(int $minPerOrder, ?int $maxPerOrder): void
    {
        if ($minPerOrder < 1) {
            throw new InvalidArgumentException('La quantité minimale par commande doit être d\'au moins 1.');
        }

        if ($maxPerOrder !== null && $maxPerOrder < $minPerOrder) {
            throw new InvalidArgumentException('La quantité maximale par commande ne peut pas être inférieure au minimum.');
        }
    }

    private function assertValidVat(VatMode $vatMode, int $vatRateBp): void
    {
        if ($vatRateBp < 0) {
            throw new InvalidArgumentException('Le taux de TVA ne peut pas être négatif.');
        }

        if ($vatMode === VatMode::None && $vatRateBp !== 0) {
            throw new InvalidArgumentException('Un taux de TVA ne peut être renseigné sans régime de TVA (incluse/en sus).');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tiers
     */
    private function assertValidTiers(bool $isFree, array $tiers): void
    {
        if ($isFree && $tiers !== []) {
            throw new InvalidArgumentException('Un billet gratuit ne peut pas avoir de palier de tarification.');
        }

        if (! $isFree && $tiers === []) {
            throw new InvalidArgumentException('Un billet payant doit avoir au moins un palier de tarification.');
        }
    }
}
