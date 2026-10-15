<?php

declare(strict_types=1);

namespace App\Support\SpeakerQa;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\SessionQuestion;
use App\Domain\Form\Models\SessionQuestionStatus;
use App\Domain\Form\Models\SessionQuestionUpvote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Questions au conférencier (D9) : un participant pose une question, les
 * autres votent pour la faire remonter, l'organisateur répond ou la masque.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du
 * CLAUDE.md), comme PlanAttendeeMeeting pour le networking (D8).
 */
final class ManageSessionQuestions
{
    public function ask(Event $event, Registration $registration, string $body): SessionQuestion
    {
        return SessionQuestion::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'body' => $body,
            'status' => SessionQuestionStatus::Pending,
        ]);
    }

    /**
     * Ajoute ou retire le vote du participant. Retourne le nouvel état
     * (true = désormais soutenue par ce participant).
     */
    public function toggleUpvote(SessionQuestion $question, Registration $registration): bool
    {
        return DB::transaction(function () use ($question, $registration): bool {
            $existing = SessionQuestionUpvote::query()
                ->where('session_question_id', $question->id)
                ->where('registration_id', $registration->id)
                ->first();

            if ($existing !== null) {
                $existing->delete();
                $question->decrement('upvote_count');

                return false;
            }

            SessionQuestionUpvote::query()->create([
                'organization_id' => $question->organization_id,
                'session_question_id' => $question->id,
                'registration_id' => $registration->id,
            ]);
            $question->increment('upvote_count');

            return true;
        });
    }

    public function answer(SessionQuestion $question, ?string $answer): void
    {
        $question->update([
            'status' => SessionQuestionStatus::Answered,
            'answer' => $answer,
            'answered_at' => CarbonImmutable::now(),
        ]);
    }

    public function hide(SessionQuestion $question): void
    {
        $question->update(['status' => SessionQuestionStatus::Hidden]);
    }

    public function restore(SessionQuestion $question): void
    {
        $question->update(['status' => SessionQuestionStatus::Pending]);
    }

    /**
     * Les questions visibles d'un participant : jamais celles masquées,
     * les plus soutenues d'abord.
     *
     * @return list<array{id: int, body: string, upvoteCount: int, status: string, answer: ?string, mine: bool, upvoted: bool}>
     */
    public function forAttendee(Event $event, Registration $viewer): array
    {
        $questions = SessionQuestion::query()
            ->where('event_id', $event->id)
            ->where('status', '!=', SessionQuestionStatus::Hidden)
            ->orderByDesc('upvote_count')
            ->orderBy('created_at')
            ->get();

        $upvotedIds = SessionQuestionUpvote::query()
            ->where('registration_id', $viewer->id)
            ->whereIn('session_question_id', $questions->pluck('id'))
            ->pluck('session_question_id')
            ->all();

        return $questions
            ->map(fn (SessionQuestion $question): array => [
                'id' => $question->id,
                'body' => $question->body,
                'upvoteCount' => $question->upvote_count,
                'status' => $question->status->value,
                'answer' => $question->answer,
                'mine' => $question->registration_id === $viewer->id,
                'upvoted' => in_array($question->id, $upvotedIds, true),
            ])
            ->all();
    }

    /**
     * Toutes les questions d'un événement, masquées comprises : la vue de
     * modération de l'organisateur.
     *
     * @return list<array{id: int, body: string, upvoteCount: int, status: string, statusLabel: string, answer: ?string, authorName: string, createdAt: string}>
     */
    public function forOrganizer(Event $event): array
    {
        return SessionQuestion::query()
            ->where('event_id', $event->id)
            ->with('author')
            ->orderByDesc('upvote_count')
            ->orderBy('created_at')
            ->get()
            ->map(fn (SessionQuestion $question): array => [
                'id' => $question->id,
                'body' => $question->body,
                'upvoteCount' => $question->upvote_count,
                'status' => $question->status->value,
                'statusLabel' => $question->status->label(),
                'answer' => $question->answer,
                'authorName' => $question->author === null ? '' : trim("{$question->author->first_name} {$question->author->last_name}"),
                'createdAt' => $question->created_at->toIso8601String(),
            ])
            ->all();
    }
}
