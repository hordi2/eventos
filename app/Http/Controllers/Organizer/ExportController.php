<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Analytics\Actions\RequestExport;
use App\Domain\Analytics\Models\Export;
use App\Domain\Analytics\Models\ExportStatus;
use App\Domain\Analytics\Models\ExportType;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Export\StoreExportRequest;
use App\Support\Export\BuildExportRows;
use App\Support\Segments\EventSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ExportController extends Controller
{
    public function index(int $event, BuildExportRows $buildExportRows): InertiaResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('exportData', $event->organization);

        $exports = Export::query()->where('event_id', $event->id)->latest()->limit(10)->get();

        return Inertia::render('Exports/Show', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'types' => array_map(fn (ExportType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'columns' => $buildExportRows->columns($type),
            ], ExportType::cases()),
            'segments' => array_map(fn (EventSegment $segment): array => [
                'value' => $segment->value,
                'label' => $segment->label(),
            ], EventSegment::cases()),
            'exports' => $exports->map($this->serialize(...)),
        ]);
    }

    public function store(int $event, StoreExportRequest $request, RequestExport $action): JsonResponse
    {
        $event = $this->findEvent($event);

        $type = ExportType::from($request->string('type')->toString());
        $segmentValue = $request->string('segment')->toString();
        /** @var list<string> $columns */
        $columns = array_values(array_map(strval(...), $request->array('columns')));

        $export = $action->handle(
            organization: $event->organization,
            eventId: $event->id,
            type: $type,
            columns: $columns,
            segment: $segmentValue !== '' ? EventSegment::from($segmentValue) : null,
            user: $request->user(),
        );

        return response()->json($this->serialize($export), 201);
    }

    public function status(int $event, int $export): JsonResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('exportData', $event->organization);

        $exportModel = Export::query()->where('event_id', $event->id)->findOrFail($export);

        return response()->json($this->serialize($exportModel));
    }

    public function download(int $event, int $export): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('exportData', $event->organization);

        $exportModel = Export::query()->where('event_id', $event->id)->findOrFail($export);

        $expired = $exportModel->expires_at === null || $exportModel->expires_at->isPast();
        abort_if($exportModel->status !== ExportStatus::Completed || $exportModel->file_path === null || $expired, 404);

        return response(Storage::disk('local')->get($exportModel->file_path), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"export-{$exportModel->type->value}-{$event->id}.csv\"",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Export $export): array
    {
        return [
            'id' => $export->id,
            'type' => $export->type->value,
            'status' => $export->status->value,
            'row_count' => $export->row_count,
            'created_at' => $export->created_at->toIso8601String(),
            'expired' => $export->expires_at !== null && $export->expires_at->isPast(),
        ];
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
