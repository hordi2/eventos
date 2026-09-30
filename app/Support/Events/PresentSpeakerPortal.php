<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Domain\Form\Support\FileUploadAnswer;
use App\Support\Antivirus\FileScanStatus;

/**
 * Ce que voit un intervenant sur son portail (D6) : son créneau, les
 * informations logistiques de l'organisateur, et l'état de son support. Les
 * heures s'affichent dans le fuseau de l'événement (règle 4.3).
 */
final class PresentSpeakerPortal
{
    /**
     * Formats acceptés pour un support de présentation : les diapositives
     * s'ajoutent aux formats déjà admis pour les fichiers joints d'invité.
     *
     * @var list<string>
     */
    public const SUPPORT_EXTENSIONS = ['pdf', 'ppt', 'pptx', 'odp', 'doc', 'docx', 'odt', 'jpg', 'jpeg', 'png'];

    public const SUPPORT_MAX_SIZE_MB = 50;

    /**
     * @return array{name: string, role: ?string, status: string, responseNote: ?string, event: array{title: string, schedule: string, place: ?string, briefing: ?string}, sessions: list<array{title: string, schedule: string, room: ?string}>, support: ?array{name: string, size: string, status: string, isRejected: bool, isPending: bool}}
     */
    public function handle(Speaker $speaker, Event $event): array
    {
        return [
            'name' => $speaker->name,
            'role' => $speaker->role,
            'status' => $speaker->slotStatus(),
            'responseNote' => $speaker->response_note,
            'event' => [
                'title' => $event->title,
                'schedule' => $this->schedule($event),
                'place' => $event->is_online ? __('En ligne') : $event->venue?->name,
                'briefing' => $event->speaker_briefing,
            ],
            'sessions' => $this->sessions($speaker, $event),
            'support' => $this->support($speaker),
        ];
    }

    /**
     * Dates de l'événement dans son fuseau (règle 4.3), telles que les voit
     * l'intervenant — sur son portail comme dans son e-mail d'invitation.
     */
    public function schedule(Event $event): string
    {
        $start = $event->start_at->setTimezone($event->timezone);
        $end = $event->end_at->setTimezone($event->timezone);

        return $start->translatedFormat('l j F Y \à H\hi').' – '.$end->translatedFormat($start->isSameDay($end) ? 'H\hi' : 'l j F \à H\hi');
    }

    /**
     * @return list<array{title: string, schedule: string, room: ?string}>
     */
    private function sessions(Speaker $speaker, Event $event): array
    {
        return $speaker->sessions()
            ->orderBy('start_at')
            ->get()
            ->map(function (Event $session) use ($event): array {
                $start = $session->start_at->setTimezone($event->timezone);
                $end = $session->end_at->setTimezone($event->timezone);

                return [
                    'title' => $session->title,
                    'schedule' => $start->translatedFormat('l j F \à H\hi').' – '.$end->format('H\hi'),
                    'room' => $session->room,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, size: string, status: string, isRejected: bool, isPending: bool}|null
     */
    private function support(Speaker $speaker): ?array
    {
        if ($speaker->support_original_name === null || $speaker->support_scan_status === null) {
            return null;
        }

        return [
            'name' => $speaker->support_original_name,
            'size' => FileUploadAnswer::formatSize($speaker->support_size_bytes ?? 0),
            'status' => __($speaker->support_scan_status->label()),
            'isRejected' => $speaker->support_scan_status === FileScanStatus::Infected,
            'isPending' => $speaker->support_scan_status === FileScanStatus::Pending,
        ];
    }
}
