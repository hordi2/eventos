<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\CheckIn\Actions\GenerateBadgePdf;
use App\Domain\CheckIn\Actions\SaveBadgeLogo;
use App\Domain\CheckIn\Models\BadgeBatch;
use App\Domain\CheckIn\Models\BadgeBatchStatus;
use App\Domain\CheckIn\Models\BadgeSettings;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\CheckIn\UploadBadgeLogoRequest;
use App\Jobs\GenerateEventBadgesJob;
use App\Support\CheckIn\BuildBadgeContexts;
use App\Support\CheckIn\GetEventGuestList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class BadgeController extends Controller
{
    public function index(int $event, GetEventGuestList $getEventGuestList): InertiaResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        $guests = $getEventGuestList->handle($event);
        $badgeSettings = BadgeSettings::query()->where('event_id', $event->id)->first();
        $batches = BadgeBatch::query()->where('event_id', $event->id)->latest()->limit(5)->get();

        return Inertia::render('Badges/Show', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'guests' => array_map(fn ($guest): array => [
                'guest_type' => $guest->guestType,
                'id' => $guest->id,
                'name' => $guest->name,
            ], $guests),
            'hasLogo' => $badgeSettings?->logo_path !== null,
            'batches' => $batches->map(fn (BadgeBatch $batch): array => [
                'id' => $batch->id,
                'status' => $batch->status->value,
                'guest_count' => $batch->guest_count,
                'created_at' => $batch->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function uploadLogo(int $event, UploadBadgeLogoRequest $request, SaveBadgeLogo $saveBadgeLogo): RedirectResponse
    {
        $event = $this->findEvent($event);

        $saveBadgeLogo->handle($event->organization, $event->id, $request->file('logo'), $request->user());

        return back();
    }

    public function single(
        int $event,
        string $guestType,
        int $guestId,
        Request $request,
        GetEventGuestList $getEventGuestList,
        BuildBadgeContexts $buildBadgeContexts,
        GenerateBadgePdf $generateBadgePdf,
    ): Response {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        $guest = $getEventGuestList->findOne($event, $guestType, $guestId);
        abort_if($guest === null, 404);

        $badgeSettings = BadgeSettings::query()->where('event_id', $event->id)->first();
        $contexts = $buildBadgeContexts->handle($event, $event->organization->name, [$guest], $badgeSettings);

        $pdf = $generateBadgePdf->handle($contexts[0]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"badge-{$guestType}-{$guestId}.pdf\"",
        ]);
    }

    public function startBatch(int $event, Request $request): JsonResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        $batch = BadgeBatch::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'status' => BadgeBatchStatus::Pending,
            'created_by' => $request->user()->id,
        ]);

        GenerateEventBadgesJob::dispatch($batch->id, $event->organization_id, $event->id);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status->value,
            'guest_count' => $batch->guest_count,
            'created_at' => $batch->created_at->toIso8601String(),
        ]);
    }

    public function batchStatus(int $event, int $batch): JsonResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        $badgeBatch = BadgeBatch::query()->where('event_id', $event->id)->findOrFail($batch);

        return response()->json([
            'id' => $badgeBatch->id,
            'status' => $badgeBatch->status->value,
            'guest_count' => $badgeBatch->guest_count,
        ]);
    }

    public function downloadBatch(int $event, int $batch): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        $badgeBatch = BadgeBatch::query()->where('event_id', $event->id)->findOrFail($batch);

        abort_if($badgeBatch->status !== BadgeBatchStatus::Completed || $badgeBatch->file_path === null, 404);

        return response(Storage::disk('local')->get($badgeBatch->file_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"badges-{$event->id}.pdf\"",
        ]);
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
