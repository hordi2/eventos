import { type DragEvent, Fragment } from 'react';
import BuilderIcon from './BuilderIcon';
import DropZone from './DropZone';
import IdentityBlock from './IdentityBlock';
import { computeVisibility, previewTheme } from './logic';
import HeaderSlot from './HeaderSlot';
import LogoSlot from './LogoSlot';
import QuestionBlock from './QuestionBlock';
import ScreenBlock from './ScreenBlock';
import {
    type BuilderField,
    type FieldTypeOption,
    type FontOption,
    type FormSettings,
    type PreviewDevice,
    type RuleData,
    type ScreenKey,
    type Selection,
    type Simulation,
    type TagOption,
} from './types';

interface CanvasProps {
    eventTitle: string;
    phoneRequired: boolean;
    fields: BuilderField[];
    rules: RuleData[];
    settings: FormSettings;
    fonts: FontOption[];
    fieldTypes: FieldTypeOption[];
    tags: TagOption[];
    selection: Selection;
    simulation: Simulation;
    answers: Record<string, unknown>;
    device: PreviewDevice;
    dragging: boolean;
    onSelect: (selection: Selection) => void;
    onSimulationChange: (simulation: Simulation) => void;
    onAnswerChange: (key: string, value: unknown) => void;
    onDeviceChange: (device: PreviewDevice) => void;
    onToggleWelcome: (enabled: boolean) => void;
    onToggleDecline: (enabled: boolean) => void;
    onLogoClick: () => void;
    onHeaderClick: () => void;
    onDuplicate: (uid: string) => void;
    onMove: (uid: string, direction: -1 | 1) => void;
    onRemove: (uid: string) => void;
    onDropAt: (index: number, event: DragEvent<HTMLDivElement>) => void;
    onDragStateChange: (dragging: boolean) => void;
}

const DEVICES: { value: PreviewDevice; label: string }[] = [
    { value: 'desktop', label: 'Ordinateur' },
    { value: 'mobile', label: 'Mobile' },
];

/**
 * Aperçu du parcours invité, dans l'ordre où l'invité le parcourt : logo,
 * bienvenue, coordonnées, questions, puis écrans de fin.
 */
export default function Canvas(props: CanvasProps) {
    const { eventTitle, fields, rules, settings, fonts, fieldTypes, tags, selection, simulation, answers, device, dragging } = props;
    const theme = previewTheme(settings.theme, fonts);
    const visibility = computeVisibility(fields, rules, answers, simulation);

    function isScreen(screen: ScreenKey): boolean {
        return selection?.kind === 'screen' && selection.screen === screen;
    }

    function openScreen(screen: ScreenKey) {
        props.onSelect({ kind: 'screen', screen });
    }

    return (
        <div className="min-w-0">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-ink-soft">Aperçu du parcours de vos invités</p>
                <div role="group" aria-label="Taille de l'aperçu" className="flex rounded-pill bg-bg p-1 ring-1 ring-line">
                    {DEVICES.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            aria-pressed={device === item.value}
                            onClick={() => props.onDeviceChange(item.value)}
                            className={`flex items-center gap-1.5 rounded-pill px-3 py-1.5 text-xs font-medium transition ${
                                device === item.value ? 'bg-ink text-bg' : 'text-ink-soft hover:text-ink'
                            }`}
                        >
                            <BuilderIcon name={item.value} className="h-4 w-4" />
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="overflow-hidden rounded-card ring-1 ring-line" style={theme.page}>
                <div className={`mx-auto space-y-5 px-4 py-8 sm:py-10 sm:pr-8 sm:pl-16 ${device === 'mobile' ? 'max-w-[460px]' : 'max-w-3xl'}`}>
                    <HeaderSlot headerUrl={settings.theme.header_image_url} onClick={props.onHeaderClick} />
                    <LogoSlot logoUrl={settings.theme.logo_url} onClick={props.onLogoClick} />

                    <ScreenBlock
                        title="Message de bienvenue"
                        icon="welcome"
                        selected={isScreen('welcome')}
                        onOpen={() => openScreen('welcome')}
                        toggle={{ label: 'Afficher', checked: settings.welcome.enabled, onChange: props.onToggleWelcome }}
                        muted={!settings.welcome.enabled}
                        mutedNote="Désactivé : le parcours commence directement par les coordonnées."
                    >
                        <div className="text-center">
                            <p className="mb-2 text-sm opacity-70">{eventTitle}</p>
                            <h2 className="mb-4 text-3xl" style={theme.heading}>
                                {settings.welcome.title || eventTitle}
                            </h2>
                            {settings.welcome.message && <p className="mb-6 whitespace-pre-line opacity-80">{settings.welcome.message}</p>}
                            <span className="inline-flex min-h-11 items-center rounded-pill px-10 py-3 font-medium" style={theme.button}>
                                {settings.welcome.button_label || 'Commencer'}
                            </span>
                        </div>
                    </ScreenBlock>

                    <IdentityBlock
                        eventTitle={eventTitle}
                        rsvp={settings.rsvp}
                        phoneRequired={props.phoneRequired}
                        simulation={simulation}
                        selected={isScreen('rsvp')}
                        theme={theme}
                        onOpen={() => openScreen('rsvp')}
                        onSimulationChange={props.onSimulationChange}
                    />

                    <div>
                        <h2 className="mb-2 text-2xl" style={theme.heading}>
                            Vos réponses
                        </h2>
                        {fields.length === 0 ? (
                            <DropZone active emptyHint onDrop={(event) => props.onDropAt(0, event)} />
                        ) : (
                            <DropZone active={dragging} onDrop={(event) => props.onDropAt(0, event)} />
                        )}
                        {fields.map((field, index) => {
                            const type = fieldTypes.find((item) => item.value === field.type);
                            const tagIds = field.config.tag_ids ?? [];

                            return (
                                <Fragment key={field.uid}>
                                    <QuestionBlock
                                        field={field}
                                        position={index}
                                        total={fields.length}
                                        state={visibility[field.key] ?? { visible: true, required: field.is_required }}
                                        selected={selection?.kind === 'field' && selection.uid === field.uid}
                                        premium={type?.premium ?? false}
                                        typeLabel={type?.label ?? field.type}
                                        tagNames={tags.filter((tag) => tagIds.includes(tag.id)).map((tag) => tag.name)}
                                        hasRule={rules.some((rule) => rule.target_field_key === field.key)}
                                        declineEnabled={settings.rsvp.decline_enabled}
                                        companionsEnabled={settings.rsvp.max_companions > 0}
                                        value={answers[field.key]}
                                        onValueChange={(value) => props.onAnswerChange(field.key, value)}
                                        onOpen={() => props.onSelect({ kind: 'field', uid: field.uid })}
                                        onDuplicate={() => props.onDuplicate(field.uid)}
                                        onMove={(direction) => props.onMove(field.uid, direction)}
                                        onRemove={() => props.onRemove(field.uid)}
                                        onDragStateChange={props.onDragStateChange}
                                    />
                                    <DropZone active={dragging} onDrop={(event) => props.onDropAt(index + 1, event)} />
                                </Fragment>
                            );
                        })}
                        <span className="mt-2 flex min-h-11 w-full items-center justify-center rounded-pill px-8 py-3 font-medium" style={theme.button}>
                            {simulation === 'attending' ? 'Confirmer mon inscription' : 'Envoyer ma réponse'}
                        </span>
                    </div>

                    <ScreenBlock title="Écran de confirmation" icon="confirmation" selected={isScreen('confirmation')} onOpen={() => openScreen('confirmation')}>
                        <div className="text-center">
                            <h2 className="mb-4 text-2xl" style={theme.heading}>
                                {settings.confirmation.title || 'Inscription confirmée'}
                            </h2>
                            <p className="whitespace-pre-line opacity-80">
                                {settings.confirmation.message || `Merci, votre inscription à ${eventTitle} est enregistrée.`}
                            </p>
                        </div>
                    </ScreenBlock>

                    <ScreenBlock
                        title="Écran « Je ne peux pas venir »"
                        icon="decline"
                        selected={isScreen('decline_screen')}
                        onOpen={() => openScreen('decline_screen')}
                        toggle={{ label: 'Proposer', checked: settings.rsvp.decline_enabled, onChange: props.onToggleDecline }}
                        muted={!settings.rsvp.decline_enabled}
                        mutedNote="Désactivé : les invités peuvent seulement confirmer leur venue."
                    >
                        <div className="text-center">
                            <h2 className="mb-4 text-2xl" style={theme.heading}>
                                {settings.decline_screen.title || 'Merci pour votre réponse'}
                            </h2>
                            {settings.decline_screen.message && <p className="whitespace-pre-line opacity-80">{settings.decline_screen.message}</p>}
                        </div>
                    </ScreenBlock>
                </div>
            </div>
        </div>
    );
}
