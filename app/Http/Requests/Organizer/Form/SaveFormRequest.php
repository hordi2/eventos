<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Form;

use App\Domain\Form\Data\CompanionData;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\RuleAction;
use App\Domain\Form\Support\AskScope;
use App\Domain\Form\Support\CustomCss;
use App\Domain\Form\Support\DonationAnswer;
use App\Domain\Form\Support\FileUploadAnswer;
use App\Domain\Form\Support\FormSettings;
use App\Domain\Form\Support\VideoEmbed;
use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Utilisée à la fois pour créer et pour enregistrer un formulaire : les deux
 * actions envoient la même forme de données (nom, champs, règles, réglages),
 * seule l'action qui les reçoit diffère (CreateForm vs UpdateFormDraft/ReviseForm).
 */
final class SaveFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'fields' => ['present', 'array'],
            'fields.*.key' => ['nullable', 'string', 'max:255', Rule::notIn([CompanionData::INPUT_KEY])],
            'fields.*.type' => ['required', Rule::enum(FieldType::class)],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.config' => ['nullable', 'array'],
            // Réglages « Demander si » et « Seulement pour les invités portant
            // le tag… » du panneau d'un bloc (EvaluateFormVisibility).
            'fields.*.config.show_if' => ['nullable', Rule::in(['always', 'attending', 'not_attending'])],
            // « Poser la question » : une fois, ou à chaque personne (T-032).
            'fields.*.config.ask_scope' => ['nullable', Rule::in([AskScope::ONCE, AskScope::EACH_ATTENDEE])],
            // Bloc « Événements secondaires » : l'appartenance des sessions à
            // l'événement et leurs titres sont repris du serveur
            // (ResolveSubEventFieldConfig), jamais du navigateur.
            'fields.*.config.sub_events' => ['nullable', 'array'],
            'fields.*.config.sub_events.*.id' => ['required', 'integer'],
            // Bloc « Don » (T-056) : devise, montants proposés en unité
            // mineure (§4.2), montant libre autorisé, cause soutenue.
            'fields.*.config.currency' => ['nullable', Rule::in(array_keys(DonationAnswer::CURRENCIES))],
            'fields.*.config.amounts' => ['nullable', 'array', 'max:'.DonationAnswer::MAX_SUGGESTED_AMOUNTS],
            'fields.*.config.amounts.*' => ['integer', 'min:1', 'max:100000000000'],
            'fields.*.config.allow_custom' => ['nullable', 'boolean'],
            'fields.*.config.cause' => ['nullable', 'string', 'max:255'],
            // Bloc « Fichier joint » : formats acceptés et taille maximale (CDC M2.1).
            'fields.*.config.file_types' => ['nullable', 'array', 'min:1'],
            'fields.*.config.file_types.*' => [Rule::in(array_keys(FileUploadAnswer::TYPES))],
            'fields.*.config.max_size_mb' => ['nullable', 'integer', 'min:1', 'max:'.FileUploadAnswer::MAX_SIZE_MB],
            // Bloc « Texte, image, vidéo » : la configuration ne garde que le
            // chemin de l'image, qui doit désigner une image de la
            // bibliothèque de l'organisation — son adresse publique est
            // recalculée à l'affichage.
            'fields.*.config.image_path' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('organization_images', 'path')
                    ->where('organization_id', app(CurrentOrganization::class)->requireId())
                    ->whereNull('deleted_at'),
            ],
            'fields.*.config.image_alt' => ['nullable', 'string', 'max:255'],
            'fields.*.config.image_width' => ['nullable', 'integer', 'min:1'],
            'fields.*.config.image_height' => ['nullable', 'integer', 'min:1'],
            'fields.*.config.image_url' => ['prohibited'],
            'fields.*.config.video_url' => ['nullable', 'string', 'max:2048'],
            'fields.*.config.tag_ids' => ['nullable', 'array'],
            'fields.*.config.tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('organization_id', app(CurrentOrganization::class)->requireId()),
            ],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'fields.*.options.*.label' => ['required', 'string', 'max:255'],
            'fields.*.options.*.quota' => ['nullable', 'integer', 'min:0'],

            'rules' => ['nullable', 'array'],
            'rules.*.target_field_key' => ['required', 'string'],
            'rules.*.action' => ['required', Rule::enum(RuleAction::class)],
            'rules.*.condition_group' => ['required', 'array'],

            ...FormSettings::rules(),
        ];
    }

    /**
     * Un bloc « Don » sans montant proposé ni montant libre ne laisserait
     * aucun choix à l'invité.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('fields', []) as $index => $field) {
                    if (! is_array($field) || ($field['type'] ?? null) !== FieldType::Donation->value) {
                        continue;
                    }

                    $config = is_array($field['config'] ?? null) ? $field['config'] : [];

                    if (DonationAnswer::suggestedAmounts($config) === [] && ! DonationAnswer::allowsCustom($config)) {
                        $validator->errors()->add("fields.{$index}.config.amounts", "Proposez au moins un montant ou laissez l'invité choisir le sien.");
                    }
                }
            },
            // Seules YouTube et Vimeo sont reconnues, et seule leur adresse
            // d'intégration sans cookie est servie à l'invité (VideoEmbed).
            function (Validator $validator): void {
                foreach ((array) $this->input('fields', []) as $index => $field) {
                    $video = is_array($field) && is_array($field['config'] ?? null) ? ($field['config']['video_url'] ?? null) : null;

                    if ($video !== null && $video !== '' && VideoEmbed::from($video) === null) {
                        $validator->errors()->add("fields.{$index}.config.video_url", 'Collez un lien YouTube ou Vimeo.');
                    }
                }
            },
            // CSS personnalisé : réservé aux plans payants, contrairement aux
            // questions avancées il s'applique dès l'enregistrement — un plan
            // Gratuit ne peut donc pas l'enregistrer du tout. Les
            // constructions refusées sont nommées, pour que l'organisateur
            // sache quoi corriger.
            function (Validator $validator): void {
                $css = $this->input('settings.theme.custom_css');

                if (! is_string($css) || trim($css) === '') {
                    return;
                }

                $organization = Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());

                if (app(GetEffectivePlan::class)->handle($organization) === PlanTier::Free) {
                    $validator->errors()->add('settings.theme.custom_css', 'Le CSS personnalisé est réservé aux plans payants.');

                    return;
                }

                $problems = CustomCss::problems($css);

                if ($problems !== []) {
                    $validator->errors()->add('settings.theme.custom_css', 'Ce CSS ne peut pas être publié : '.implode(', ', $problems).'.');
                }
            },
        ];
    }
}
