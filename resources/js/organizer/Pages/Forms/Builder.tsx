import { type RequestPayload } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type DragEvent, type ReactNode, useEffect, useRef, useState } from 'react';
import Badge from '../../Components/Badge';
import BuilderIcon from '../../Components/FormBuilder/BuilderIcon';
import Canvas from '../../Components/FormBuilder/Canvas';
import ImageUploadModal from '../../Components/FormBuilder/ImageUploadModal';
import {
    blueprintForType,
    buildPayload,
    createField,
    donationBlueprint,
    duplicateField,
    MOVE_DRAG_TYPE,
    normalizeSettings,
    PALETTE,
    PALETTE_DRAG_TYPE,
    type PaletteEntry,
    resetTheme,
    subEventsBlueprint,
    toBuilderField,
    uniqueKey,
} from '../../Components/FormBuilder/logic';
import Palette from '../../Components/FormBuilder/Palette';
import QuestionDrawer from '../../Components/FormBuilder/QuestionDrawer';
import QuestionTypeModal from '../../Components/FormBuilder/QuestionTypeModal';
import ScreenDrawer from '../../Components/FormBuilder/ScreenDrawer';
import ThemeDrawer from '../../Components/FormBuilder/ThemeDrawer';
import {
    type BuilderField,
    type CurrencyOption,
    type FieldTypeOption,
    type FontOption,
    type FormPayload,
    type FormSettings,
    type ImageKind,
    type PreviewDevice,
    type RuleData,
    type ScreenKey,
    type Selection,
    type Simulation,
    type SubEventOption,
    type TagOption,
    type ThemeSettings,
} from '../../Components/FormBuilder/types';
import EventLayout from '../../Layouts/EventLayout';
import { type SharedProps } from '../../types';

interface BuilderPageProps {
    event: { id: number; title: string; phoneRequired: boolean };
    form: FormPayload | null;
    fieldTypes: FieldTypeOption[];
    fonts: FontOption[];
    tags: TagOption[];
    isFreePlan: boolean;
    defaultSettings: FormSettings;
    subEvents: SubEventOption[];
    subEventsUrl: string;
    donationCurrencies: CurrencyOption[];
    defaultDonationCurrency: string;
}

interface Notice {
    message: string;
    undo?: () => void;
    link?: { href: string; label: string };
}

const AUTOSAVE_DELAY_MS = 1200;

export default function FormBuilder({
    event,
    form,
    fieldTypes,
    fonts,
    tags,
    isFreePlan,
    defaultSettings,
    subEvents,
    subEventsUrl,
    donationCurrencies,
    defaultDonationCurrency,
}: BuilderPageProps) {
    const { errors: pageErrors, settingsAccess } = usePage<SharedProps & { errors: Record<string, string> }>().props;

    const [name, setName] = useState(form?.name ?? `Inscription — ${event.title}`);
    const [fields, setFields] = useState<BuilderField[]>(() => (form?.fields ?? []).map(toBuilderField));
    const [rules, setRules] = useState<RuleData[]>(form?.rules ?? []);
    const [settings, setSettings] = useState<FormSettings>(() => normalizeSettings(form?.settings ?? defaultSettings));
    const [selection, setSelection] = useState<Selection>(null);
    const [simulationChoice, setSimulationChoice] = useState<Simulation>('attending');
    const [answers, setAnswers] = useState<Record<string, unknown>>({});
    const [device, setDevice] = useState<PreviewDevice>('desktop');
    const [dragging, setDragging] = useState(false);
    const [typeModalAt, setTypeModalAt] = useState<number | null>(null);
    const [imageKind, setImageKind] = useState<ImageKind | null>(null);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [revision, setRevision] = useState(0);
    const [savedRevision, setSavedRevision] = useState(0);
    const [saving, setSaving] = useState(false);
    const [saveErrors, setSaveErrors] = useState<Record<string, string>>({});

    const revisionRef = useRef(0);
    const formIdRef = useRef<number | null>(form?.id ?? null);
    const payloadRef = useRef<RequestPayload>({});
    const inFlightRef = useRef(false);
    const queuedRef = useRef(false);
    const selfVisitRef = useRef(false);
    // Blocs tout juste ajoutés : leur clé suit le texte de la question au
    // premier « Enregistrer », avant qu'une réponse ne puisse s'y rattacher.
    const freshUidsRef = useRef<Set<string>>(new Set());

    const formId = form?.id ?? null;
    const dirty = revision !== savedRevision;
    const premiumTypes = new Set(fieldTypes.filter((type) => type.premium).map((type) => type.value));
    const hasPremiumField = fields.some((field) => premiumTypes.has(field.type));
    const simulation: Simulation = settings.rsvp.decline_enabled ? simulationChoice : 'attending';

    useEffect(() => {
        payloadRef.current = buildPayload(name, fields, rules, settings);
    }, [name, fields, rules, settings]);

    // Enregistrement automatique : chaque modification repousse l'envoi.
    useEffect(() => {
        if (revision === savedRevision) {
            return;
        }

        const timer = window.setTimeout(persist, AUTOSAVE_DELAY_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [revision]);

    useEffect(() => {
        if (notice === null) {
            return;
        }

        const timer = window.setTimeout(() => setNotice(null), 8000);

        return () => window.clearTimeout(timer);
    }, [notice]);

    useEffect(() => {
        function handleBeforeUnload(beforeUnload: BeforeUnloadEvent) {
            if (dirty) {
                beforeUnload.preventDefault();
            }
        }

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [dirty]);

    // Les visites lancées par le constructeur lui-même (enregistrer, publier)
    // ne doivent jamais déclencher la confirmation de sortie.
    useEffect(
        () =>
            router.on('before', (visitEvent) => {
                if (selfVisitRef.current || !dirty) {
                    return;
                }

                if (!window.confirm('Des modifications ne sont pas encore enregistrées. Quitter quand même ?')) {
                    visitEvent.preventDefault();
                }
            }),
        [dirty],
    );

    function touch() {
        revisionRef.current += 1;
        setRevision(revisionRef.current);
    }

    function persist() {
        if (inFlightRef.current) {
            queuedRef.current = true;

            return;
        }

        const sentRevision = revisionRef.current;
        const targetFormId = formIdRef.current;
        inFlightRef.current = true;
        selfVisitRef.current = true;
        setSaving(true);

        // Sans formulaire, la première modification le crée ; la redirection
        // vers sa page d'édition garde l'état local du constructeur.
        router.visit(targetFormId === null ? `/events/${event.id}/form` : `/forms/${targetFormId}`, {
            method: targetFormId === null ? 'post' : 'patch',
            data: payloadRef.current,
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const saved = page.props.form as FormPayload | null | undefined;
                formIdRef.current = saved?.id ?? formIdRef.current;
                setSavedRevision(sentRevision);
                setSaveErrors({});
            },
            onError: (errors) => setSaveErrors(errors as Record<string, string>),
            onFinish: () => {
                inFlightRef.current = false;
                selfVisitRef.current = false;
                setSaving(false);

                if (queuedRef.current) {
                    queuedRef.current = false;
                    persist();
                }
            },
        });
    }

    function publish() {
        if (formId === null || dirty || saving) {
            return;
        }

        selfVisitRef.current = true;
        router.post(
            `/forms/${formId}/publish`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    selfVisitRef.current = false;
                },
            },
        );
    }

    function insertField(field: BuilderField, index: number) {
        setFields((previous) => {
            const next = [...previous];
            next.splice(Math.min(index, next.length), 0, field);

            return next;
        });
        freshUidsRef.current.add(field.uid);
        setSelection({ kind: 'field', uid: field.uid });
        touch();

        if (isFreePlan && premiumTypes.has(field.type)) {
            setNotice({ message: 'Question réservée aux plans payants : essayez-la librement, la publication demandera un plan payant.' });
        }
    }

    function openScreen(screen: ScreenKey) {
        if (screen === 'welcome' && !settings.welcome.enabled) {
            updateSettings({ welcome: { ...settings.welcome, enabled: true } });
        }

        if (screen === 'decline_screen' && !settings.rsvp.decline_enabled) {
            updateSettings({ rsvp: { ...settings.rsvp, decline_enabled: true } });
        }

        setSelection({ kind: 'screen', screen });
    }

    function activatePalette(entry: PaletteEntry, index: number) {
        const action = entry.action;

        switch (action.kind) {
            case 'field':
                insertField(createField(action.blueprint, fields), index);
                break;
            case 'custom':
                setTypeModalAt(index);
                break;
            case 'subEvents':
                if (subEvents.length === 0) {
                    setNotice({
                        message: "Créez d'abord vos événements secondaires, puis ajoutez ce bloc.",
                        link: { href: subEventsUrl, label: 'Créer des sessions' },
                    });
                    break;
                }

                insertField(createField(subEventsBlueprint(subEvents), fields), index);
                break;
            case 'donation':
                insertField(createField(donationBlueprint(defaultDonationCurrency), fields), index);
                break;
            case 'screen':
                openScreen(action.screen);
                break;
            case 'soon':
                setNotice({ message: `« ${entry.label} » arrive bientôt dans Itaza.` });
                break;
        }
    }

    function dropAt(index: number, dropEvent: DragEvent<HTMLDivElement>) {
        setDragging(false);
        const paletteId = dropEvent.dataTransfer.getData(PALETTE_DRAG_TYPE);
        const movedUid = dropEvent.dataTransfer.getData(MOVE_DRAG_TYPE);

        if (paletteId) {
            const entry = PALETTE.find((item) => item.id === paletteId);

            if (entry) {
                activatePalette(entry, index);
            }

            return;
        }

        if (movedUid) {
            setFields((previous) => {
                const from = previous.findIndex((field) => field.uid === movedUid);

                if (from === -1) {
                    return previous;
                }

                const next = [...previous];
                const [moved] = next.splice(from, 1);
                next.splice(from < index ? index - 1 : index, 0, moved);

                return next;
            });
            touch();
        }
    }

    function moveField(uid: string, direction: -1 | 1) {
        const index = fields.findIndex((field) => field.uid === uid);
        const target = index + direction;

        if (index === -1 || target < 0 || target >= fields.length) {
            return;
        }

        setFields((previous) => {
            const next = [...previous];
            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
        touch();
    }

    function duplicate(uid: string) {
        const index = fields.findIndex((field) => field.uid === uid);

        if (index !== -1) {
            insertField(duplicateField(fields[index], fields), index + 1);
        }
    }

    function removeField(uid: string) {
        const index = fields.findIndex((field) => field.uid === uid);
        const removed = fields[index];

        if (!removed) {
            return;
        }

        const removedRules = rules.filter((rule) => rule.target_field_key === removed.key || rule.condition.field_key === removed.key);

        setFields((previous) => previous.filter((field) => field.uid !== uid));
        setRules((previous) => previous.filter((rule) => !removedRules.includes(rule)));

        if (selection?.kind === 'field' && selection.uid === uid) {
            setSelection(null);
        }

        touch();
        setNotice({
            message: `« ${removed.label || 'Question'} » a été retirée du formulaire.`,
            undo: () => {
                setFields((previous) => {
                    const next = [...previous];
                    next.splice(Math.min(index, next.length), 0, removed);

                    return next;
                });
                setRules((previous) => [...previous, ...removedRules]);
                setNotice(null);
                touch();
            },
        });
    }

    function saveField(updated: BuilderField, rule: RuleData | null) {
        let saved = updated;

        if (freshUidsRef.current.has(updated.uid)) {
            freshUidsRef.current.delete(updated.uid);
            saved = { ...updated, key: uniqueKey(updated.label, fields.filter((field) => field.uid !== updated.uid)) };
        }

        const savedRule = rule ? { ...rule, target_field_key: saved.key } : null;

        setFields((previous) => previous.map((field) => (field.uid === saved.uid ? saved : field)));
        setRules((previous) => [
            ...previous
                .filter((item) => item.target_field_key !== updated.key)
                .map((item) =>
                    item.condition.field_key === updated.key ? { ...item, condition: { ...item.condition, field_key: saved.key } } : item,
                ),
            ...(savedRule ? [savedRule] : []),
        ]);
        setSelection(null);
        touch();
    }

    function updateSettings(patch: Partial<FormSettings>) {
        setSettings((previous) => ({ ...previous, ...patch }));
        touch();
    }

    function updateTheme(patch: Partial<ThemeSettings>) {
        setSettings((previous) => ({ ...previous, theme: { ...previous.theme, ...patch } }));
        touch();
    }

    // L'image est déjà enregistrée par son envoi : seul l'aperçu change.
    function setImageUrl(kind: ImageKind, url: string | null) {
        setSettings((previous) => ({
            ...previous,
            theme: kind === 'logo' ? { ...previous.theme, logo_url: url } : { ...previous.theme, background_image_url: url },
        }));
    }

    function fieldErrors(index: number): Record<string, string> {
        const prefix = `fields.${index}.`;
        const result: Record<string, string> = {};

        Object.entries(saveErrors).forEach(([key, message]) => {
            if (key.startsWith(prefix)) {
                result[key.slice(prefix.length)] = message;
            } else if (key === 'rules') {
                result.rules = message;
            }
        });

        return result;
    }

    const selectedField = selection?.kind === 'field' ? (fields.find((field) => field.uid === selection.uid) ?? null) : null;
    const firstSaveError = Object.values(saveErrors)[0];

    let statusLabel = 'Non enregistré';

    if (form?.status === 'published') {
        statusLabel = 'Publié';
    } else if (form?.has_published_version) {
        statusLabel = 'Modifications non publiées';
    } else if (form) {
        statusLabel = 'Brouillon';
    }

    let publishLabel = 'Publier le formulaire';

    if (form?.status === 'published' && !dirty) {
        publishLabel = 'Formulaire publié';
    } else if (form?.has_published_version) {
        publishLabel = 'Publier les modifications';
    }

    let saveLabel = 'Tout est enregistré';

    if (saving) {
        saveLabel = 'Enregistrement…';
    } else if (dirty) {
        saveLabel = 'Modifications en attente…';
    } else if (form === null) {
        saveLabel = 'Votre première modification créera le formulaire.';
    }

    let panel: ReactNode = <Palette onActivate={(entry) => activatePalette(entry, fields.length)} onDragStateChange={setDragging} />;

    if (selectedField) {
        panel = (
            <QuestionDrawer
                key={selectedField.uid}
                field={selectedField}
                fields={fields}
                rule={rules.find((rule) => rule.target_field_key === selectedField.key) ?? null}
                fieldTypes={fieldTypes}
                tags={tags}
                declineEnabled={settings.rsvp.decline_enabled}
                isFreePlan={isFreePlan}
                errors={fieldErrors(fields.indexOf(selectedField))}
                freshKey={freshUidsRef.current.has(selectedField.uid)}
                companionsEnabled={settings.rsvp.max_companions > 0}
                subEvents={subEvents}
                subEventsUrl={subEventsUrl}
                donationCurrencies={donationCurrencies}
                onSave={saveField}
                onCancel={() => {
                    freshUidsRef.current.delete(selectedField.uid);
                    setSelection(null);
                }}
                onRemove={() => removeField(selectedField.uid)}
            />
        );
    } else if (selection?.kind === 'screen') {
        panel = (
            <ScreenDrawer
                key={selection.screen}
                screen={selection.screen}
                settings={settings}
                eventTitle={event.title}
                onSave={(next) => {
                    setSettings((previous) => ({ ...next, theme: previous.theme }));
                    setSelection(null);
                    touch();
                }}
                onCancel={() => setSelection(null)}
            />
        );
    } else if (selection?.kind === 'theme') {
        panel = (
            <ThemeDrawer
                theme={settings.theme}
                fonts={fonts}
                onChange={updateTheme}
                onReset={() => updateTheme(resetTheme(settings.theme))}
                onClose={() => setSelection(null)}
                onPickImage={setImageKind}
            />
        );
    }

    return (
        <EventLayout title="Formulaire d'inscription" wide>
            <Head title={`Formulaire d'inscription — ${event.title}`} />

            <div className="mb-6 flex flex-wrap items-center gap-x-4 gap-y-3">
                <Badge variant={form?.status === 'published' ? 'success' : 'neutral'}>{statusLabel}</Badge>
                <span role="status" className="text-sm text-ink-soft">
                    {saveLabel}
                </span>
                <label className="flex min-w-0 items-center gap-2 text-sm text-ink-soft">
                    <span className="shrink-0">Nom interne :</span>
                    <input
                        type="text"
                        value={name}
                        maxLength={255}
                        onChange={(changeEvent) => {
                            setName(changeEvent.target.value);
                            touch();
                        }}
                        className="w-56 min-w-0 border-0 border-b border-line bg-transparent px-1 py-1 text-sm text-ink focus:border-accent focus:outline-none"
                    />
                </label>
                <button
                    type="button"
                    onClick={publish}
                    disabled={formId === null || dirty || saving || form?.status === 'published'}
                    className="ml-auto inline-flex min-h-10 items-center gap-2 rounded-pill bg-ink px-6 py-2 text-sm font-medium text-bg transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <BuilderIcon name="upload" className="h-4 w-4" />
                    {publishLabel}
                </button>
            </div>

            {pageErrors.publish && (
                <div role="alert" className="mb-5 rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">
                    {pageErrors.publish}{' '}
                    {settingsAccess.billing && (
                        <Link href="/billing" className="font-medium underline">
                            Voir les plans
                        </Link>
                    )}
                </div>
            )}

            {firstSaveError && (
                <div role="alert" className="mb-5 rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">
                    L'enregistrement a été refusé : {firstSaveError}
                </div>
            )}

            {isFreePlan && hasPremiumField && (
                <div className="mb-5 flex flex-wrap items-center gap-2 rounded-card bg-[#fff1cc] p-4 text-sm text-[#7a4f00]">
                    <BuilderIcon name="star" className="h-4 w-4" />
                    Ce formulaire contient des questions réservées aux plans payants : vous pouvez les essayer, mais sa publication demandera un plan payant.
                    {settingsAccess.billing && (
                        <Link href="/billing" className="font-medium underline">
                            Voir les plans
                        </Link>
                    )}
                </div>
            )}

            {notice && (
                <div role="status" className="fixed right-6 bottom-6 z-40 flex max-w-md items-center gap-4 rounded-card bg-ink px-5 py-4 text-sm text-bg shadow-xl">
                    <span className="min-w-0 flex-1">{notice.message}</span>
                    {notice.link && (
                        <a href={notice.link.href} className="shrink-0 font-medium underline">
                            {notice.link.label}
                        </a>
                    )}
                    {notice.undo && (
                        <button type="button" onClick={notice.undo} className="shrink-0 font-medium underline">
                            Annuler
                        </button>
                    )}
                    <button type="button" onClick={() => setNotice(null)} aria-label="Fermer le message" className="shrink-0 opacity-70 hover:opacity-100">
                        <BuilderIcon name="close" className="h-4 w-4" />
                    </button>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[21rem_minmax(0,1fr)] lg:items-start">
                <aside className="space-y-4 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:pb-4">
                    <button
                        type="button"
                        onClick={() => setSelection({ kind: 'theme' })}
                        aria-pressed={selection?.kind === 'theme'}
                        className="flex w-full items-center gap-2 rounded-pill border border-line bg-bg px-4 py-2.5 text-sm font-medium text-ink transition hover:border-ink"
                    >
                        <BuilderIcon name="palette" className="h-4 w-4" />
                        Modifier le thème du formulaire
                    </button>
                    {panel}
                </aside>

                <Canvas
                    eventTitle={event.title}
                    phoneRequired={event.phoneRequired}
                    fields={fields}
                    rules={rules}
                    settings={settings}
                    fonts={fonts}
                    fieldTypes={fieldTypes}
                    tags={tags}
                    selection={selection}
                    simulation={simulation}
                    answers={answers}
                    device={device}
                    dragging={dragging}
                    onSelect={setSelection}
                    onSimulationChange={setSimulationChoice}
                    onAnswerChange={(key, value) => setAnswers((previous) => ({ ...previous, [key]: value }))}
                    onDeviceChange={setDevice}
                    onToggleWelcome={(enabled) => updateSettings({ welcome: { ...settings.welcome, enabled } })}
                    onToggleDecline={(enabled) => updateSettings({ rsvp: { ...settings.rsvp, decline_enabled: enabled } })}
                    onLogoClick={() => setImageKind('logo')}
                    onDuplicate={duplicate}
                    onMove={moveField}
                    onRemove={removeField}
                    onDropAt={dropAt}
                    onDragStateChange={setDragging}
                />
            </div>

            <QuestionTypeModal
                open={typeModalAt !== null}
                fieldTypes={fieldTypes}
                isFreePlan={isFreePlan}
                onClose={() => setTypeModalAt(null)}
                onPick={(type) => {
                    insertField(createField(blueprintForType(type.value), fields), typeModalAt ?? fields.length);
                    setTypeModalAt(null);
                }}
            />

            <ImageUploadModal
                key={imageKind ?? 'ferme'}
                open={imageKind !== null}
                kind={imageKind ?? 'logo'}
                formId={formId}
                currentUrl={imageKind === 'background' ? settings.theme.background_image_url : settings.theme.logo_url}
                onClose={() => setImageKind(null)}
                onChange={(url) => setImageUrl(imageKind ?? 'logo', url)}
            />
        </EventLayout>
    );
}
