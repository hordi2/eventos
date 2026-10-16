<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRegistrationsRequest;
use App\Http\Resources\Api\V1\RegistrationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Lecture seule (M9.2), inscriptions principales d'un événement — jamais
 * les sessions d'un sous-événement, qui ne sont pas des inscriptions
 * distinctes pour qui consomme cette API (même filtre que
 * GetEventDashboardStats).
 */
final class EventRegistrationController extends Controller
{
    public function index(ListRegistrationsRequest $request, int $event): AnonymousResourceCollection
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('viewGuests', $eventModel->organization);

        $registrations = Registration::query()
            ->where('event_id', $eventModel->id)
            ->whereNull('parent_registration_id')
            ->when($request->filled('status'), fn ($query) => $query->where('status', RegistrationStatus::from($request->string('status')->toString())))
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 25));

        return RegistrationResource::collection($registrations);
    }

    public function show(int $event, int $registration): RegistrationResource
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('viewGuests', $eventModel->organization);

        return RegistrationResource::make(
            Registration::query()->where('event_id', $eventModel->id)->findOrFail($registration),
        );
    }
}
