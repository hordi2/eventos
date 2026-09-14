<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Analytics\Models\Export;
use App\Domain\Analytics\Models\ExportStatus;
use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventChecklistMark;
use App\Domain\Event\Models\EventChecklistStep;
use App\Domain\Event\Models\EventChecklistTab;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Page\Models\Page;

/**
 * Liste de contrôle d'un événement. Une étape est complète quand Itaza le
 * constate dans les données (formulaire publié, plan de table créé...) ou
 * quand l'organisateur l'a cochée (EventChecklistMark) — décision produit :
 * automatique d'abord, bouton ensuite.
 *
 * Traverse les modules Event, Form, Page, Messaging, CheckIn et Analytics,
 * d'où sa place dans Support.
 */
final class GetEventChecklist
{
    /**
     * @return array{
     *     completed: int, total: int, percent: int,
     *     tabs: list<array{key: string, label: string, completed: int, total: int, steps: list<array{
     *         key: string, title: string, description: string, optional: bool,
     *         done: bool, automatic: bool, markable: bool, linkKey: string
     *     }>}>
     * }
     */
    public function handle(Event $event): array
    {
        $automatic = $this->automaticallyDone($event);
        $marked = EventChecklistMark::query()
            ->where('event_id', $event->id)
            ->whereNotNull('marked_at')
            ->get()
            ->map(fn (EventChecklistMark $mark): string => $mark->step->value)
            ->all();

        $tabs = [];
        $completed = 0;

        foreach (EventChecklistTab::cases() as $tab) {
            $steps = [];

            foreach (EventChecklistStep::cases() as $step) {
                if ($step->tab() !== $tab) {
                    continue;
                }

                $isAutomatic = in_array($step, $automatic, true);
                $done = $isAutomatic || ($step->canBeMarkedManually() && in_array($step->value, $marked, true));
                $completed += $done ? 1 : 0;

                $steps[] = [
                    'key' => $step->value,
                    'title' => $step->title(),
                    'description' => $step->description(),
                    'optional' => $step->isOptional(),
                    'done' => $done,
                    'automatic' => $isAutomatic,
                    'markable' => $step->canBeMarkedManually() && ! $isAutomatic,
                    'linkKey' => $step->linkKey(),
                ];
            }

            $tabs[] = [
                'key' => $tab->value,
                'label' => $tab->label(),
                'completed' => count(array_filter($steps, fn (array $step): bool => $step['done'])),
                'total' => count($steps),
                'steps' => $steps,
            ];
        }

        $total = count(EventChecklistStep::cases());

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => intdiv($completed * 100, $total),
            'tabs' => $tabs,
        ];
    }

    /**
     * @return list<EventChecklistStep>
     */
    private function automaticallyDone(Event $event): array
    {
        $automationTypes = MessageAutomation::query()
            ->where('event_id', $event->id)
            ->pluck('type')
            ->map(fn (MessageAutomationType $type): string => $type->value)
            ->all();

        $hasAutomation = fn (MessageAutomationType ...$types): bool => array_intersect(
            array_map(fn (MessageAutomationType $type): string => $type->value, $types),
            $automationTypes,
        ) !== [];

        $checks = [
            EventChecklistStep::Website->value => $this->hasCustomizedPage($event),
            EventChecklistStep::Form->value => Form::query()->where('event_id', $event->id)->whereNotNull('current_version_id')->exists(),
            EventChecklistStep::GuestList->value => Registration::query()->where('event_id', $event->id)->exists(),
            EventChecklistStep::ConfirmationEmails->value => $hasAutomation(
                MessageAutomationType::Confirmation,
                MessageAutomationType::ReminderUnanswered,
                MessageAutomationType::ReminderJ7,
                MessageAutomationType::ReminderJ1,
            ),
            // Un événement archivé a forcément été publié ou abandonné en
            // brouillon : seul « publié » compte comme lancé.
            EventChecklistStep::Publish->value => $event->status === EventStatus::Published,
            EventChecklistStep::Invitations->value => $hasAutomation(MessageAutomationType::Invitation),
            EventChecklistStep::Seating->value => SeatingTable::query()->where('event_id', $event->id)->exists(),
            EventChecklistStep::CheckIn->value => CheckIn::query()->where('event_id', $event->id)->exists(),
            EventChecklistStep::Export->value => Export::query()->where('event_id', $event->id)->where('status', ExportStatus::Completed)->exists(),
            EventChecklistStep::ThankYou->value => $hasAutomation(MessageAutomationType::ThankYou),
        ];

        return array_map(
            fn (string $key): EventChecklistStep => EventChecklistStep::from($key),
            array_keys(array_filter($checks)),
        );
    }

    private function hasCustomizedPage(Event $event): bool
    {
        $page = Page::query()->where('event_id', $event->id)->first();

        return $page !== null && (
            $page->banner_path !== null
            || filled($page->meta_description)
            || $page->program_items !== []
            || $page->faq_items !== []
        );
    }
}
