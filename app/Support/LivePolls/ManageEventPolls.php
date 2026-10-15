<?php

declare(strict_types=1);

namespace App\Support\LivePolls;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\EventPoll;
use App\Domain\Form\Models\EventPollOption;
use App\Domain\Form\Models\EventPollStatus;
use App\Domain\Form\Models\EventPollVote;
use App\Domain\Form\Models\Registration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sondages en direct (D9) : l'organisateur pose une question à choix
 * unique, l'ouvre, les participants votent, il la ferme. Pas de
 * rafraîchissement temps réel (WebSocket) : la page de résultats se
 * recharge toute seule, comme la salle d'attente de la billetterie
 * (ticketing/waiting.blade.php) — même choix, même raison (§2 du
 * CLAUDE.md, poids des pages invité).
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du
 * CLAUDE.md), comme PlanAttendeeMeeting pour le networking (D8).
 */
final class ManageEventPolls
{
    /**
     * @param  list<string>  $optionLabels
     */
    public function create(Event $event, string $question, array $optionLabels): EventPoll
    {
        $poll = EventPoll::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'question' => $question,
            'status' => EventPollStatus::Draft,
        ]);

        foreach ($optionLabels as $position => $label) {
            EventPollOption::query()->create([
                'organization_id' => $event->organization_id,
                'event_poll_id' => $poll->id,
                'label' => $label,
                'position' => $position,
            ]);
        }

        return $poll;
    }

    public function open(EventPoll $poll): void
    {
        $poll->update([
            'status' => EventPollStatus::Open,
            'opened_at' => $poll->opened_at ?? CarbonImmutable::now(),
        ]);
    }

    public function close(EventPoll $poll): void
    {
        $poll->update(['status' => EventPollStatus::Closed, 'closed_at' => CarbonImmutable::now()]);
    }

    /**
     * Enregistre ou change le vote d'un participant. Faux quand le sondage
     * n'est pas ouvert, ou que l'option n'est pas la sienne.
     */
    public function vote(EventPoll $poll, EventPollOption $option, Registration $registration): bool
    {
        if ($poll->status !== EventPollStatus::Open || $option->event_poll_id !== $poll->id) {
            return false;
        }

        return DB::transaction(function () use ($poll, $option, $registration): bool {
            $existing = EventPollVote::query()
                ->where('event_poll_id', $poll->id)
                ->where('registration_id', $registration->id)
                ->first();

            if ($existing !== null) {
                if ($existing->event_poll_option_id === $option->id) {
                    return true;
                }

                EventPollOption::query()->whereKey($existing->event_poll_option_id)->decrement('vote_count');
                $existing->update(['event_poll_option_id' => $option->id]);
            } else {
                EventPollVote::query()->create([
                    'organization_id' => $poll->organization_id,
                    'event_poll_id' => $poll->id,
                    'event_poll_option_id' => $option->id,
                    'registration_id' => $registration->id,
                ]);
            }

            $option->increment('vote_count');

            return true;
        });
    }

    /**
     * Les sondages visibles d'un participant : jamais ceux en brouillon.
     *
     * @return list<array{id: int, question: string, status: string, totalVotes: int, myOptionId: ?int, options: list<array{id: int, label: string, voteCount: int, percent: int}>}>
     */
    public function forAttendee(Event $event, Registration $viewer): array
    {
        $polls = EventPoll::query()
            ->where('event_id', $event->id)
            ->where('status', '!=', EventPollStatus::Draft)
            ->with('options')
            ->orderByDesc('opened_at')
            ->get();

        $myVotes = EventPollVote::query()
            ->where('registration_id', $viewer->id)
            ->whereIn('event_poll_id', $polls->pluck('id'))
            ->pluck('event_poll_option_id', 'event_poll_id');

        return $polls
            ->map(fn (EventPoll $poll): array => $this->present($poll, $myVotes->get($poll->id)))
            ->all();
    }

    /**
     * Tous les sondages d'un événement, brouillons compris : la vue de
     * l'organisateur.
     *
     * @return list<array{id: int, question: string, status: string, statusLabel: string, totalVotes: int, myOptionId: ?int, options: list<array{id: int, label: string, voteCount: int, percent: int}>}>
     */
    public function forOrganizer(Event $event): array
    {
        return EventPoll::query()
            ->where('event_id', $event->id)
            ->with('options')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EventPoll $poll): array => [...$this->present($poll, null), 'statusLabel' => $poll->status->label()])
            ->all();
    }

    /**
     * @return array{id: int, question: string, status: string, totalVotes: int, myOptionId: ?int, options: list<array{id: int, label: string, voteCount: int, percent: int}>}
     */
    private function present(EventPoll $poll, ?int $myOptionId): array
    {
        $total = $poll->options->sum('vote_count');

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'status' => $poll->status->value,
            'totalVotes' => $total,
            'myOptionId' => $myOptionId,
            'options' => $poll->options
                ->map(fn (EventPollOption $option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'voteCount' => $option->vote_count,
                    'percent' => $total === 0 ? 0 : (int) round($option->vote_count * 100 / $total),
                ])
                ->all(),
        ];
    }
}
