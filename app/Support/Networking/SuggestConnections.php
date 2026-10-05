<?php

declare(strict_types=1);

namespace App\Support\Networking;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeBlock;
use App\Domain\Form\Models\AttendeeConnection;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use Illuminate\Support\Str;

/**
 * Suggestions de mise en relation (D8) : les participants qui partagent au
 * moins un centre d'intérêt avec celui qui regarde.
 *
 * Seuls les inscrits à l'annuaire sont suggérés — sans consentement, on
 * n'existe pas ici —, et ceux qu'on a déjà rencontrés disparaissent de la
 * liste : la suggestion sert à faire se rencontrer, pas à répéter.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class SuggestConnections
{
    /**
     * Cinq centres d'intérêt au plus : au-delà, ils ne veulent plus rien
     * dire et tout le monde ressemble à tout le monde.
     */
    public const MAX_INTERESTS = 5;

    /**
     * Normalise ce que le participant a écrit : minuscules, sans espaces
     * superflus, sans doublons, pour que « Santé » et « santé » se
     * reconnaissent.
     *
     * @return list<string>
     */
    public static function normalize(?string $interests): array
    {
        if ($interests === null || trim($interests) === '') {
            return [];
        }

        $words = array_map(
            fn (string $word): string => Str::lower(Str::limit(trim($word), 30, '')),
            explode(',', $interests),
        );

        return array_slice(array_values(array_unique(array_filter($words))), 0, self::MAX_INTERESTS);
    }

    /**
     * @return list<array{id: int, name: string, headline: ?string, shared: list<string>}>
     */
    public function handle(Event $event, Registration $registration): array
    {
        $mine = $registration->directory_interests ?? [];

        if ($mine === [] || $registration->directory_consent_at === null) {
            return [];
        }

        $met = AttendeeConnection::query()
            ->where('event_id', $event->id)
            ->where(fn ($query) => $query
                ->where('scanner_registration_id', $registration->id)
                ->orWhere('scanned_registration_id', $registration->id))
            ->get()
            ->flatMap(fn (AttendeeConnection $connection): array => [
                $connection->scanner_registration_id,
                $connection->scanned_registration_id,
            ])
            ->unique()
            ->all();

        $blocked = AttendeeBlock::query()
            ->where('event_id', $event->id)
            ->where(fn ($query) => $query
                ->where('blocker_registration_id', $registration->id)
                ->orWhere('blocked_registration_id', $registration->id))
            ->get()
            ->flatMap(fn (AttendeeBlock $block): array => [
                $block->blocker_registration_id,
                $block->blocked_registration_id,
            ])
            ->unique()
            ->all();

        $others = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->whereNotNull('directory_consent_at')
            ->whereKeyNot($registration->id)
            ->whereNotIn('id', [...$met, ...$blocked])
            ->get(['id', 'first_name', 'last_name', 'directory_headline', 'directory_interests']);

        $rows = [];

        foreach ($others as $other) {
            $shared = array_values(array_intersect($mine, $other->directory_interests ?? []));

            if ($shared === []) {
                continue;
            }

            $rows[] = [
                'id' => $other->id,
                'name' => trim("{$other->first_name} {$other->last_name}"),
                'headline' => $other->directory_headline,
                'shared' => $shared,
            ];
        }

        // Le plus de points communs d'abord ; à égalité, l'ordre alphabétique.
        usort($rows, fn (array $a, array $b): int => count($b['shared']) <=> count($a['shared']) ?: strcmp($a['name'], $b['name']));

        return $rows;
    }
}
