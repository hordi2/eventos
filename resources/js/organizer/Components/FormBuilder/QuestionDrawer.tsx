import { useState } from 'react';
import Checkbox from '../Checkbox';
import InputError from '../InputError';
import InputLabel from '../InputLabel';
import Select from '../Select';
import Textarea from '../Textarea';
import TextInput from '../TextInput';
import Toggle from '../Toggle';
import DonationSettings from './DonationSettings';
import { GROUP_WIDE_TYPES, PANEL_SECTION_TITLE, SHOW_IF_LABELS, TYPES_WITH_OPTIONS, withOptionValues } from './logic';
import OptionsEditor from './OptionsEditor';
import PremiumBadge from './PremiumBadge';
import RuleEditor from './RuleEditor';
import SidePanel from './SidePanel';
import SoonBadge from './SoonBadge';
import {
    type BuilderField,
    type CurrencyOption,
    type FieldConfig,
    type FieldTypeOption,
    type RuleData,
    type ShowIf,
    type SubEventOption,
    type TagOption,
} from './types';

interface QuestionDrawerProps {
    field: BuilderField;
    fields: BuilderField[];
    rule: RuleData | null;
    fieldTypes: FieldTypeOption[];
    tags: TagOption[];
    declineEnabled: boolean;
    isFreePlan: boolean;
    errors: Record<string, string>;
    // Bloc tout juste ajouté : sa clé sera tirée du texte à l'enregistrement.
    freshKey: boolean;
    // Accompagnants autorisés : la question peut alors être posée à chacun.
    companionsEnabled: boolean;
    subEvents: SubEventOption[];
    subEventsUrl: string;
    donationCurrencies: CurrencyOption[];
    onSave: (field: BuilderField, rule: RuleData | null) => void;
    onCancel: () => void;
    onRemove: () => void;
}

const SHOW_IF_CHOICES: ShowIf[] = ['always', 'attending', 'not_attending'];

const COUNTRIES: { value: string; label: string }[] = [
    { value: 'CD', label: 'RDC (+243)' },
    { value: 'CG', label: 'Congo (+242)' },
    { value: 'CM', label: 'Cameroun (+237)' },
    { value: 'CI', label: "Côte d'Ivoire (+225)" },
    { value: 'SN', label: 'Sénégal (+221)' },
    { value: 'FR', label: 'France (+33)' },
    { value: 'BE', label: 'Belgique (+32)' },
];

function toNumber(value: string): number | undefined {
    return value === '' ? undefined : Number(value);
}

/**
 * Réglages d'une question. Les modifications restent un brouillon local
 * jusqu'à « Enregistrer », comme dans un tiroir de propriétés classique.
 */
export default function QuestionDrawer({
    field,
    fields,
    rule,
    fieldTypes,
    tags,
    declineEnabled,
    isFreePlan,
    errors,
    freshKey,
    companionsEnabled,
    subEvents,
    subEventsUrl,
    donationCurrencies,
    onSave,
    onCancel,
    onRemove,
}: QuestionDrawerProps) {
    const [draft, setDraft] = useState<BuilderField>(() => ({
        ...field,
        config: { ...field.config },
        options: field.options.map((option) => ({ ...option })),
    }));
    const [draftRule, setDraftRule] = useState<RuleData | null>(rule);
    const [tagFilter, setTagFilter] = useState((field.config.tag_ids ?? []).length > 0);
    const [localError, setLocalError] = useState<string | null>(null);

    const type = fieldTypes.find((item) => item.value === draft.type);
    const isInformational = draft.type === 'informational_text';
    const hasOptions = TYPES_WITH_OPTIONS.includes(draft.type);
    const showIf: ShowIf = draft.config.show_if ?? 'attending';
    const askScope = draft.config.ask_scope ?? 'once';
    const isSubEvents = draft.type === 'sub_events';
    const isDonation = draft.type === 'donation';
    const isDonorInfo = draft.type === 'donor_info';
    const isGroupWide = GROUP_WIDE_TYPES.includes(draft.type);
    const offeredSubEvents = (draft.config.sub_events ?? []).map((subEvent) => subEvent.id);
    const tagIds = draft.config.tag_ids ?? [];
    const sources = fields.filter(
        (item) => item.uid !== field.uid && item.type !== 'informational_text' && item.type !== 'donation' && item.type !== 'donor_info',
    );

    function update(patch: Partial<BuilderField>) {
        setDraft((previous) => ({ ...previous, ...patch }));
    }

    function updateConfig(patch: Partial<FieldConfig>) {
        setDraft((previous) => ({ ...previous, config: { ...previous.config, ...patch } }));
    }

    function toggleTag(id: number) {
        const next = tagIds.includes(id) ? tagIds.filter((tagId) => tagId !== id) : [...tagIds, id];
        updateConfig({ tag_ids: next.length > 0 ? next : undefined });
    }

    function toggleSubEvent(subEvent: SubEventOption) {
        const current = draft.config.sub_events ?? [];
        const next = current.some((item) => item.id === subEvent.id)
            ? current.filter((item) => item.id !== subEvent.id)
            : [...current, { id: subEvent.id, title: subEvent.title }];

        updateConfig({ sub_events: next });
    }

    function toggleTagFilter(enabled: boolean) {
        setTagFilter(enabled);

        if (!enabled) {
            updateConfig({ tag_ids: undefined });
        }
    }

    function toggleRule(enabled: boolean) {
        const source = sources[0];

        if (!enabled || !source) {
            setDraftRule(null);

            return;
        }

        setDraftRule({ target_field_key: draft.key, action: 'show', condition: { field_key: source.key, operator: 'is', value: '' } });
    }

    function handleSave() {
        if (draft.label.trim() === '') {
            setLocalError(isInformational ? 'Le texte ne peut pas être vide.' : 'Écrivez la question avant de l’enregistrer.');

            return;
        }

        const options = hasOptions ? withOptionValues(draft.options) : [];

        if (hasOptions && options.length === 0) {
            setLocalError('Ajoutez au moins une option.');

            return;
        }

        if (isSubEvents && offeredSubEvents.length === 0) {
            setLocalError('Choisissez au moins une session à proposer.');

            return;
        }

        if (isDonation && (draft.config.amounts ?? []).length === 0 && draft.config.allow_custom === false) {
            setLocalError('Proposez au moins un montant ou laissez l’invité choisir le sien.');

            return;
        }

        onSave({ ...draft, label: draft.label.trim(), options }, draftRule ? { ...draftRule, target_field_key: draft.key } : null);
    }

    return (
        <SidePanel
            title={isInformational ? 'Texte, image, vidéo' : 'Réglages de la question'}
            icon={isInformational ? 'media' : 'question'}
            onClose={onCancel}
            footer={
                <>
                    <button type="button" onClick={onRemove} className="mr-auto text-sm font-medium text-danger hover:underline">
                        Retirer
                    </button>
                    <button type="button" onClick={onCancel} className="rounded-pill border border-line px-5 py-2 text-sm font-medium text-ink hover:border-ink">
                        Annuler
                    </button>
                    <button type="button" onClick={handleSave} className="rounded-pill bg-ink px-5 py-2 text-sm font-medium text-bg hover:opacity-90">
                        Enregistrer
                    </button>
                </>
            }
        >
            <div className="space-y-3">
                <p className="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                    Type : <strong className="font-medium text-ink">{type?.label ?? draft.type}</strong>
                    {type?.premium && <PremiumBadge />}
                </p>
                {type?.premium && isFreePlan && (
                    <p className="rounded-control bg-[#fff1cc] px-3 py-2 text-xs text-[#7a4f00]">
                        Essayez cette question librement : pour publier le formulaire avec elle, il faudra un plan payant.
                    </p>
                )}
                {localError && (
                    <p role="alert" className="text-sm text-danger">
                        {localError}
                    </p>
                )}
            </div>

            {isInformational ? (
                <div className="space-y-4">
                    <div className="flex flex-wrap gap-2">
                        <span className="rounded-pill bg-ink px-3 py-1 text-xs font-medium text-bg">Texte</span>
                        <span className="flex items-center gap-1.5 rounded-pill px-3 py-1 text-xs text-ink-soft ring-1 ring-line">
                            Image <SoonBadge />
                        </span>
                        <span className="flex items-center gap-1.5 rounded-pill px-3 py-1 text-xs text-ink-soft ring-1 ring-line">
                            Vidéo <SoonBadge />
                        </span>
                    </div>
                    <div>
                        <InputLabel htmlFor="block_text">Texte</InputLabel>
                        <Textarea id="block_text" value={draft.label} maxLength={255} onChange={(event) => update({ label: event.target.value })} />
                        <InputError message={errors.label} />
                    </div>
                </div>
            ) : (
                <div className="space-y-5">
                    <div>
                        <InputLabel htmlFor="block_label">Question</InputLabel>
                        <TextInput id="block_label" type="text" value={draft.label} maxLength={255} onChange={(event) => update({ label: event.target.value })} />
                        <InputError message={errors.label} />
                    </div>
                    <div>
                        <InputLabel htmlFor="block_help">Description</InputLabel>
                        <Textarea
                            id="block_help"
                            value={draft.help_text ?? ''}
                            placeholder="Précision affichée sous la question (facultatif)"
                            onChange={(event) => update({ help_text: event.target.value === '' ? null : event.target.value })}
                        />
                    </div>
                </div>
            )}

            {hasOptions && <OptionsEditor options={draft.options} withQuota onChange={(options) => update({ options })} error={errors['options.0.label']} />}

            {isSubEvents && (
                <fieldset>
                    <legend className={PANEL_SECTION_TITLE}>Sessions proposées</legend>
                    {subEvents.length === 0 ? (
                        <p className="text-sm text-ink-soft">Aucun événement secondaire pour l'instant.</p>
                    ) : (
                        <div className="space-y-3">
                            {subEvents.map((subEvent) => (
                                <label key={subEvent.id} className="flex items-start gap-2 text-sm text-ink">
                                    <Checkbox className="mt-0.5" checked={offeredSubEvents.includes(subEvent.id)} onChange={() => toggleSubEvent(subEvent)} />
                                    <span>
                                        <span className="block font-medium">{subEvent.title}</span>
                                        <span className="block text-xs text-ink-soft">{subEvent.schedule}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                    <p className="mt-3 text-xs text-ink-soft">
                        Chaque session cochée par l'invité l'inscrit, avec ses accompagnants, sur sa capacité et sa liste d'attente propres.
                    </p>
                    <a href={subEventsUrl} className="mt-2 inline-block text-sm font-medium text-ink underline">
                        Gérer les événements secondaires
                    </a>
                </fieldset>
            )}

            {isDonation && <DonationSettings config={draft.config} currencies={donationCurrencies} onChange={updateConfig} />}

            {isDonorInfo && (
                <p className="rounded-control bg-bg-alt px-3 py-2 text-xs text-ink-soft">
                    L'invité indique son nom (prérempli), son entreprise, son adresse, et peut demander à rester anonyme. Ces informations figurent sur son
                    reçu : elles ne sont demandées que s'il fait un don.
                </p>
            )}

            {(draft.type === 'short_text' || draft.type === 'long_text') && (
                <div>
                    <InputLabel htmlFor="block_max_length">Nombre maximal de caractères</InputLabel>
                    <TextInput
                        id="block_max_length"
                        type="number"
                        min={1}
                        value={draft.config.max_length ?? ''}
                        onChange={(event) => updateConfig({ max_length: toNumber(event.target.value) })}
                    />
                </div>
            )}

            {(draft.type === 'number' || draft.type === 'quantity') && (
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="block_min">Minimum</InputLabel>
                        <TextInput
                            id="block_min"
                            type="number"
                            value={draft.config.min ?? ''}
                            placeholder={draft.type === 'quantity' ? '0' : undefined}
                            onChange={(event) => updateConfig({ min: toNumber(event.target.value) })}
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="block_max">Maximum</InputLabel>
                        <TextInput
                            id="block_max"
                            type="number"
                            value={draft.config.max ?? ''}
                            placeholder={draft.type === 'quantity' ? '99' : undefined}
                            onChange={(event) => updateConfig({ max: toNumber(event.target.value) })}
                        />
                    </div>
                </div>
            )}

            {draft.type === 'phone' && (
                <div>
                    <InputLabel htmlFor="block_country">Indicatif par défaut</InputLabel>
                    <Select
                        id="block_country"
                        value={draft.config.default_country ?? ''}
                        onChange={(event) => updateConfig({ default_country: event.target.value || undefined })}
                    >
                        <option value="">Aucun</option>
                        {COUNTRIES.map((country) => (
                            <option key={country.value} value={country.value}>
                                {country.label}
                            </option>
                        ))}
                    </Select>
                </div>
            )}

            {draft.type === 'consent' && (
                <div>
                    <InputLabel htmlFor="block_legal">Texte accepté par l'invité</InputLabel>
                    <Textarea
                        id="block_legal"
                        value={draft.config.legal_text ?? ''}
                        onChange={(event) => updateConfig({ legal_text: event.target.value || undefined })}
                    />
                </div>
            )}

            {!isInformational && !isGroupWide && (
                <fieldset>
                    <legend className={PANEL_SECTION_TITLE}>Poser la question</legend>
                    <div className="space-y-2 text-sm">
                        <label className="flex items-center gap-2 text-ink">
                            <input type="radio" name="ask_scope" checked={askScope === 'once'} onChange={() => updateConfig({ ask_scope: undefined })} />
                            Une fois par réponse
                        </label>
                        <label className={`flex items-center gap-2 ${companionsEnabled ? 'text-ink' : 'text-ink-soft'}`}>
                            <input
                                type="radio"
                                name="ask_scope"
                                disabled={!companionsEnabled}
                                checked={askScope === 'each_attendee'}
                                onChange={() => updateConfig({ ask_scope: 'each_attendee' })}
                            />
                            À chaque personne (l'invité et ses accompagnants)
                        </label>
                    </div>
                    {!companionsEnabled && (
                        <p className="mt-2 text-xs text-ink-soft">Autorisez des accompagnants dans « Coordonnées et réponse » pour poser une question à chacun.</p>
                    )}
                </fieldset>
            )}

            <fieldset>
                <legend className={PANEL_SECTION_TITLE}>Demander si</legend>
                <div className="space-y-2 text-sm">
                    {SHOW_IF_CHOICES.map((choice) => (
                        <label key={choice} className="flex items-center gap-2 text-ink">
                            <input type="radio" name="show_if" checked={showIf === choice} onChange={() => updateConfig({ show_if: choice })} />
                            {SHOW_IF_LABELS[choice]}
                        </label>
                    ))}
                </div>
                {!declineEnabled && (
                    <p className="mt-2 text-xs text-ink-soft">
                        La réponse « Je ne peux pas venir » n'est pas proposée : tous les invités sont considérés comme présents.
                    </p>
                )}
            </fieldset>

            <fieldset>
                <legend className={PANEL_SECTION_TITLE}>Critères supplémentaires</legend>
                <label className="flex items-start gap-2 text-sm text-ink">
                    <Checkbox className="mt-0.5" checked={tagFilter} onChange={(event) => toggleTagFilter(event.target.checked)} />
                    Seulement pour les invités portant l'un de ces tags
                </label>
                {tagFilter &&
                    (tags.length === 0 ? (
                        <p className="mt-2 text-xs text-ink-soft">Aucun tag dans vos contacts pour l'instant.</p>
                    ) : (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {tags.map((tag) => (
                                <label
                                    key={tag.id}
                                    className={`flex cursor-pointer items-center gap-1.5 rounded-pill px-3 py-1 text-xs ring-1 ${
                                        tagIds.includes(tag.id) ? 'bg-ink text-bg ring-ink' : 'text-ink ring-line hover:ring-ink'
                                    }`}
                                >
                                    <input type="checkbox" className="sr-only" checked={tagIds.includes(tag.id)} onChange={() => toggleTag(tag.id)} />
                                    {tag.name}
                                </label>
                            ))}
                        </div>
                    ))}
                {tagFilter && (
                    <p className="mt-2 text-xs text-ink-soft">L'invité est reconnu par son adresse e-mail : un invité absent de vos contacts ne verra pas cette question.</p>
                )}
            </fieldset>

            <fieldset>
                <legend className={PANEL_SECTION_TITLE}>Logique conditionnelle</legend>
                {sources.length === 0 ? (
                    <p className="text-sm text-ink-soft">Ajoutez une autre question pour pouvoir créer une condition.</p>
                ) : (
                    <>
                        <label className="flex items-start gap-2 text-sm text-ink">
                            <Checkbox className="mt-0.5" checked={draftRule !== null} onChange={(event) => toggleRule(event.target.checked)} />
                            Dépend de la réponse à une autre question
                        </label>
                        {draftRule && <RuleEditor rule={draftRule} sources={sources} onChange={setDraftRule} />}
                    </>
                )}
                <InputError message={errors.rules} />
            </fieldset>

            {!isInformational && (
                <div className="flex items-center justify-between gap-4">
                    <span className="text-sm font-medium text-ink">Obligatoire</span>
                    <Toggle checked={draft.is_required} onChange={(checked) => update({ is_required: checked })} label="Question obligatoire" />
                </div>
            )}

            <p className="text-xs text-ink-soft">
                {freshKey ? (
                    "L'identifiant utilisé dans les exports sera tiré du texte de la question."
                ) : (
                    <>
                        Identifiant dans les exports : <code className="rounded bg-bg-alt px-1 py-0.5">{draft.key}</code>
                    </>
                )}
            </p>
        </SidePanel>
    );
}
