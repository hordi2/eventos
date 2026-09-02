<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Actions\UpdateFormDraft;
use App\Domain\Form\Models\ConditionalRule;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\SaveFormRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FormController extends Controller
{
    public function create(int $event): Response
    {
        $event = $this->findEvent($event);

        Gate::authorize('create', [Form::class, $this->currentOrganization()]);

        return Inertia::render('Forms/Builder', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'form' => null,
            'fieldTypes' => $this->fieldTypeOptions(),
        ]);
    }

    public function store(SaveFormRequest $request, int $event, CreateForm $action): RedirectResponse
    {
        $eventModel = $this->findEvent($event);
        $form = $action->handle($this->currentOrganization(), $eventModel->id, $request->user(), $request->validated());

        return redirect()->route('forms.edit', $form);
    }

    public function edit(int $form): Response
    {
        $form = $this->findForm($form);

        Gate::authorize('update', $form);

        $event = Event::query()->findOrFail($form->event_id);

        return Inertia::render('Forms/Builder', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'form' => $this->presentForm($form),
            'fieldTypes' => $this->fieldTypeOptions(),
        ]);
    }

    public function update(SaveFormRequest $request, int $form, UpdateFormDraft $updateFormDraft, ReviseForm $reviseForm): RedirectResponse
    {
        $formModel = $this->findForm($form);
        $latest = $formModel->latestVersion();
        $data = $request->validated();

        if ($latest?->status === FormVersionStatus::Published) {
            $reviseForm->handle($formModel, $request->user(), $data['fields'], $data['rules'] ?? []);
        } else {
            $updateFormDraft->handle($formModel, $request->user(), $data['fields'], $data['rules'] ?? [], $data['name']);
        }

        return redirect()->route('forms.edit', $formModel);
    }

    public function publish(int $form, PublishFormVersion $action): RedirectResponse
    {
        $formModel = $this->findForm($form);
        $action->handle($formModel, request()->user());

        return redirect()->route('forms.edit', $formModel);
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }

    private function findForm(int $id): Form
    {
        return Form::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fieldTypeOptions(): array
    {
        $labels = [
            'short_text' => 'Texte court',
            'long_text' => 'Texte long',
            'number' => 'Nombre',
            'email' => 'E-mail',
            'phone' => 'Téléphone',
            'date' => 'Date',
            'single_choice' => 'Choix unique',
            'multiple_choice' => 'Choix multiple',
            'yes_no' => 'Oui / Non',
            'consent' => 'Consentement',
            'meal_choice' => 'Menu / repas',
            'informational_text' => 'Texte informatif',
        ];

        return array_map(
            fn (FieldType $type): array => ['value' => $type->value, 'label' => $labels[$type->value]],
            FieldType::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentForm(Form $form): array
    {
        $version = $form->latestVersion();
        $version?->loadMissing(['fields.options', 'conditionalRules.targetField']);

        return [
            'id' => $form->id,
            'name' => $form->name,
            'status' => $version?->status->value ?? 'draft',
            'fields' => $version?->fields->map($this->presentField(...))->all() ?? [],
            'rules' => $version?->conditionalRules->map($this->presentRule(...))->all() ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentField(FormField $field): array
    {
        return [
            'key' => $field->key,
            'type' => $field->type->value,
            'label' => $field->label,
            'help_text' => $field->help_text,
            'is_required' => $field->is_required,
            'config' => $field->config ?? [],
            'options' => $field->options->map(fn ($option): array => [
                'value' => $option->value,
                'label' => $option->label,
                'quota' => $option->quota,
            ])->all(),
        ];
    }

    /**
     * Le constructeur ne propose qu'une seule condition par règle (pas
     * d'arborescence ET/OU imbriquée) : on ne récupère donc que la première.
     * Le moteur (T-022) supporte déjà plus, pour une future évolution de
     * cette interface.
     *
     * @return array<string, mixed>
     */
    private function presentRule(ConditionalRule $rule): array
    {
        $firstCondition = $rule->condition_group['conditions'][0] ?? ['field_key' => '', 'operator' => 'is', 'value' => ''];

        return [
            'target_field_key' => $rule->targetField->key,
            'action' => $rule->action->value,
            'condition' => [
                'field_key' => $firstCondition['field_key'] ?? '',
                'operator' => $firstCondition['operator'] ?? 'is',
                'value' => $firstCondition['value'] ?? '',
            ],
        ];
    }
}
