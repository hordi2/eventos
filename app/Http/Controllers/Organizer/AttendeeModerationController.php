<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeMessageReport;
use App\Domain\Form\Models\AttendeeReportStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Networking\ModerateAttendeeMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modération de la messagerie entre participants (D8).
 *
 * L'organisateur ne voit ici que les messages qu'on lui a signalés — jamais
 * les conversations entières. Chacune de ses décisions part au journal
 * d'audit (§7 du CLAUDE.md).
 */
final class AttendeeModerationController extends Controller
{
    public function __construct(
        private readonly ModerateAttendeeMessages $moderateAttendeeMessages,
    ) {}

    public function index(int $event): Response
    {
        $eventModel = $this->event($event);

        return Inertia::render('Events/Moderation', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'reports' => $this->reports($eventModel),
        ]);
    }

    public function update(Request $request, int $event, int $report): RedirectResponse
    {
        $eventModel = $this->event($event);
        /** @var User $user */
        $user = $request->user();

        $reportModel = AttendeeMessageReport::query()->where('event_id', $eventModel->id)->findOrFail($report);
        abort_unless($reportModel->status === AttendeeReportStatus::Open, 422);

        match ($request->string('decision')->toString()) {
            'remove' => $this->moderateAttendeeMessages->removeMessage($reportModel, $user),
            'suspend' => $this->moderateAttendeeMessages->suspendSender($reportModel, $user),
            'dismiss' => $this->moderateAttendeeMessages->dismiss($reportModel, $user),
            default => abort(422),
        };

        return back()->with('success', 'Le signalement est traité.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reports(Event $event): array
    {
        return AttendeeMessageReport::query()
            ->where('event_id', $event->id)
            ->with(['message.sender', 'reporter', 'handler'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (AttendeeMessageReport $report): array => [
                'id' => $report->id,
                'status' => $report->status->value,
                'statusLabel' => $report->status->label(),
                'reason' => $report->reason,
                'reportedAt' => $report->created_at?->setTimezone($event->timezone)->translatedFormat('j F Y, H\\hi'),
                'reporter' => trim("{$report->reporter?->first_name} {$report->reporter?->last_name}"),
                'sender' => trim("{$report->message?->sender?->first_name} {$report->message?->sender?->last_name}"),
                'body' => $report->message?->body,
                'removed' => $report->message?->removed_at !== null,
                'suspended' => $report->message?->sender?->messaging_suspended_at !== null,
                'handledBy' => $report->handler?->name,
            ])
            ->values()
            ->all();
    }

    private function event(int $event): Event
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('moderateMessages', $eventModel->organization);

        return $eventModel;
    }
}
