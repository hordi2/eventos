<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\DeletePromoCode;
use App\Domain\Ticketing\Actions\SavePromoCode;
use App\Domain\Ticketing\Models\PromoCode;
use App\Domain\Ticketing\Models\PromoCodeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Ticketing\SavePromoCodeRequest;
use App\Models\User;
use App\Support\Ticketing\PresentEventPromoCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Codes promo d'un événement (lot 2) : réductions sur les billets.
 */
final class PromoCodeController extends Controller
{
    public function index(int $event, PresentEventPromoCodes $presentEventPromoCodes): Response
    {
        $eventModel = $this->event($event);
        Gate::authorize('viewAny', [PromoCode::class, $eventModel->organization]);

        return Inertia::render('PromoCodes/Index', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title, 'currency' => $eventModel->currency ?? 'EUR', 'timezone' => $eventModel->timezone],
            'promoCodes' => $presentEventPromoCodes->handle($eventModel),
            'kinds' => array_map(fn (PromoCodeKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], PromoCodeKind::cases()),
            'ticketsUrl' => route('events.ticket-types.index', $eventModel->id),
        ]);
    }

    public function store(SavePromoCodeRequest $request, int $event, SavePromoCode $savePromoCode): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();
        $this->assertCodeIsFree($eventModel->id, (string) $request->validated('code'), null);

        $savePromoCode->create($eventModel->organization, $eventModel->id, $user, $this->data($request, $eventModel));

        return back()->with('status', 'promo-code-saved');
    }

    public function update(SavePromoCodeRequest $request, int $promoCode, SavePromoCode $savePromoCode): RedirectResponse
    {
        $code = PromoCode::query()->findOrFail($promoCode);
        $eventModel = $this->event($code->event_id);
        /** @var User $user */
        $user = $request->user();
        $this->assertCodeIsFree($eventModel->id, (string) $request->validated('code'), $code->id);

        $savePromoCode->update($code, $user, $this->data($request, $eventModel));

        return back()->with('status', 'promo-code-saved');
    }

    public function destroy(Request $request, int $promoCode, DeletePromoCode $deletePromoCode): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deletePromoCode->handle(PromoCode::query()->findOrFail($promoCode), $user);

        return back()->with('status', 'promo-code-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(SavePromoCodeRequest $request, Event $event): array
    {
        return [
            ...$request->validated(),
            'currency' => $event->currency ?? 'EUR',
            'timezone' => $event->timezone,
        ];
    }

    /**
     * Un même mot ne peut pas désigner deux codes en service : vérifié ici
     * plutôt que par Rule::unique, qui ne verrait pas l'index partiel sur
     * les codes non supprimés.
     */
    private function assertCodeIsFree(int $eventId, string $code, ?int $exceptId): void
    {
        $taken = PromoCode::query()
            ->where('event_id', $eventId)
            ->where('code', PromoCode::normalize($code))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['code' => 'Ce code existe déjà pour cet événement.']);
        }
    }

    private function event(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
