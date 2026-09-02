import { Head, Link, router } from '@inertiajs/react';
import { type DragEvent, type FormEvent, useEffect, useRef, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import Textarea from '../../Components/Textarea';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface EventSummary {
    id: number;
    title: string;
}

interface FieldTypeOption {
    value: string;
    label: string;
}

interface FieldOptionData {
    value: string;
    label: string;
    quota: number | null;
}

interface FieldData {
    key: string;
    type: string;
    label: string;
    help_text: string | null;
    is_required: boolean;
    config: Record<string, unknown>;
    options: FieldOptionData[];
}

type LocalField = FieldData & { _localId: string };

interface RuleConditionData {
    field_key: string;
    operator: string;
    value: string;
}

interface RuleData {
    target_field_key: string;
    action: string;
    condition: RuleConditionData;
}

interface FormPayload {
    id: number;
    name: string;
    status: string;
    fields: FieldData[];
    rules: RuleData[];
}

interface BuilderPageProps {
    event: EventSummary;
    form: FormPayload | null;
    fieldTypes: FieldTypeOption[];
}

const OPERATORS: { value: string; label: string }[] = [
    { value: 'is', label: 'est' },
    { value: 'is_not', label: "n'est pas" },
    { value: 'contains', label: 'contient' },
    { value: 'does_not_contain', label: 'ne contient pas' },
    { value: 'greater_than', label: 'supérieur à' },
    { value: 'less_than', label: 'inférieur à' },
    { value: 'is_empty', label: 'est vide' },
    { value: 'is_not_empty', label: "n'est pas vide" },
];

const ACTIONS: { value: string; label: string }[] = [
    { value: 'show', label: 'Afficher' },
    { value: 'hide', label: 'Masquer' },
    { value: 'require', label: 'Rendre obligatoire' },
];

const TYPES_WITH_OPTIONS = ['single_choice', 'multiple_choice', 'meal_choice'];

let localIdCounter = 0;
function nextLocalId(): string {
    localIdCounter += 1;
    return `local_${localIdCounter}`;
}

function defaultFieldFor(type: string, label: string, key: string): LocalField {
    return {
        _localId: nextLocalId(),
        key,
        type,
        label,
        help_text: null,
        is_required: false,
        config: {},
        options: TYPES_WITH_OPTIONS.includes(type) ? [{ value: '', label: '', quota: null }] : [],
    };
}

function evaluateCondition(condition: RuleConditionData, answers: Record<string, unknown>): boolean {
    const value = answers[condition.field_key];
    const target = condition.value;

    switch (condition.operator) {
        case 'is':
            return String(value ?? '') === String(target ?? '');
        case 'is_not':
            return String(value ?? '') !== String(target ?? '');
        case 'contains':
            return Array.isArray(value) ? value.map(String).includes(String(target)) : String(value ?? '').includes(String(target ?? ''));
        case 'does_not_contain':
            return Array.isArray(value) ? !value.map(String).includes(String(target)) : !String(value ?? '').includes(String(target ?? ''));
        case 'greater_than':
            return Number(value) > Number(target);
        case 'less_than':
            return Number(value) < Number(target);
        case 'is_empty':
            return value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0);
        case 'is_not_empty':
            return !(value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0));
        default:
            return false;
    }
}

/**
 * Reproduit EvaluateFormVisibility côté client, pour l'aperçu « simuler une
 * réponse » uniquement — la validation réelle d'une soumission restera
 * toujours tranchée par le serveur (aucune divergence possible sur ce qui
 * compte vraiment), cette copie ne sert qu'au confort de l'organisateur
 * pendant qu'il construit le formulaire.
 */
function computeVisibility(
    fields: LocalField[],
    rules: RuleData[],
    answers: Record<string, unknown>,
): Record<string, { visible: boolean; required: boolean }> {
    const state: Record<string, { visible: boolean; required: boolean }> = {};

    fields.forEach((field) => {
        state[field.key] = { visible: true, required: field.is_required };
    });

    rules.forEach((rule) => {
        if (!state[rule.target_field_key]) {
            return;
        }

        const matched = evaluateCondition(rule.condition, answers);

        if (rule.action === 'show') {
            state[rule.target_field_key].visible = matched;
        } else if (rule.action === 'hide') {
            state[rule.target_field_key].visible = !matched;
        } else if (rule.action === 'require') {
            state[rule.target_field_key].required = matched;
        }
    });

    return state;
}

export default function FormBuilder({ event, form, fieldTypes }: BuilderPageProps) {
    const [name, setName] = useState(form?.name ?? `Formulaire — ${event.title}`);
    const [fields, setFields] = useState<LocalField[]>(() => (form?.fields ?? []).map((f) => ({ ...f, _localId: nextLocalId() })));
    const [rules, setRules] = useState<RuleData[]>(form?.rules ?? []);
    const [selectedId, setSelectedId] = useState<string | null>(fields[0]?._localId ?? null);
    const [previewMode, setPreviewMode] = useState<'desktop' | 'mobile'>('desktop');
    const [simulatedAnswers, setSimulatedAnswers] = useState<Record<string, unknown>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const isPublished = form?.status === 'published';
    const saveTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const dragIndexRef = useRef<number | null>(null);
    const isSelfNavigatingRef = useRef(false);

    function markDirty() {
        setDirty(true);
    }

    function buildPayload() {
        return {
            name,
            fields: fields.map(({ _localId, ...field }) => field),
            rules: rules
                .filter((r) => r.target_field_key && r.condition.field_key)
                .map((r) => ({
                    target_field_key: r.target_field_key,
                    action: r.action,
                    condition_group: { combinator: 'and', conditions: [r.condition] },
                })),
        };
    }

    function performSave() {
        if (!form) {
            return;
        }

        setSaving(true);
        isSelfNavigatingRef.current = true;
        // FormDataConvertible ne modélise pas Record<string, unknown> pour
        // "config", qui peut légitimement contenir n'importe quelle valeur
        // scalaire selon le type de champ ; le payload reste un JSON simple
        // que seul ce contrôleur interprète.
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.patch(`/forms/${form.id}`, buildPayload() as any, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setDirty(false);
                setSaving(false);
                setErrors({});
            },
            onError: (serverErrors) => {
                setSaving(false);
                setErrors(serverErrors as Record<string, string>);
            },
            onFinish: () => {
                isSelfNavigatingRef.current = false;
            },
        });
    }

    // Sauvegarde automatique (débounce) dès que le formulaire existe déjà.
    useEffect(() => {
        if (!form || !dirty) {
            return;
        }

        if (saveTimeoutRef.current) {
            clearTimeout(saveTimeoutRef.current);
        }

        saveTimeoutRef.current = setTimeout(performSave, 1500);

        return () => {
            if (saveTimeoutRef.current) {
                clearTimeout(saveTimeoutRef.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [name, fields, rules, dirty, form]);

    // Avertissement de sortie non enregistrée : fermeture d'onglet...
    useEffect(() => {
        function handleBeforeUnload(e: BeforeUnloadEvent) {
            if (dirty) {
                e.preventDefault();
            }
        }

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [dirty]);

    // ...et navigation interne (liens Inertia) — mais jamais pour les visites
    // déclenchées par ce composant lui-même (créer/enregistrer/publier),
    // sinon la confirmation se déclenche sur l'enregistrement lui-même.
    useEffect(() => {
        return router.on('before', (visitEvent) => {
            if (isSelfNavigatingRef.current) {
                return;
            }

            if (dirty && !window.confirm("Des modifications ne sont pas encore enregistrées. Quitter quand même ?")) {
                visitEvent.preventDefault();
            }
        });
    }, [dirty]);

    function handleCreate(e: FormEvent) {
        e.preventDefault();
        isSelfNavigatingRef.current = true;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.post(`/events/${event.id}/form`, buildPayload() as any, {
            onFinish: () => {
                isSelfNavigatingRef.current = false;
            },
        });
    }

    function handlePublish() {
        if (!form || dirty) {
            return;
        }

        isSelfNavigatingRef.current = true;
        router.post(`/forms/${form.id}/publish`, {}, { onFinish: () => (isSelfNavigatingRef.current = false) });
    }

    function addField(type: string) {
        const typeLabel = fieldTypes.find((t) => t.value === type)?.label ?? type;
        const key = `champ_${fields.length + 1}`;
        const newField = defaultFieldFor(type, `Nouveau champ (${typeLabel})`, key);

        setFields((prev) => [...prev, newField]);
        setSelectedId(newField._localId);
        markDirty();
    }

    function updateField(localId: string, patch: Partial<FieldData>) {
        setFields((prev) => prev.map((f) => (f._localId === localId ? { ...f, ...patch } : f)));
        markDirty();
    }

    function removeField(localId: string) {
        const removed = fields.find((f) => f._localId === localId);
        setFields((prev) => prev.filter((f) => f._localId !== localId));

        if (removed) {
            setRules((prev) => prev.filter((r) => r.target_field_key !== removed.key && r.condition.field_key !== removed.key));
        }

        if (selectedId === localId) {
            setSelectedId(null);
        }

        markDirty();
    }

    function moveField(localId: string, direction: -1 | 1) {
        setFields((prev) => {
            const index = prev.findIndex((f) => f._localId === localId);
            const targetIndex = index + direction;

            if (index === -1 || targetIndex < 0 || targetIndex >= prev.length) {
                return prev;
            }

            const next = [...prev];
            [next[index], next[targetIndex]] = [next[targetIndex], next[index]];

            return next;
        });
        markDirty();
    }

    function handleDrop(index: number) {
        const from = dragIndexRef.current;
        dragIndexRef.current = null;

        if (from === null || from === index) {
            return;
        }

        setFields((prev) => {
            const next = [...prev];
            const [moved] = next.splice(from, 1);
            next.splice(index, 0, moved);

            return next;
        });
        markDirty();
    }

    function addRule() {
        const selectableTargets = fields.filter((f) => f.type !== 'informational_text' && !rules.some((r) => r.target_field_key === f.key));

        if (selectableTargets.length === 0 || fields.length < 2) {
            return;
        }

        const target = selectableTargets[0];
        const source = fields.find((f) => f.key !== target.key) ?? fields[0];

        setRules((prev) => [
            ...prev,
            { target_field_key: target.key, action: 'show', condition: { field_key: source.key, operator: 'is', value: '' } },
        ]);
        markDirty();
    }

    function updateRule(index: number, patch: Partial<RuleData>) {
        setRules((prev) => prev.map((r, i) => (i === index ? { ...r, ...patch } : r)));
        markDirty();
    }

    function updateRuleCondition(index: number, patch: Partial<RuleConditionData>) {
        setRules((prev) => prev.map((r, i) => (i === index ? { ...r, condition: { ...r.condition, ...patch } } : r)));
        markDirty();
    }

    function removeRule(index: number) {
        setRules((prev) => prev.filter((_, i) => i !== index));
        markDirty();
    }

    const selectedField = fields.find((f) => f._localId === selectedId) ?? null;
    const visibility = computeVisibility(fields, rules, simulatedAnswers);

    return (
        <OrganizerLayout title={form ? name : 'Nouveau formulaire'} eyebrow={event.title}>
            <Head title={form ? name : 'Nouveau formulaire'} />

            <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    {form && <Badge variant={isPublished ? 'success' : 'neutral'}>{isPublished ? 'Publié' : 'Brouillon'}</Badge>}
                    {form && dirty && <span className="text-sm text-ink-soft">Modifications non enregistrées…</span>}
                    {form && !dirty && saving && <span className="text-sm text-ink-soft">Enregistrement…</span>}
                    {form && !dirty && !saving && <span className="text-sm text-ink-soft">Enregistré</span>}
                </div>

                <div className="flex gap-3">
                    {form && (
                        <Button type="button" variant="secondary" onClick={handlePublish} disabled={dirty}>
                            {isPublished ? 'Publier une nouvelle version' : 'Publier'}
                        </Button>
                    )}
                    <Link
                        href="/dashboard"
                        className="inline-flex min-h-11 items-center justify-center gap-2.5 rounded-pill border border-line px-8 py-4 font-sans text-[14.5px] font-medium text-ink transition-all duration-300 hover:-translate-y-0.5 hover:border-ink"
                    >
                        Terminer
                    </Link>
                </div>
            </div>

            <div className="mb-8">
                <InputLabel htmlFor="form_name">Nom du formulaire</InputLabel>
                <TextInput
                    id="form_name"
                    type="text"
                    value={name}
                    onChange={(e) => {
                        setName(e.target.value);
                        markDirty();
                    }}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
                {/* Colonne 1 : liste des champs */}
                <div>
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Champs</h2>
                        <AddFieldMenu fieldTypes={fieldTypes} onAdd={addField} />
                    </div>

                    <ul className="space-y-2">
                        {fields.map((field, index) => (
                            <li
                                key={field._localId}
                                draggable
                                onDragStart={() => {
                                    dragIndexRef.current = index;
                                }}
                                onDragOver={(e: DragEvent) => e.preventDefault()}
                                onDrop={() => handleDrop(index)}
                                className={`flex items-center gap-2 rounded-card border px-3 py-2.5 transition-colors duration-300 ${
                                    field._localId === selectedId ? 'border-accent' : 'border-line'
                                }`}
                            >
                                <button
                                    type="button"
                                    onClick={() => setSelectedId(field._localId)}
                                    className="min-w-0 flex-1 truncate text-left font-sans text-sm text-ink"
                                >
                                    {field.label || '(sans libellé)'}
                                    <span className="ml-2 text-xs text-ink-soft">
                                        {fieldTypes.find((t) => t.value === field.type)?.label}
                                    </span>
                                </button>
                                <div className="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        aria-label="Monter"
                                        onClick={() => moveField(field._localId, -1)}
                                        disabled={index === 0}
                                        className="px-1 text-ink-soft hover:text-ink disabled:opacity-30"
                                    >
                                        ↑
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Descendre"
                                        onClick={() => moveField(field._localId, 1)}
                                        disabled={index === fields.length - 1}
                                        className="px-1 text-ink-soft hover:text-ink disabled:opacity-30"
                                    >
                                        ↓
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="Supprimer"
                                        onClick={() => removeField(field._localId)}
                                        className="px-1 text-danger hover:opacity-70"
                                    >
                                        ×
                                    </button>
                                </div>
                            </li>
                        ))}
                        {fields.length === 0 && <p className="text-sm text-ink-soft">Aucun champ pour l'instant.</p>}
                    </ul>

                    <div className="mt-10 flex items-center justify-between">
                        <h2 className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Logique conditionnelle</h2>
                        <button type="button" onClick={addRule} className="font-label text-xs tracking-[0.1em] text-accent uppercase hover:opacity-80">
                            + Ajouter une règle
                        </button>
                    </div>

                    <ul className="mt-4 space-y-4">
                        {rules.map((rule, index) => (
                            <RuleEditor
                                key={index}
                                rule={rule}
                                fields={fields}
                                onChange={(patch) => updateRule(index, patch)}
                                onChangeCondition={(patch) => updateRuleCondition(index, patch)}
                                onRemove={() => removeRule(index)}
                            />
                        ))}
                        {rules.length === 0 && <p className="text-sm text-ink-soft">Aucune règle : tous les champs sont toujours visibles.</p>}
                    </ul>
                </div>

                {/* Colonne 2 : propriétés du champ sélectionné */}
                <div>
                    <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Propriétés</h2>
                    {selectedField ? (
                        <FieldProperties field={selectedField} onChange={(patch) => updateField(selectedField._localId, patch)} />
                    ) : (
                        <p className="text-sm text-ink-soft">Sélectionne un champ pour modifier ses propriétés.</p>
                    )}
                </div>

                {/* Colonne 3 : aperçu */}
                <div>
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Aperçu — simuler une réponse</h2>
                        <div className="flex gap-1">
                            <button
                                type="button"
                                onClick={() => setPreviewMode('desktop')}
                                className={`rounded-pill px-3 py-1 text-xs uppercase ${previewMode === 'desktop' ? 'bg-ink text-bg' : 'text-ink-soft'}`}
                            >
                                Bureau
                            </button>
                            <button
                                type="button"
                                onClick={() => setPreviewMode('mobile')}
                                className={`rounded-pill px-3 py-1 text-xs uppercase ${previewMode === 'mobile' ? 'bg-ink text-bg' : 'text-ink-soft'}`}
                            >
                                Mobile
                            </button>
                        </div>
                    </div>

                    <div className={`rounded-card border border-line p-4 ${previewMode === 'mobile' ? 'mx-auto max-w-[320px]' : ''}`}>
                        {fields.map((field) => {
                            const state = visibility[field.key];

                            if (!state?.visible) {
                                return null;
                            }

                            return (
                                <PreviewField
                                    key={field._localId}
                                    field={field}
                                    required={state.required}
                                    value={simulatedAnswers[field.key]}
                                    onChange={(value) => setSimulatedAnswers((prev) => ({ ...prev, [field.key]: value }))}
                                />
                            );
                        })}
                        {fields.length === 0 && <p className="text-sm text-ink-soft">Ajoute des champs pour voir l'aperçu.</p>}
                    </div>
                </div>
            </div>

            {!form && (
                <div className="mt-10">
                    <Button type="button" onClick={handleCreate}>
                        Créer le formulaire
                    </Button>
                </div>
            )}
        </OrganizerLayout>
    );
}

function AddFieldMenu({ fieldTypes, onAdd }: { fieldTypes: FieldTypeOption[]; onAdd: (type: string) => void }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="font-label text-xs tracking-[0.1em] text-accent uppercase hover:opacity-80"
            >
                + Ajouter un champ
            </button>
            {open && (
                <div className="absolute right-0 z-10 mt-2 w-56 rounded-card border border-line bg-bg p-2 shadow-lg">
                    {fieldTypes.map((type) => (
                        <button
                            key={type.value}
                            type="button"
                            onClick={() => {
                                onAdd(type.value);
                                setOpen(false);
                            }}
                            className="block w-full rounded-control px-3 py-2 text-left text-sm text-ink hover:bg-bg-alt"
                        >
                            {type.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function FieldProperties({ field, onChange }: { field: LocalField; onChange: (patch: Partial<FieldData>) => void }) {
    const config = field.config ?? {};

    function setConfig(patch: Record<string, unknown>) {
        onChange({ config: { ...config, ...patch } });
    }

    return (
        <div className="space-y-5">
            <div>
                <InputLabel htmlFor="field_key">Identifiant technique</InputLabel>
                <TextInput id="field_key" type="text" value={field.key} onChange={(e) => onChange({ key: e.target.value })} />
            </div>

            <div>
                <InputLabel htmlFor="field_label">Libellé</InputLabel>
                <TextInput id="field_label" type="text" value={field.label} onChange={(e) => onChange({ label: e.target.value })} />
            </div>

            <div>
                <InputLabel htmlFor="field_help">Texte d'aide (optionnel)</InputLabel>
                <Textarea id="field_help" value={field.help_text ?? ''} onChange={(e) => onChange({ help_text: e.target.value || null })} />
            </div>

            {field.type !== 'informational_text' && (
                <label className="flex items-center gap-2">
                    <Checkbox checked={field.is_required} onChange={(e) => onChange({ is_required: e.target.checked })} />
                    <span className="text-sm text-ink">Obligatoire</span>
                </label>
            )}

            {(field.type === 'short_text' || field.type === 'long_text') && (
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="max_length">Longueur max</InputLabel>
                        <TextInput
                            id="max_length"
                            type="number"
                            value={(config.max_length as number | undefined) ?? ''}
                            onChange={(e) => setConfig({ max_length: e.target.value ? Number(e.target.value) : undefined })}
                        />
                    </div>
                </div>
            )}

            {field.type === 'number' && (
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="min">Minimum</InputLabel>
                        <TextInput
                            id="min"
                            type="number"
                            value={(config.min as number | undefined) ?? ''}
                            onChange={(e) => setConfig({ min: e.target.value ? Number(e.target.value) : undefined })}
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="max">Maximum</InputLabel>
                        <TextInput
                            id="max"
                            type="number"
                            value={(config.max as number | undefined) ?? ''}
                            onChange={(e) => setConfig({ max: e.target.value ? Number(e.target.value) : undefined })}
                        />
                    </div>
                </div>
            )}

            {field.type === 'phone' && (
                <div>
                    <InputLabel htmlFor="default_country">Indicatif par défaut</InputLabel>
                    <Select
                        id="default_country"
                        value={(config.default_country as string | undefined) ?? ''}
                        onChange={(e) => setConfig({ default_country: e.target.value || undefined })}
                    >
                        <option value="">Aucun</option>
                        <option value="CD">RDC (+243)</option>
                        <option value="CG">Congo (+242)</option>
                        <option value="CM">Cameroun (+237)</option>
                        <option value="CI">Côte d'Ivoire (+225)</option>
                        <option value="SN">Sénégal (+221)</option>
                        <option value="FR">France (+33)</option>
                        <option value="BE">Belgique (+32)</option>
                    </Select>
                </div>
            )}

            {field.type === 'consent' && (
                <div>
                    <InputLabel htmlFor="legal_text">Texte légal</InputLabel>
                    <Textarea
                        id="legal_text"
                        value={(config.legal_text as string | undefined) ?? ''}
                        onChange={(e) => setConfig({ legal_text: e.target.value || undefined })}
                    />
                </div>
            )}

            {TYPES_WITH_OPTIONS.includes(field.type) && (
                <OptionsEditor
                    options={field.options}
                    onChange={(options) => onChange({ options })}
                    withQuota={field.type !== 'multiple_choice' || true}
                />
            )}
        </div>
    );
}

function OptionsEditor({
    options,
    onChange,
    withQuota,
}: {
    options: FieldOptionData[];
    onChange: (options: FieldOptionData[]) => void;
    withQuota: boolean;
}) {
    function updateOption(index: number, patch: Partial<FieldOptionData>) {
        onChange(options.map((o, i) => (i === index ? { ...o, ...patch } : o)));
    }

    function addOption() {
        onChange([...options, { value: '', label: '', quota: null }]);
    }

    function removeOption(index: number) {
        onChange(options.filter((_, i) => i !== index));
    }

    return (
        <div>
            <InputLabel>Options</InputLabel>
            <div className="space-y-3">
                {options.map((option, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <TextInput
                            type="text"
                            placeholder="Libellé"
                            value={option.label}
                            onChange={(e) => updateOption(index, { label: e.target.value })}
                        />
                        {withQuota && (
                            <TextInput
                                type="number"
                                placeholder="Quota"
                                className="w-24"
                                value={option.quota ?? ''}
                                onChange={(e) => updateOption(index, { quota: e.target.value ? Number(e.target.value) : null })}
                            />
                        )}
                        <button type="button" onClick={() => removeOption(index)} className="px-1 text-danger hover:opacity-70">
                            ×
                        </button>
                    </div>
                ))}
            </div>
            <button type="button" onClick={addOption} className="mt-2 font-label text-xs tracking-[0.1em] text-accent uppercase hover:opacity-80">
                + Ajouter une option
            </button>
        </div>
    );
}

function RuleEditor({
    rule,
    fields,
    onChange,
    onChangeCondition,
    onRemove,
}: {
    rule: RuleData;
    fields: LocalField[];
    onChange: (patch: Partial<RuleData>) => void;
    onChangeCondition: (patch: Partial<RuleConditionData>) => void;
    onRemove: () => void;
}) {
    const needsValue = rule.condition.operator !== 'is_empty' && rule.condition.operator !== 'is_not_empty';

    return (
        <li className="rounded-card border border-line p-4">
            <div className="mb-3 flex items-center justify-between">
                <p className="font-sans text-sm text-ink-soft">
                    Si <SelectInline value={rule.condition.field_key} onChange={(v) => onChangeCondition({ field_key: v })} fields={fields} />{' '}
                    <SelectInline value={rule.condition.operator} onChange={(v) => onChangeCondition({ operator: v })} options={OPERATORS} />
                    {needsValue && (
                        <input
                            type="text"
                            value={rule.condition.value}
                            onChange={(e) => onChangeCondition({ value: e.target.value })}
                            className="ml-1 w-24 border-0 border-b border-line bg-transparent px-1 text-sm text-ink focus:border-accent focus:ring-0 focus:outline-none"
                        />
                    )}
                    , alors <SelectInline value={rule.action} onChange={(v) => onChange({ action: v })} options={ACTIONS} />{' '}
                    <SelectInline value={rule.target_field_key} onChange={(v) => onChange({ target_field_key: v })} fields={fields} />
                </p>
                <button type="button" onClick={onRemove} className="shrink-0 px-1 text-danger hover:opacity-70">
                    ×
                </button>
            </div>
        </li>
    );
}

function SelectInline({
    value,
    onChange,
    options,
    fields,
}: {
    value: string;
    onChange: (value: string) => void;
    options?: { value: string; label: string }[];
    fields?: LocalField[];
}) {
    const items = options ?? (fields ?? []).map((f) => ({ value: f.key, label: f.label || f.key }));

    return (
        <select
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className="border-0 border-b border-line bg-transparent px-1 text-sm text-ink focus:border-accent focus:ring-0 focus:outline-none"
        >
            {items.map((item) => (
                <option key={item.value} value={item.value}>
                    {item.label}
                </option>
            ))}
        </select>
    );
}

function PreviewField({
    field,
    required,
    value,
    onChange,
}: {
    field: LocalField;
    required: boolean;
    value: unknown;
    onChange: (value: unknown) => void;
}) {
    return (
        <div className="mb-5">
            {field.type !== 'informational_text' && (
                <InputLabel>
                    {field.label}
                    {required ? ' *' : ''}
                </InputLabel>
            )}

            {field.help_text && <p className="mb-2 text-xs text-ink-soft">{field.help_text}</p>}

            {(() => {
                switch (field.type) {
                    case 'informational_text':
                        return <p className="text-sm text-ink-soft">{field.label}</p>;
                    case 'long_text':
                        return <Textarea value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;
                    case 'number':
                        return <TextInput type="number" value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;
                    case 'email':
                        return <TextInput type="email" value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;
                    case 'phone':
                        return <TextInput type="tel" value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;
                    case 'date':
                        return <TextInput type="date" value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;
                    case 'yes_no':
                        return (
                            <Select value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)}>
                                <option value="">—</option>
                                <option value="true">Oui</option>
                                <option value="false">Non</option>
                            </Select>
                        );
                    case 'consent':
                        return (
                            <label className="flex items-center gap-2">
                                <Checkbox checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} />
                                <span className="text-sm text-ink-soft">{(field.config.legal_text as string) || "J'accepte"}</span>
                            </label>
                        );
                    case 'single_choice':
                    case 'meal_choice':
                        return (
                            <Select value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)}>
                                <option value="">—</option>
                                {field.options.map((option, i) => (
                                    <option key={i} value={option.label}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        );
                    case 'multiple_choice':
                        return (
                            <div className="space-y-1">
                                {field.options.map((option, i) => {
                                    const selected = Array.isArray(value) ? (value as string[]) : [];

                                    return (
                                        <label key={i} className="flex items-center gap-2">
                                            <Checkbox
                                                checked={selected.includes(option.label)}
                                                onChange={(e) => {
                                                    const next = e.target.checked
                                                        ? [...selected, option.label]
                                                        : selected.filter((v) => v !== option.label);
                                                    onChange(next);
                                                }}
                                            />
                                            <span className="text-sm text-ink">{option.label}</span>
                                        </label>
                                    );
                                })}
                            </div>
                        );
                    default:
                        return <TextInput type="text" value={(value as string) ?? ''} onChange={(e) => onChange(e.target.value)} />;
                }
            })()}
        </div>
    );
}
