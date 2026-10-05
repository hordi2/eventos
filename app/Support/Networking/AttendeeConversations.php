<?php

declare(strict_types=1);

namespace App\Support\Networking;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeMessage;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use Carbon\CarbonImmutable;

/**
 * Messagerie interne à l'événement (D8).
 *
 * On n'écrit qu'à un participant inscrit à l'annuaire, et seulement si
 * l'organisateur a ouvert la messagerie : sans consentement de l'un et
 * accord de l'autre, personne n'est joignable. Rien n'est envoyé par
 * e-mail : les messages se lisent ici, dans l'événement.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class AttendeeConversations
{
    public function __construct(
        private readonly ModerateAttendeeMessages $moderateAttendeeMessages,
    ) {}

    /**
     * Envoie un message. Null quand le destinataire ne peut pas être
     * joint — messagerie fermée, absent de l'annuaire, ou soi-même.
     */
    public function send(Event $event, Registration $sender, int $recipientId, string $body): ?AttendeeMessage
    {
        if (! $event->has_attendee_messaging || $sender->directory_consent_at === null) {
            return null;
        }

        // Parole suspendue par l'organisateur : plus rien ne part.
        if ($sender->messaging_suspended_at !== null) {
            return null;
        }

        $recipient = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->whereNotNull('directory_consent_at')
            ->find($recipientId);

        if ($recipient === null || $recipient->id === $sender->id) {
            return null;
        }

        // Un blocage, dans un sens ou dans l'autre, ferme la conversation.
        if ($this->moderateAttendeeMessages->isBlockedBetween($sender->id, $recipient->id)) {
            return null;
        }

        return AttendeeMessage::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'from_registration_id' => $sender->id,
            'to_registration_id' => $recipient->id,
            'body' => $body,
        ]);
    }

    /**
     * Les conversations d'un participant : une par interlocuteur, le dernier
     * mot en tête, avec le fil complet.
     *
     * Lire ses messages les marque comme lus : le fil est affiché, ils ont
     * été vus.
     *
     * @return list<array{id: int, name: string, unread: int, messages: list<array{id: int, body: ?string, mine: bool, sentAt: string}>}>
     */
    public function forAttendee(Event $event, Registration $registration): array
    {
        $messages = AttendeeMessage::query()
            ->where('event_id', $event->id)
            ->where(fn ($query) => $query
                ->where('from_registration_id', $registration->id)
                ->orWhere('to_registration_id', $registration->id))
            ->with(['sender', 'recipient'])
            ->orderBy('id')
            ->get();

        $threads = [];

        foreach ($messages as $message) {
            $mine = $message->from_registration_id === $registration->id;
            $other = $mine ? $message->recipient : $message->sender;

            if ($other === null) {
                continue;
            }

            $threads[$other->id] ??= [
                'id' => $other->id,
                'name' => trim("{$other->first_name} {$other->last_name}"),
                'unread' => 0,
                'messages' => [],
            ];

            if (! $mine && $message->read_at === null) {
                $threads[$other->id]['unread']++;
            }

            $threads[$other->id]['messages'][] = [
                'id' => $message->id,
                // Message retiré par l'organisateur : la place reste, le
                // texte non.
                'body' => $message->removed_at === null ? $message->body : null,
                'mine' => $mine,
                'sentAt' => $message->created_at?->setTimezone($event->timezone)->translatedFormat('j F, H\\hi') ?? '',
            ];
        }

        AttendeeMessage::query()
            ->where('event_id', $event->id)
            ->where('to_registration_id', $registration->id)
            ->whereNull('read_at')
            ->update(['read_at' => CarbonImmutable::now()]);

        return array_values($threads);
    }
}
