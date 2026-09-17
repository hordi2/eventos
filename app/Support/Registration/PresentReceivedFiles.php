<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\RegistrationFile;
use App\Domain\Form\Support\FileUploadAnswer;
use Carbon\CarbonImmutable;

/**
 * Page « Fichiers reçus » d'un événement : les fichiers joints rattachés à
 * une inscription, avec l'invité, la question et l'état de l'analyse.
 * Traverse Event (fuseau) et Form, d'où Support.
 */
final class PresentReceivedFiles
{
    /**
     * @return list<array{
     *     id: int, guestName: string, guestEmail: string, question: string, fileName: string, size: string,
     *     status: string, statusLabel: string, receivedAt: string, downloadUrl: ?string, rescanUrl: ?string
     * }>
     */
    public function handle(Event $event, bool $canDownload, bool $canRescan): array
    {
        return RegistrationFile::query()
            ->where('event_id', $event->id)
            ->whereNotNull('registration_id')
            ->with(['registration', 'formField'])
            ->latest('id')
            ->get()
            ->map(function (RegistrationFile $file) use ($event, $canDownload, $canRescan): array {
                $registration = $file->registration;
                $name = trim("{$registration?->first_name} {$registration?->last_name}");

                return [
                    'id' => $file->id,
                    'guestName' => $name !== '' ? $name : (string) $registration?->email,
                    'guestEmail' => (string) $registration?->email,
                    'question' => (string) $file->formField?->label,
                    'fileName' => $file->original_name,
                    'size' => FileUploadAnswer::formatSize($file->size_bytes),
                    'status' => $file->scan_status->value,
                    'statusLabel' => $file->scan_status->label(),
                    'receivedAt' => CarbonImmutable::parse($file->created_at)->setTimezone($event->timezone)->translatedFormat('j M Y \à H\hi'),
                    'downloadUrl' => $canDownload && $file->scan_status === FileScanStatus::Clean
                        ? route('events.files.download', [$event->id, $file->id])
                        : null,
                    'rescanUrl' => $canRescan && $file->scan_status === FileScanStatus::Failed
                        ? route('events.files.rescan', [$event->id, $file->id])
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
