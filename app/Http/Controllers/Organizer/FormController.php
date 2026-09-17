<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Actions\UpdateFormDraft;
use App\Domain\Form\Actions\UpdateFormSettings;
use App\Domain\Form\FormRequiresPaidPlanException;
use App\Domain\Form\InvalidConditionalRuleException;
use App\Domain\Form\Models\ConditionalRule;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Form\Support\DonationAnswer;
use App\Domain\Form\Support\FileUploadAnswer;
use App\Domain\Form\Support\FormSettings;
use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\SaveFormRequest;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Registration\ResolveSubEventFieldConfig;
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
            'event' => $this->presentEvent($event),
            'form' => null,
            ...$this->builderOptions($event),
        ]);
    }

    public function store(SaveFormRequest $request, int $event, CreateForm $action, UpdateFormSettings $updateFormSettings, ResolveSubEventFieldConfig $resolveSubEventFieldConfig): RedirectResponse
    {
        $eventModel = $this->findEvent($event);
        $data = $request->validated();
        $data['fields'] = $resolveSubEventFieldConfig->handle($eventModel->id, $data['fields']);

        try {
            $form = $action->handle($this->currentOrganization(), $eventModel->id, $request->user(), $data);
        } catch (InvalidConditionalRuleException $exception) {
            return back()->withErrors(['rules' => $exception->getMessage()]);
        }

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
            'event' => $this->presentEvent($event),
            'form' => $this->presentForm($form),
            ...$this->builderOptions($event),
        ]);
    }

    public function update(SaveFormRequest $request, int $form, UpdateFormSettings $updateFormSettings, ResolveSubEventFieldConfig $resolveSubEventFieldConfig): RedirectResponse
    {
        $formModel = $this->findForm($form);
        $data = $request->validated();
        $data['fields'] = $resolveSubEventFieldConfig->handle($formModel->event_id, $data['fields']);

        try {
            $this->writeVersion($formModel, $request->user(), $data);
        } catch (InvalidConditionalRuleException $exception) {
            return back()->withErrors(['rules' => $exception->getMessage()]);
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

    /**
     * Un brouillon jamais publié se modifie en place ; une version publiée ne
     * change jamais, la modification ouvre une nouvelle version (règle 4.7).
     *
     * @param  array<string, mixed>  $data
     */
    private function writeVersion(Form $form, User $editor, array $data): void
    {
        if ($form->latestVersion()?->status === FormVersionStatus::Published) {
            app(ReviseForm::class)->handle($form, $editor, $data['fields'], $data['rules'] ?? []);

            return;
        }

        app(UpdateFormDraft::class)->handle($form, $editor, $data['fields'], $data['rules'] ?? [], $data['name']);
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
     * @return array{id: int, title: string, phoneRequired: bool}
     */
    private function presentEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            // Même règle que l'étape « Coordonnées » du parcours invité.
            'phoneRequired' => $event->type->category() === EventCategory::Personal,
        ];
    }

    /**
     * Ce dont le constructeur à blocs a besoin en plus du formulaire : types
     * de questions (avec leur marque premium), polices du thème, tags pour le
     * critère « Seulement pour les invités portant le tag… » et sessions pour
     * le bloc « Événements secondaires ».
     *
     * @return array<string, mixed>
     */
    private function builderOptions(Event $event): array
    {
        return [
            'fieldTypes' => array_map(
                fn (FieldType $type): array => ['value' => $type->value, 'label' => $type->label(), 'premium' => $type->isPremium()],
                FieldType::cases(),
            ),
            'fonts' => array_map(
                fn (string $key, array $font): array => ['value' => $key, 'label' => $font['label'], 'stack' => $font['stack']],
                array_keys(FormSettings::FONTS),
                FormSettings::FONTS,
            ),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Tag $tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all(),
            'isFreePlan' => app(GetEffectivePlan::class)->handle($this->currentOrganization()) === PlanTier::Free,
            'defaultSettings' => FormSettings::resolve(null),
            'subEvents' => $event->subEvents()->orderBy('start_at')->get()
                ->map(fn (Event $subEvent): array => [
                    'id' => $subEvent->id,
                    'title' => $subEvent->title,
                    'schedule' => $subEvent->start_at->setTimezone($subEvent->timezone)->translatedFormat('j F \à H\hi'),
                ])
                ->values()
                ->all(),
            'subEventsUrl' => route('events.sub-events.index', $event->id),
            // Bloc « Don » (T-056) : la devise se choisit dans le bloc, celle
            // de l'événement proposée d'abord quand elle fait partie de la liste.
            'donationCurrencies' => array_map(
                fn (string $code, string $label): array => ['code' => $code, 'label' => $label],
                array_keys(DonationAnswer::CURRENCIES),
                DonationAnswer::CURRENCIES,
            ),
            'defaultDonationCurrency' => DonationAnswer::currency(['currency' => $event->currency]),
            // Bloc « Fichier joint » : formats proposés et taille maximale.
            'fileUploadTypes' => array_map(
                fn (string $value, array $type): array => ['value' => $value, 'label' => $type['label']],
                array_keys(FileUploadAnswer::TYPES),
                FileUploadAnswer::TYPES,
            ),
            'maxFileSizeMb' => FileUploadAnswer::MAX_SIZE_MB,
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
            'has_published_version' => $form->hasPublishedVersion(),
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
        $config = $field->config ?? [];

        // L'aperçu du constructeur a besoin de l'adresse de l'image ; la
        // configuration enregistrée, elle, ne garde que son chemin.
        if (is_string($config['image_path'] ?? null)) {
            $config['image_url'] = Storage::disk('public')->url($config['image_path']);
        }

        return [
            'key' => $field->key,
            'type' => $field->type->value,
            'label' => $field->label,
            'help_text' => $field->help_text,
            'is_required' => $field->is_required,
            'config' => $config,
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
