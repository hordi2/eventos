<?php

declare(strict_types=1);

namespace App\Support\Assistant;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Les outils qu'Itaza expose à l'assistant IA de l'organisateur (D4, serveur
 * MCP).
 *
 * Tout est en lecture : un assistant branché sur un compte répond à des
 * questions, il ne crée ni ne supprime rien. Ce qu'il lit, l'organisateur le
 * lit déjà dans son tableau de bord — ni plus, ni moins : pas de téléphone,
 * pas de réponse de formulaire, rien d'autre que ce qu'il faut pour répondre.
 *
 * Traverse Event, Form, Ticketing et Organization : sa place est dans
 * Support (section 3 du CLAUDE.md).
 */
final class McpTools
{
    /**
     * La description des outils, telle que l'assistant la reçoit.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'lister_evenements',
                'description' => "Liste les événements de l'organisation : titre, date, statut et nombre d'inscrits confirmés.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'a_venir' => ['type' => 'boolean', 'description' => 'Ne garder que les événements à venir.'],
                    ],
                ],
            ],
            [
                'name' => 'detail_evenement',
                'description' => "Détail d'un événement : dates, lieu, capacité, inscrits par statut et recettes encaissées.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'evenement_id' => ['type' => 'integer', 'description' => "Identifiant de l'événement."],
                    ],
                    'required' => ['evenement_id'],
                ],
            ],
            [
                'name' => 'rechercher_inscrit',
                'description' => "Retrouve un inscrit d'un événement par son nom ou son adresse e-mail.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'evenement_id' => ['type' => 'integer'],
                        'recherche' => ['type' => 'string', 'description' => 'Nom ou adresse, même partiels.'],
                    ],
                    'required' => ['evenement_id', 'recherche'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $name, array $arguments, Organization $organization): string
    {
        return match ($name) {
            'lister_evenements' => $this->events((bool) ($arguments['a_venir'] ?? false)),
            'detail_evenement' => $this->event((int) ($arguments['evenement_id'] ?? 0), $organization),
            'rechercher_inscrit' => $this->search((int) ($arguments['evenement_id'] ?? 0), (string) ($arguments['recherche'] ?? '')),
            default => "Outil inconnu : {$name}.",
        };
    }

    private function events(bool $upcomingOnly): string
    {
        $events = Event::query()
            ->when($upcomingOnly, fn ($query) => $query->where('start_at', '>=', now()))
            ->orderBy('start_at')
            ->limit(50)
            ->get();

        if ($events->isEmpty()) {
            return 'Aucun événement.';
        }

        return $events
            ->map(function (Event $event): string {
                $confirmed = Registration::query()
                    ->where('event_id', $event->id)
                    ->where('status', RegistrationStatus::Confirmed)
                    ->count();

                return sprintf(
                    '#%d · %s · %s · %s · %d inscrits',
                    $event->id,
                    $event->title,
                    $event->start_at->setTimezone($event->timezone)->translatedFormat('j F Y, H\\hi'),
                    $event->status->value,
                    $confirmed,
                );
            })
            ->implode("\n");
    }

    private function event(int $eventId, Organization $organization): string
    {
        $event = Event::query()->with('venue')->find($eventId);

        if ($event === null) {
            return "Aucun événement #{$eventId} dans cette organisation.";
        }

        $byStatus = Registration::query()
            ->where('event_id', $event->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $currency = $event->currency ?? 'EUR';
        $revenue = (int) DB::table('orders')
            ->where('event_id', $event->id)
            ->where('status', OrderStatus::Paid->value)
            ->whereNull('refunded_at')
            ->whereNull('deleted_at')
            ->where('total_currency', $currency)
            ->sum('total_amount_minor');

        $lines = [
            "{$event->title} (#{$event->id})",
            'Quand : '.$event->start_at->setTimezone($event->timezone)->translatedFormat('l j F Y, H\\hi'),
            'Où : '.$this->place($event),
            'Statut : '.$event->status->value,
            'Capacité : '.($event->capacity === null ? 'sans limite' : (string) $event->capacity),
            'Recettes encaissées : '.Money::fromMinorUnits($revenue, $currency)->format(),
            'Organisation : '.$organization->name,
        ];

        foreach ($byStatus as $status => $total) {
            $lines[] = "Inscrits « {$status} » : {$total}";
        }

        return implode("\n", $lines);
    }

    private function place(Event $event): string
    {
        if ($event->is_online) {
            return 'en ligne';
        }

        return $event->venue === null ? 'lieu non renseigné' : $event->venue->name;
    }

    private function search(int $eventId, string $query): string
    {
        if (mb_strlen(trim($query)) < 2) {
            return 'Donnez au moins deux caractères à chercher.';
        }

        $like = '%'.mb_strtolower(trim($query)).'%';

        $found = Registration::query()
            ->where('event_id', $eventId)
            ->where(fn ($builder) => $builder
                ->whereRaw('lower(first_name) like ?', [$like])
                ->orWhereRaw('lower(last_name) like ?', [$like])
                ->orWhereRaw('lower(email) like ?', [$like]))
            ->orderBy('last_name')
            ->limit(25)
            ->get(['first_name', 'last_name', 'email', 'status']);

        if ($found->isEmpty()) {
            return 'Personne ne correspond à cette recherche.';
        }

        return $found
            ->map(fn (Registration $registration): string => sprintf(
                '%s %s · %s · %s',
                $registration->first_name,
                $registration->last_name,
                $registration->email,
                $registration->status->value,
            ))
            ->implode("\n");
    }
}
