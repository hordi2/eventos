<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Support\Sustainability\CarbonFootprintData;
use App\Support\Sustainability\GetEventCarbonFootprint;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Empreinte carbone d'un événement (D12) : ce que pèsent les déplacements
 * déclarés, les repas servis et les impressions, et le rapport à joindre à
 * un appel d'offres.
 */
final class CarbonFootprintController extends Controller
{
    public function __construct(
        private readonly GetEventCarbonFootprint $getEventCarbonFootprint,
    ) {}

    public function index(int $event): Response
    {
        $eventModel = $this->event($event);
        $footprint = $this->getEventCarbonFootprint->handle($eventModel);

        return Inertia::render('Events/CarbonFootprint', [
            'event' => [
                'id' => $eventModel->id,
                'title' => $eventModel->title,
                'mealsServed' => $eventModel->meals_served,
                'printedPages' => $eventModel->printed_pages,
                'enabled' => (bool) $eventModel->has_carbon_report,
            ],
            'footprint' => $this->present($footprint),
        ]);
    }

    public function update(Request $request, int $event): RedirectResponse
    {
        $eventModel = $this->event($event);

        $validated = $request->validate([
            'meals_served' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'printed_pages' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $eventModel->update($validated);

        return back()->with('success', 'Le bilan est à jour.');
    }

    /**
     * Le rapport RSE, à joindre à un appel d'offres.
     */
    public function report(int $event): HttpResponse
    {
        $eventModel = $this->event($event);
        $footprint = $this->getEventCarbonFootprint->handle($eventModel);

        $pdf = Pdf::loadView('organizer.carbon-report', [
            'event' => $eventModel,
            'footprint' => $footprint,
            'carpoolSaving' => $this->getEventCarbonFootprint->carpoolSaving($footprint),
        ])->setPaper('a4', 'portrait')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="empreinte-'.(Str::slug($eventModel->title) ?: 'evenement').'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CarbonFootprintData $footprint): array
    {
        return [
            'travel' => $footprint->travelKilograms,
            'meals' => $footprint->mealKilograms,
            'print' => $footprint->printKilograms,
            'total' => $footprint->totalKilograms,
            'perAttendee' => $footprint->kilogramsPerAttendee(),
            'attendeeCount' => $footprint->attendeeCount,
            'declaredCount' => $footprint->declaredCount,
            'declarationRate' => $footprint->declarationRate(),
            'byMode' => $footprint->travelByMode,
            'carpoolOffers' => $footprint->carpoolOffers,
            'carpoolSeekers' => $footprint->carpoolSeekers,
            'carpoolSaving' => $this->getEventCarbonFootprint->carpoolSaving($footprint),
        ];
    }

    private function event(int $event): Event
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('updateEvents', $eventModel->organization);

        return $eventModel;
    }
}
