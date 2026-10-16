<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListEventsRequest;
use App\Http\Resources\Api\V1\EventResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lecture seule (M9.2) : les événements de l'organisation de la clé API.
 * Le cloisonnement multi-tenant vient du global scope du modèle (§4.1 du
 * CLAUDE.md) une fois resolve-api-organization posé — pas besoin de
 * filtrer organization_id ici.
 */
final class EventController extends Controller
{
    public function index(ListEventsRequest $request): AnonymousResourceCollection
    {
        $events = Event::query()
            ->with('venue')
            ->when($request->filled('status'), fn ($query) => $query->where('status', EventStatus::from($request->string('status')->toString())))
            ->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 25));

        return EventResource::collection($events);
    }

    public function show(int $event): EventResource
    {
        return EventResource::make(Event::query()->findOrFail($event));
    }
}
