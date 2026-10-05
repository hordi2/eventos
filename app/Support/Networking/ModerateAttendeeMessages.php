<?php

declare(strict_types=1);

namespace App\Support\Networking;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeBlock;
use App\Domain\Form\Models\AttendeeMessage;
use App\Domain\Form\Models\AttendeeMessageReport;
use App\Domain\Form\Models\AttendeeReportStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Actions\RecordAuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Modération de la messagerie entre participants (D8).
 *
 * Trois recours, du plus immédiat au plus lourd. Le participant bloque
 * lui-même qui l'importune, sans attendre personne. Il signale un message à
 * l'organisateur quand cela dépasse le simple désagrément. L'organisateur
 * retire le message, ou suspend l'envoi pour ce participant.
 *
 * L'organisateur ne voit que les messages signalés — jamais les
 * conversations entières : il modère ce qu'on lui soumet, il ne lit pas le
 * courrier de ses invités.
 *
 * Toute décision de modération est journalisée (§7 du CLAUDE.md).
 */
final class ModerateAttendeeMessages
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * Bloque un participant. Rien n'en est dit à l'intéressé : ses messages
     * ne partent simplement plus.
     */
    public function block(Event $event, Registration $blocker, int $blockedId): bool
    {
        if ($blockedId === $blocker->id) {
            return false;
        }

        $blocked = Registration::query()->where('event_id', $event->id)->find($blockedId);

        if ($blocked === null) {
            return false;
        }

        AttendeeBlock::query()->firstOrCreate(
            ['blocker_registration_id' => $blocker->id, 'blocked_registration_id' => $blocked->id],
            ['organization_id' => $event->organization_id, 'event_id' => $event->id],
        );

        return true;
    }

    public function unblock(Registration $blocker, int $blockedId): void
    {
        AttendeeBlock::query()
            ->where('blocker_registration_id', $blocker->id)
            ->where('blocked_registration_id', $blockedId)
            ->delete();
    }

    /**
     * Deux participants peuvent-ils encore s'écrire ? Non dès que l'un des
     * deux a bloqué l'autre, dans un sens comme dans l'autre.
     */
    public function isBlockedBetween(int $first, int $second): bool
    {
        return AttendeeBlock::query()
            ->where(fn ($query) => $query
                ->where(fn ($pair) => $pair->where('blocker_registration_id', $first)->where('blocked_registration_id', $second))
                ->orWhere(fn ($pair) => $pair->where('blocker_registration_id', $second)->where('blocked_registration_id', $first)))
            ->exists();
    }

    /**
     * Les personnes qu'un participant a bloquées.
     *
     * @return list<array{id: int, name: string}>
     */
    public function blockedBy(Registration $blocker): array
    {
        return AttendeeBlock::query()
            ->where('blocker_registration_id', $blocker->id)
            ->with('blocked')
            ->get()
            ->filter(fn (AttendeeBlock $block): bool => $block->blocked !== null)
            ->map(fn (AttendeeBlock $block): array => [
                'id' => $block->blocked_registration_id,
                'name' => trim("{$block->blocked?->first_name} {$block->blocked?->last_name}"),
            ])
            ->values()
            ->all();
    }

    /**
     * Signale un message. On ne signale que ce qu'on a reçu, et une seule
     * fois (règle 4.4).
     */
    public function report(Event $event, Registration $reporter, int $messageId, ?string $reason): bool
    {
        $message = AttendeeMessage::query()
            ->where('event_id', $event->id)
            ->where('to_registration_id', $reporter->id)
            ->find($messageId);

        if ($message === null) {
            return false;
        }

        AttendeeMessageReport::query()->firstOrCreate(
            ['attendee_message_id' => $message->id, 'reporter_registration_id' => $reporter->id],
            [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'reason' => $reason,
                'status' => AttendeeReportStatus::Open,
            ],
        );

        return true;
    }

    /**
     * Retire un message signalé. Son texte reste en base — le signalement
     * perdrait son sens sans lui —, mais plus personne ne le lit.
     */
    public function removeMessage(AttendeeMessageReport $report, User $moderator): void
    {
        $report->loadMissing('message');

        $report->message?->update(['removed_at' => CarbonImmutable::now(), 'removed_by' => $moderator->id]);
        $this->close($report, $moderator, AttendeeReportStatus::Handled);

        $this->recordAuditLog->handle(
            action: 'attendee_message.removed',
            causer: $moderator,
            subject: $report->message,
            metadata: ['event_id' => $report->event_id, 'report_id' => $report->id],
        );
    }

    /**
     * Suspend l'envoi de messages pour l'auteur d'un message signalé. Il
     * garde l'annuaire et ses rendez-vous : on lui retire la parole, pas sa
     * place à l'événement.
     */
    public function suspendSender(AttendeeMessageReport $report, User $moderator): void
    {
        $report->loadMissing('message.sender');
        $sender = $report->message?->sender;

        if ($sender === null) {
            return;
        }

        $sender->update(['messaging_suspended_at' => CarbonImmutable::now()]);
        $this->close($report, $moderator, AttendeeReportStatus::Handled);

        $this->recordAuditLog->handle(
            action: 'attendee_messaging.suspended',
            causer: $moderator,
            subject: $sender,
            metadata: ['event_id' => $report->event_id, 'report_id' => $report->id],
        );
    }

    public function dismiss(AttendeeMessageReport $report, User $moderator): void
    {
        $this->close($report, $moderator, AttendeeReportStatus::Dismissed);

        $this->recordAuditLog->handle(
            action: 'attendee_message_report.dismissed',
            causer: $moderator,
            subject: $report,
            metadata: ['event_id' => $report->event_id],
        );
    }

    private function close(AttendeeMessageReport $report, User $moderator, AttendeeReportStatus $status): void
    {
        $report->update([
            'status' => $status,
            'handled_by' => $moderator->id,
            'handled_at' => CarbonImmutable::now(),
        ]);
    }
}
