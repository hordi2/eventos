<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\CreatePriceTier;
use App\Domain\Ticketing\Actions\CreateTicketType;
use App\Domain\Ticketing\Actions\DeletePriceTier;
use App\Domain\Ticketing\Actions\DeleteTicketType;
use App\Domain\Ticketing\Actions\UpdatePriceTier;
use App\Domain\Ticketing\Actions\UpdateTicketType;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\Models\VatMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\PriceTier\SavePriceTierRequest;
use App\Http\Requests\Organizer\TicketType\SaveTicketTypeRequest;
use App\Http\Requests\Organizer\TicketType\UpdateTicketTypeRequest;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class TicketTypeController extends Controller
{
    public function index(int $event, GetRemainingCapacity $getRemainingCapacity): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewAny', [TicketType::class, $event->organization]);

        $ticketTypes = TicketType::query()
            ->where('event_id', $event->id)
            ->with('priceTiers')
            ->orderBy('position')
            ->get()
            ->map(fn (TicketType $ticketType): array => $this->serializeTicketType($ticketType, $getRemainingCapacity));

        return Inertia::render('TicketTypes/Index', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'currency' => $event->currency ?? 'EUR'],
            'ticketTypes' => $ticketTypes,
            'vatModes' => array_map(fn (VatMode $mode): array => ['value' => $mode->value, 'label' => $this->vatModeLabel($mode)], VatMode::cases()),
        ]);
    }

    public function store(SaveTicketTypeRequest $request, int $event, CreateTicketType $action): RedirectResponse
    {
        $event = $this->findEvent($event);
        $currency = (string) $request->validated('currency');

        /** @var list<array<string, mixed>> $validatedTiers */
        $validatedTiers = $request->validated('tiers') ?? [];

        $tiers = array_map(fn (array $tier): array => [
            'name' => $tier['name'],
            'amount' => Money::fromMinorUnits((int) $tier['amount_minor'], $currency),
            'quantity' => $tier['quantity'] ?? null,
            'starts_at' => $tier['starts_at'] ?? null,
            'ends_at' => $tier['ends_at'] ?? null,
        ], $validatedTiers);

        try {
            $action->handle($event->organization, $event->id, $request->user(), [
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'is_free' => $request->validated('is_free'),
                'currency' => $currency,
                'min_per_order' => $request->validated('min_per_order'),
                'max_per_order' => $request->validated('max_per_order'),
                'total_quantity' => $request->validated('total_quantity'),
                'vat_mode' => VatMode::from($request->validated('vat_mode')),
                'vat_rate_bp' => $request->validated('vat_rate_bp'),
                'fees_absorbed' => $request->validated('fees_absorbed'),
                'tiers' => $tiers,
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return redirect()->route('events.ticket-types.index', $event);
    }

    public function update(UpdateTicketTypeRequest $request, int $ticketType, UpdateTicketType $action): RedirectResponse
    {
        $ticketType = $this->findTicketType($ticketType);

        try {
            $action->handle($ticketType, $request->user(), [
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'min_per_order' => $request->validated('min_per_order'),
                'max_per_order' => $request->validated('max_per_order'),
                'total_quantity' => $request->validated('total_quantity'),
                'vat_mode' => VatMode::from($request->validated('vat_mode')),
                'vat_rate_bp' => $request->validated('vat_rate_bp'),
                'fees_absorbed' => $request->validated('fees_absorbed'),
                'is_active' => $request->validated('is_active'),
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return redirect()->route('events.ticket-types.index', $ticketType->event_id);
    }

    public function destroy(Request $request, int $ticketType, DeleteTicketType $action): RedirectResponse
    {
        $ticketType = $this->findTicketType($ticketType);
        $eventId = $ticketType->event_id;

        $action->handle($ticketType, $request->user());

        return redirect()->route('events.ticket-types.index', $eventId);
    }

    public function storeTier(SavePriceTierRequest $request, int $ticketType, CreatePriceTier $action): RedirectResponse
    {
        $ticketType = $this->findTicketType($ticketType);

        try {
            $action->handle($ticketType, $request->user(), [
                'name' => $request->validated('name'),
                'amount' => Money::fromMinorUnits((int) $request->validated('amount_minor'), $ticketType->currency),
                'quantity' => $request->validated('quantity'),
                'starts_at' => $request->validated('starts_at'),
                'ends_at' => $request->validated('ends_at'),
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['tier' => $e->getMessage()]);
        }

        return redirect()->route('events.ticket-types.index', $ticketType->event_id);
    }

    public function updateTier(SavePriceTierRequest $request, int $priceTier, UpdatePriceTier $action): RedirectResponse
    {
        $tier = $this->findTier($priceTier);

        $action->handle($tier, $request->user(), [
            'name' => $request->validated('name'),
            'amount' => Money::fromMinorUnits((int) $request->validated('amount_minor'), $tier->amount->currency()),
            'quantity' => $request->validated('quantity'),
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
        ]);

        return redirect()->route('events.ticket-types.index', $tier->ticketType->event_id);
    }

    public function destroyTier(Request $request, int $priceTier, DeletePriceTier $action): RedirectResponse
    {
        $tier = $this->findTier($priceTier);
        $eventId = $tier->ticketType->event_id;

        try {
            $action->handle($tier, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['tier' => $e->getMessage()]);
        }

        return redirect()->route('events.ticket-types.index', $eventId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTicketType(TicketType $ticketType, GetRemainingCapacity $getRemainingCapacity): array
    {
        return [
            'id' => $ticketType->id,
            'name' => $ticketType->name,
            'description' => $ticketType->description,
            'is_free' => $ticketType->is_free,
            'currency' => $ticketType->currency,
            'min_per_order' => $ticketType->min_per_order,
            'max_per_order' => $ticketType->max_per_order,
            'total_quantity' => $ticketType->total_quantity,
            'remaining' => $getRemainingCapacity->handle('ticket_type', (string) $ticketType->id, $ticketType->total_quantity),
            'vat_mode' => $ticketType->vat_mode->value,
            'vat_rate_bp' => $ticketType->vat_rate_bp,
            'fees_absorbed' => $ticketType->fees_absorbed,
            'is_active' => $ticketType->is_active,
            'tiers' => $ticketType->priceTiers->map(fn (PriceTier $tier): array => [
                'id' => $tier->id,
                'name' => $tier->name,
                'amount_minor' => $tier->amount->amountMinor(),
                'currency' => $tier->amount->currency(),
                'quantity' => $tier->quantity,
                'remaining' => $getRemainingCapacity->handle('price_tier', (string) $tier->id, $tier->quantity),
                'starts_at' => $tier->starts_at?->toIso8601String(),
                'ends_at' => $tier->ends_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function vatModeLabel(VatMode $mode): string
    {
        return match ($mode) {
            VatMode::None => 'Aucune',
            VatMode::Included => 'Incluse',
            VatMode::Excluded => 'En sus',
        };
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }

    private function findTicketType(int $id): TicketType
    {
        return TicketType::query()->findOrFail($id);
    }

    private function findTier(int $id): PriceTier
    {
        return PriceTier::query()->with('ticketType')->findOrFail($id);
    }
}
