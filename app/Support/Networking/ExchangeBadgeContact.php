<?php

declare(strict_types=1);

namespace App\Support\Networking;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeConnection;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use Illuminate\Support\Str;

/**
 * Échange de coordonnées par scan de badge (D8).
 *
 * Celui qui scanne et celui qui est scanné se retrouvent dans leurs
 * rencontres respectives. Chacun ne voit de l'autre que ce que l'autre a
 * accepté de partager : le nom et la ligne de l'annuaire toujours, l'adresse
 * seulement si son propriétaire l'a voulu.
 *
 * Idempotent : scanner dix fois le même badge ne crée qu'une rencontre
 * (règle 4.4).
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class ExchangeBadgeContact
{
    /**
     * Le jeton de badge d'un participant, créé au besoin. Il n'a rien à voir
     * avec le code d'entrée, qui est à usage unique (règle 4.6).
     */
    public function tokenFor(Registration $registration): string
    {
        if ($registration->networking_token === null) {
            $registration->update(['networking_token' => (string) Str::uuid()]);
        }

        return (string) $registration->networking_token;
    }

    /**
     * Enregistre la rencontre et renvoie le participant scanné. Null quand
     * le badge ne correspond à personne de cet événement, ou quand on scanne
     * son propre badge.
     */
    public function handle(Event $event, Registration $scanner, string $badgeToken): ?Registration
    {
        $scanned = Registration::query()
            ->where('event_id', $event->id)
            ->where('networking_token', $badgeToken)
            ->where('status', RegistrationStatus::Confirmed)
            ->first();

        if ($scanned === null || $scanned->id === $scanner->id) {
            return null;
        }

        AttendeeConnection::query()->firstOrCreate(
            [
                'scanner_registration_id' => $scanner->id,
                'scanned_registration_id' => $scanned->id,
            ],
            [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ],
        );

        return $scanned;
    }

    /**
     * Les rencontres d'un participant, dans les deux sens. L'adresse n'y
     * figure que si celui qu'on a rencontré a accepté de la partager.
     *
     * @return list<array{name: string, headline: ?string, email: ?string, scannedByMe: bool}>
     */
    public function connectionsOf(Registration $registration): array
    {
        $connections = AttendeeConnection::query()
            ->where(fn ($query) => $query
                ->where('scanner_registration_id', $registration->id)
                ->orWhere('scanned_registration_id', $registration->id))
            ->with(['scanner', 'scanned'])
            ->orderByDesc('id')
            ->get();

        $rows = [];

        foreach ($connections as $connection) {
            $scannedByMe = $connection->scanner_registration_id === $registration->id;
            $other = $scannedByMe ? $connection->scanned : $connection->scanner;

            if ($other === null) {
                continue;
            }

            $rows[] = [
                'name' => trim("{$other->first_name} {$other->last_name}"),
                'headline' => $other->directory_headline,
                'email' => $other->shares_contact ? $other->email : null,
                'scannedByMe' => $scannedByMe,
            ];
        }

        return $rows;
    }
}
