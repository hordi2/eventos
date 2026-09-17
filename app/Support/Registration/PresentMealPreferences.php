<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\FormatFieldAnswerForExport;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Support\AskScope;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use Illuminate\Support\Collection;

/**
 * Rapport « Préférences alimentaires » : de quoi commander les repas, une
 * ligne par personne attendue plutôt que par inscription — un accompagnant
 * mange aussi. Les inscriptions annulées et les refus sont écartés.
 *
 * Traverse Event, Form et la capacité (quotas d'option), d'où Support.
 */
final class PresentMealPreferences
{
    public function __construct(
        private readonly GetRemainingCapacity $getRemainingCapacity,
        private readonly FormatFieldAnswerForExport $formatAnswer,
    ) {}

    /**
     * @return array{
     *     questions: list<array{key: string, label: string, options: list<array{label: string, count: int, quota: ?int, remaining: ?int}>}>,
     *     people: list<array{name: string, registrationName: string, isCompanion: bool, statusLabel: string, meals: array<string, string>}>,
     *     expected: int
     * }
     */
    public function handle(Event $event): array
    {
        $fields = $this->mealQuestions($event);

        if ($fields->isEmpty()) {
            return ['questions' => [], 'people' => [], 'expected' => 0];
        }

        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_registration_id')
            ->whereIn('status', [RegistrationStatus::Confirmed, RegistrationStatus::Waitlisted])
            ->with('attendees')
            ->orderBy('id')
            ->get();

        $answers = RegistrationAnswer::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->whereIn('form_field_id', $this->fieldIdsByKey($fields))
            ->with('formField')
            ->get();

        $people = $registrations
            ->flatMap(fn (Registration $registration): array => $this->peopleOf($registration, $fields, $answers))
            ->values();

        return [
            'questions' => $fields->map(fn (FormField $field): array => $this->question($field, $people))->all(),
            'people' => $people->all(),
            'expected' => $people->count(),
        ];
    }

    /**
     * Questions « Menu / repas » de la version de référence du formulaire.
     *
     * @return Collection<int, FormField>
     */
    private function mealQuestions(Event $event): Collection
    {
        $form = Form::query()->where('event_id', $event->id)->first();

        if ($form === null) {
            return collect();
        }

        $version = $form->currentVersion ?? $form->latestVersion();

        if ($version === null) {
            return collect();
        }

        $version->loadMissing('fields.options');

        return $version->fields
            ->filter(fn (FormField $field): bool => $field->type === FieldType::MealChoice)
            ->values();
    }

    /**
     * Toutes les questions repas de toutes les versions, pour retrouver les
     * réponses des inscriptions faites avant une révision (§4.7).
     *
     * @param  Collection<int, FormField>  $fields
     * @return list<int>
     */
    private function fieldIdsByKey(Collection $fields): array
    {
        return FormField::query()
            ->where('type', FieldType::MealChoice)
            ->whereIn('key', $fields->pluck('key'))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Une ligne par personne attendue : le titulaire, puis ses accompagnants.
     *
     * @param  Collection<int, FormField>  $fields
     * @param  Collection<int, RegistrationAnswer>  $answers
     * @return list<array{name: string, registrationName: string, isCompanion: bool, statusLabel: string, meals: array<string, string>}>
     */
    private function peopleOf(Registration $registration, Collection $fields, Collection $answers): array
    {
        $own = $answers->where('registration_id', $registration->id);
        $holderName = trim("{$registration->first_name} {$registration->last_name}");
        $holderName = $holderName !== '' ? $holderName : $registration->email;

        return $registration->attendees
            ->sortBy('position')
            ->map(function (Attendee $attendee) use ($registration, $fields, $own, $holderName): array {
                $name = trim("{$attendee->first_name} {$attendee->last_name}");
                $meals = [];

                foreach ($fields as $field) {
                    // Question posée à chaque personne : sa propre réponse ;
                    // sinon, celle du titulaire vaut pour tout le monde.
                    $answer = AskScope::isPerPerson($field)
                        ? $own->first(fn (RegistrationAnswer $item): bool => $item->formField?->key === $field->key && $item->attendee_id === ($attendee->is_primary ? null : $attendee->id))
                        : $own->first(fn (RegistrationAnswer $item): bool => $item->formField?->key === $field->key && $item->attendee_id === null);

                    // Le libellé de l'option, jamais sa valeur technique.
                    $meals[$field->key] = $answer !== null
                        ? $this->formatAnswer->handle($answer->formField ?? $field, $answer->value)
                        : '';
                }

                return [
                    'name' => $name !== '' ? $name : $holderName,
                    'registrationName' => $holderName,
                    'isCompanion' => ! $attendee->is_primary,
                    'statusLabel' => $registration->status->label(),
                    'meals' => $meals,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{name: string, registrationName: string, isCompanion: bool, statusLabel: string, meals: array<string, string>}>  $people
     * @return array{key: string, label: string, options: list<array{label: string, count: int, quota: ?int, remaining: ?int}>}
     */
    private function question(FormField $field, Collection $people): array
    {
        return [
            'key' => $field->key,
            'label' => $field->label,
            'options' => $field->options
                ->map(fn (FieldOption $option): array => [
                    'label' => $option->label,
                    'count' => $people->filter(fn (array $person): bool => ($person['meals'][$field->key] ?? '') === $option->label)->count(),
                    'quota' => $option->quota,
                    'remaining' => $this->getRemainingCapacity->handle('form_field_option', (string) $option->id, $option->quota),
                ])
                ->values()
                ->all(),
        ];
    }
}
