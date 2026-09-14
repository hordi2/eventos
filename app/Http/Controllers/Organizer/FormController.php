<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Actions\UpdateFormDraft;
use App\Domain\Form\Actions\UpdateFormSettings;
use App\Domain\Form\FormRequiresPaidPlanException;
use App\Domain\Form\Models\ConditionalRule;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Form\Support\FormSettings;
use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\SaveFormRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
            ...$this->builderOptions(),
        ]);
    }

    public function store(SaveFormRequest $request, int $event, CreateForm $action, UpdateFormSettings $updateFormSettings): RedirectResponse
    {
        $eventModel = $this->findEvent($event);
        $data = $request->validated();
        $form = $action->handle($this->currentOrganization(), $eventModel->id, $request->user(), $data);

        if (is_array($data['settings'] ?? null)) {
            $updateFormSettings->handle($form, $request->user(), $data['settings']);
        }

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
            ...$this->builderOptions(),
        ]);
    }

    public function update(SaveFormRequest $request, int $form, UpdateFormDraft $updateFormDraft, ReviseForm $reviseForm, UpdateFormSettings $updateFormSettings): RedirectResponse
    {
        $formModel = $this->findForm($form);
        $latest = $formModel->latestVersion();
        $data = $request->validated();

        if ($latest?->status === FormVersionStatus::Published) {
            $reviseForm->handle($formModel, $request->user(), $data['fields'], $data['rules'] ?? []);
        } else {
            $updateFormDraft->handle($formModel, $request->user(), $data['fields'], $data['rules'] ?? [], $data['name']);
        }

        if (is_array($data['settings'] ?? null)) {
            $updateFormSettings->handle($formModel, $request->user(), $data['settings']);
        }

        return redirect()->route('forms.edit', $formModel);
    }

    public function publish(int $form, PublishFormVersion $action): RedirectResponse
    {
        $formModel = $this->findForm($form);

        try {
            $action->handle($formModel, request()->user());
        } catch (FormRequiresPaidPlanException $exception) {
            return redirect()->route('forms.edit', $formModel)->withErrors(['publish' => $exception->getMessage()]);
        }

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
     * Ce dont le constructeur à blocs a besoin en plus du formulaire : types
     * de questions (avec leur marque premium), polices du thème, tags pour le
     * critère « Seulement pour les invités portant le tag… ».
     *
     * @return array<string, mixed>
     */
    private function builderOptions(): array
    {
        return [
            'fieldTypes' => array_map(
                fn (FieldType $type): array => ['value' => $type->value, 'label' => $type->label(), 'premium' => $type->isPremium()],
                FieldType::cases(),
            ),
            'fonts' => array_map(
                fn (string $key, array $font): array => ['value' => $key, 'label' => $font['label']],
                array_keys(FormSettings::FONTS),
                FormSettings::FONTS,
            ),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Tag $tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all(),
            'isFreePlan' => app(GetEffectivePlan::class)->handle($this->currentOrganization()) === PlanTier::Free,
            'defaultSettings' => FormSettings::resolve(null),
        ];
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
            'settings' => $this->presentSettings($form),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function presentSettings(Form $form): array
    {
        $settings = FormSettings::resolve($form->settings);
        $theme = $settings['theme'];

        $settings['theme']['logo_url'] = is_string($theme['logo_path']) ? Storage::disk('public')->url($theme['logo_path']) : null;
        $settings['theme']['background_image_url'] = is_string($theme['background_image_path']) ? Storage::disk('public')->url($theme['background_image_path']) : null;

        return $settings;
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
