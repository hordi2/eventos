<?php

declare(strict_types=1);

namespace App\Support\Networking;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;

/**
 * Annuaire des participants d'un événement (D8).
 *
 * N'y figure que celui qui l'a explicitement demandé, et seulement s'il est
 * confirmé. Rien d'autre que son nom et la ligne qu'il a écrite lui-même
 * n'en sort : ni e-mail, ni téléphone, ni réponses au formulaire. Un
 * participant qui retire son consentement disparaît aussitôt.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class GetAttendeeDirectory
{
    /**
     * @return list<array{name: string, headline: ?string}>
     */
    public function handle(Event $event): array
    {
        if (! $event->has_attendee_directory) {
            return [];
        }

        return Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->whereNotNull('directory_consent_at')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['first_name', 'last_name', 'directory_headline'])
            ->map(fn (Registration $registration): array => [
                'name' => trim("{$registration->first_name} {$registration->last_name}"),
                'headline' => $registration->directory_headline,
            ])
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->values()
            ->all();
    }
}
