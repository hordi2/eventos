<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Event\Models\Event;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Traverse Event (Domain/Event) : ne peut pas vivre dans Domain/Messaging
 * (section 3 du CLAUDE.md), même raisonnement que ResolveMergeVariables.
 *
 * DTSTART/DTEND en UTC (suffixe Z) plutôt qu'un TZID nommé : start_at/end_at
 * sont déjà stockés en UTC (règle 4.3 du CLAUDE.md) et un horodatage UTC est
 * interprété sans ambiguïté par tout client (Google Calendar, Outlook,
 * Apple Calendar) — pas besoin d'un bloc VTIMEZONE avec les règles de
 * passage à l'heure d'été/hiver du fuseau de l'événement.
 */
final class GenerateEventIcs
{
    public function handle(Event $event): string
    {
        $event->loadMissing('venue');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Itaza Invitation//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@itaza-invitation.app',
            'DTSTAMP:'.$this->utc(CarbonImmutable::now()),
            'DTSTART:'.$this->utc($event->start_at),
            'DTEND:'.$this->utc($event->end_at),
            'SUMMARY:'.$this->escape($event->title),
            'LOCATION:'.$this->escape($this->location($event)),
        ];

        if ($event->description !== null && $event->description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escape($event->description);
        }

        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:Rappel';
        $lines[] = 'TRIGGER:-PT1H';
        $lines[] = 'END:VALARM';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function location(Event $event): string
    {
        if ($event->is_online) {
            return $event->online_url ?? 'En ligne';
        }

        $venue = $event->venue;

        if ($venue === null) {
            return '';
        }

        return $venue->address !== '' ? $venue->address : $venue->name;
    }

    private function utc(CarbonInterface $date): string
    {
        return $date->clone()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Échappement TEXT de la RFC 5545 (§3.3.11) : antislash, virgule,
     * point-virgule et retour à la ligne doivent être précédés d'un
     * antislash pour rester dans une seule valeur de propriété.
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\r\n", "\n"],
            ['\\\\', '\\,', '\\;', '\\n', '\\n'],
            $value,
        );
    }

    /**
     * Repliement de ligne de la RFC 5545 (§3.1) : toute ligne de plus de 75
     * octets est coupée et poursuivie sur la ligne suivante, indentée d'une
     * espace — sans quoi certains clients tronquent silencieusement les
     * lignes trop longues (description longue, notamment).
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $rest = $line;

        while (strlen($rest) > 75) {
            $chunks[] = substr($rest, 0, 75);
            $rest = ' '.substr($rest, 75);
        }

        $chunks[] = $rest;

        return implode("\r\n", $chunks);
    }
}
