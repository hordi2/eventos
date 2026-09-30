<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Domain\Form\Support\FileUploadAnswer;
use App\Support\Antivirus\FileScanStatus;
use Illuminate\Support\Facades\Storage;

/**
 * Intervenants d'un événement, avec les sessions où ils parlent : pour
 * l'écran de l'organisateur comme pour la page publique (D6).
 *
 * withPortal n'est vrai que pour l'organisateur : l'adresse e-mail et le
 * lien personnel du portail n'ont rien à faire dans le HTML public.
 */
final class PresentEventSpeakers
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Event $event, bool $withPortal = false): array
    {
        return Speaker::query()
            ->where('event_id', $event->id)
            ->with('sessions')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Speaker $speaker): array => [
                'id' => $speaker->id,
                'name' => $speaker->name,
                'role' => $speaker->role,
                'company' => $speaker->company,
                'bio' => $speaker->bio,
                'photoUrl' => $speaker->photo_path !== null ? Storage::disk('public')->url($speaker->photo_path) : null,
                'websiteUrl' => $speaker->website_url,
                'linkedinUrl' => $speaker->linkedin_url,
                'sessionIds' => $speaker->sessions->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
                'sessions' => $speaker->sessions->pluck('title')->values()->all(),
                ...$withPortal ? $this->portal($speaker, $event) : [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{email: ?string, portalUrl: string, status: string, responseNote: ?string, support: ?array{name: string, size: string, status: string, isClean: bool}}
     */
    private function portal(Speaker $speaker, Event $event): array
    {
        return [
            'email' => $speaker->email,
            'portalUrl' => route('speaker-portal.show', [
                'organization' => $event->organization->slug,
                'token' => $speaker->portal_token,
            ]),
            'status' => $speaker->slotStatus(),
            'responseNote' => $speaker->response_note,
            'support' => $this->support($speaker),
        ];
    }

    /**
     * @return array{name: string, size: string, status: string, isClean: bool}|null
     */
    private function support(Speaker $speaker): ?array
    {
        if ($speaker->support_original_name === null || $speaker->support_scan_status === null) {
            return null;
        }

        return [
            'name' => $speaker->support_original_name,
            'size' => FileUploadAnswer::formatSize($speaker->support_size_bytes ?? 0),
            'status' => $speaker->support_scan_status->label(),
            // Un support n'est jamais servi avant d'être déclaré sain.
            'isClean' => $speaker->support_scan_status === FileScanStatus::Clean,
        ];
    }
}
