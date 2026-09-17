<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\FormatFieldAnswerForExport;
use App\Domain\Form\Actions\SyncSubEventRegistrations;
use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Support\DonationAnswer;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Écran « Réponses aux questions » d'un événement : ce que les invités ont
 * répondu, question par question (répartition des choix, total des dons,
 * derniers textes sinon), puis la liste des invités avec leurs réponses.
 * Traverse Event, Form et la capacité (quotas d'option), d'où Support.
 *
 * Les questions de la version publiée servent de référence, et les réponses
 * des versions précédentes sont rapprochées par clé : une clé ne change
 * jamais, alors qu'un libellé peut être réécrit (§4.7 du CLAUDE.md).
 *
 * Tout est agrégé en mémoire : suffisant à l'échelle d'un événement, à
 * revoir en agrégats SQL si un événement dépassait quelques milliers
 * d'inscriptions.
 */
final class PresentEventAnswers
{
    public function __construct(
        private readonly FormatFieldAnswerForExport $formatAnswer,
        private readonly GetRemainingCapacity $getRemainingCapacity,
        private readonly CollectRegistrationAnswers $collectRegistrationAnswers,
    ) {}

    /**
     * @return array{
     *     questions: list<array{key: string, label: string, type: string, answered: int, breakdown: list<array{label: string, count: int, remaining: ?int}>, totals: list<string>, samples: list<string>, link: ?string}>,
     *     guests: list<array{id: int, name: string, email: string, status: string, statusLabel: string, registeredAt: string, answers: array<string, string>}>,
     *     totalGuests: int, shownGuests: int
     * }
     */
    public function handle(Event $event, int $guestLimit = 50): array
    {
        $version = $this->referenceVersion($event);

        if ($version === null) {
            return ['questions' => [], 'guests' => [], 'totalGuests' => 0, 'shownGuests' => 0];
        }

        $fields = $version->fields
            ->reject(fn (FormField $field): bool => $field->type === FieldType::InformationalText)
            ->values();

        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_registration_id')
            ->latest('id')
            ->get();

        /** @var Collection<int, RegistrationAnswer> $answers */
        $answers = RegistrationAnswer::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->with(['formField.options', 'attendee'])
            ->get();

        $byKey = $answers->groupBy(fn (RegistrationAnswer $answer): string => (string) $answer->formField?->key);
        $byRegistration = $answers->groupBy(fn (RegistrationAnswer $answer): int => $answer->registration_id);

        return [
            'questions' => $fields->map(fn (FormField $field): array => $this->question($event, $field, $byKey->get($field->key, collect())))->all(),
            'guests' => $registrations->take($guestLimit)
                ->map(fn (Registration $registration): array => $this->guest($event, $registration, $byRegistration->get($registration->id, collect())))
                ->values()
                ->all(),
            'totalGuests' => $registrations->count(),
            'shownGuests' => min($guestLimit, $registrations->count()),
        ];
    }

    private function referenceVersion(Event $event): ?FormVersion
    {
        $form = Form::query()->where('event_id', $event->id)->first();

        if ($form === null) {
            return null;
        }

        $version = $form->currentVersion ?? $form->latestVersion();
        $version?->loadMissing('fields.options');

        return $version;
    }

    /**
     * @param  Collection<int, RegistrationAnswer>  $answers  réponses à cette question, toutes versions confondues
     * @return array{key: string, label: string, type: string, answered: int, breakdown: list<array{label: string, count: int, remaining: ?int}>, totals: list<string>, samples: list<string>, link: ?string}
     */
    private function question(Event $event, FormField $field, Collection $answers): array
    {
        return [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->label(),
            'answered' => $answers->count(),
            'breakdown' => $this->breakdown($field, $answers),
            'totals' => $field->type === FieldType::Donation ? $this->donationTotals($answers) : [],
            'samples' => $this->samples($field, $answers),
            'link' => $field->type === FieldType::FileUpload ? route('events.files.index', $event->id) : null,
        ];
    }

    /**
     * Répartition d'une question à choix : options du bloc, sessions
     * proposées, ou oui/non. Vide pour les autres types.
     *
     * @param  Collection<int, RegistrationAnswer>  $answers
     * @return list<array{label: string, count: int, remaining: ?int}>
     */
    private function breakdown(FormField $field, Collection $answers): array
    {
        $chosen = $answers->flatMap(fn (RegistrationAnswer $answer): array => array_map(strval(...), is_array($answer->value) ? $answer->value : [$answer->value]));
        $count = fn (string $value): int => $chosen->filter(fn (string $item): bool => $item === $value)->count();

        if ($field->type->supportsOptions()) {
            return $field->options
                ->map(fn (FieldOption $option): array => [
                    'label' => $option->label,
                    'count' => $count($option->value),
                    'remaining' => $this->getRemainingCapacity->handle('form_field_option', (string) $option->id, $option->quota),
                ])
                ->values()
                ->all();
        }

        if ($field->type === FieldType::SubEvents) {
            return array_map(fn (array $subEvent): array => [
                'label' => (string) ($subEvent['title'] ?? ''),
                'count' => $count((string) ($subEvent['id'] ?? '')),
                'remaining' => null,
            ], $this->offeredSubEvents($field));
        }

        if ($field->type === FieldType::YesNo) {
            return [
                ['label' => 'Oui', 'count' => $answers->filter(fn (RegistrationAnswer $answer): bool => (bool) $answer->value)->count(), 'remaining' => null],
                ['label' => 'Non', 'count' => $answers->filter(fn (RegistrationAnswer $answer): bool => ! $answer->value)->count(), 'remaining' => null],
            ];
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offeredSubEvents(FormField $field): array
    {
        $offered = [];

        foreach (SyncSubEventRegistrations::offeredIds($field->config ?? []) as $id) {
            foreach ((array) (($field->config ?? [])['sub_events'] ?? []) as $subEvent) {
                if (is_array($subEvent) && (int) ($subEvent['id'] ?? 0) === $id) {
                    $offered[] = $subEvent;
                }
            }
        }

        return $offered;
    }

    /**
     * Dons promis : le nombre de donateurs et le total par devise.
     *
     * @param  Collection<int, RegistrationAnswer>  $answers
     * @return list<string>
     */
    private function donationTotals(Collection $answers): array
    {
        $totals = [];

        foreach ($answers as $answer) {
            $amount = DonationAnswer::stored($answer->value);

            if ($amount === null) {
                continue;
            }

            $totals[$amount->currency()] = isset($totals[$amount->currency()])
                ? $totals[$amount->currency()]->add($amount)
                : $amount;
        }

        $donors = count($answers->filter(fn (RegistrationAnswer $answer): bool => DonationAnswer::stored($answer->value) !== null));

        return [
            $donors > 1 ? "{$donors} donateurs" : "{$donors} donateur",
            ...array_map(fn (Money $total): string => $total->format(), array_values($totals)),
        ];
    }

    /**
     * Quelques réponses récentes, pour les questions sans répartition
     * possible (texte, nombre, adresse, fichier…).
     *
     * @param  Collection<int, RegistrationAnswer>  $answers
     * @return list<string>
     */
    private function samples(FormField $field, Collection $answers): array
    {
        if ($field->type->supportsOptions() || in_array($field->type, [FieldType::SubEvents, FieldType::YesNo, FieldType::Donation], true)) {
            return [];
        }

        return $answers
            ->take(5)
            ->map(fn (RegistrationAnswer $answer): string => $this->formatAnswer->handle($answer->formField, $answer->value))
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RegistrationAnswer>  $answers  réponses de cette inscription, titulaire et accompagnants
     * @return array{id: int, name: string, email: string, status: string, statusLabel: string, registeredAt: string, answers: array<string, string>}
     */
    private function guest(Event $event, Registration $registration, Collection $answers): array
    {
        $name = trim("{$registration->first_name} {$registration->last_name}");

        return [
            'id' => $registration->id,
            'name' => $name !== '' ? $name : $registration->email,
            'email' => $registration->email,
            'status' => $registration->status->value,
            'statusLabel' => $registration->status->label(),
            'registeredAt' => CarbonImmutable::parse($registration->registered_at ?? $registration->created_at)
                ->setTimezone($event->timezone)
                ->translatedFormat('j M Y \à H\hi'),
            'answers' => $this->collectRegistrationAnswers->handle($answers),
        ];
    }
}
