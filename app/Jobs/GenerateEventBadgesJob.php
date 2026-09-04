<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\CheckIn\Actions\GenerateBadgeSheetPdf;
use App\Domain\CheckIn\Models\BadgeBatch;
use App\Domain\CheckIn\Models\BadgeBatchStatus;
use App\Domain\CheckIn\Models\BadgeSettings;
use App\Domain\Event\Models\Event;
use App\Support\CheckIn\BuildBadgeContexts;
use App\Support\CheckIn\GetEventGuestList;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Génération en masse des badges (T-064, AC : « 500 badges en queue »).
 * Reçoit des ID, jamais les modèles : CurrentOrganization doit être
 * positionné avant que le moindre modèle ne soit résolu (même piège que
 * ExpireOrderJob).
 */
final class GenerateEventBadgesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $badgeBatchId,
        private readonly int $organizationId,
        private readonly int $eventId,
    ) {}

    public function handle(
        CurrentOrganization $currentOrganization,
        GetEventGuestList $getEventGuestList,
        BuildBadgeContexts $buildBadgeContexts,
        GenerateBadgeSheetPdf $generateBadgeSheetPdf,
    ): void {
        $currentOrganization->set($this->organizationId);

        $batch = BadgeBatch::query()->find($this->badgeBatchId);

        if ($batch === null) {
            return;
        }

        $batch->update(['status' => BadgeBatchStatus::Processing]);

        $event = Event::query()->find($this->eventId);

        if ($event === null) {
            $batch->update(['status' => BadgeBatchStatus::Failed]);

            return;
        }

        $guests = $getEventGuestList->handle($event);
        $badgeSettings = BadgeSettings::query()->where('event_id', $event->id)->first();
        $contexts = $buildBadgeContexts->handle($event, $event->organization->name, $guests, $badgeSettings);

        $pdf = $generateBadgeSheetPdf->handle($contexts);
        $path = 'badge-batches/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf);

        $batch->update([
            'status' => BadgeBatchStatus::Completed,
            'guest_count' => count($contexts),
            'file_path' => $path,
            'completed_at' => now(),
        ]);
    }
}
