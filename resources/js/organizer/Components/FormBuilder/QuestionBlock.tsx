import { type DragEvent } from 'react';
import BlockToolbar from './BlockToolbar';
import BuilderIcon, { type BuilderIconName } from './BuilderIcon';
import FieldPreview from './FieldPreview';
import { type FieldState, MOVE_DRAG_TYPE } from './logic';
import PremiumBadge from './PremiumBadge';
import { type BuilderField } from './types';

interface QuestionBlockProps {
    field: BuilderField;
    position: number;
    total: number;
    state: FieldState;
    selected: boolean;
    premium: boolean;
    typeLabel: string;
    tagNames: string[];
    hasRule: boolean;
    declineEnabled: boolean;
    companionsEnabled: boolean;
    value: unknown;
    onValueChange: (value: unknown) => void;
    onOpen: () => void;
    onDuplicate: () => void;
    onMove: (direction: -1 | 1) => void;
    onRemove: () => void;
    onDragStateChange: (dragging: boolean) => void;
}

interface HeaderAction {
    icon: BuilderIconName;
    text: string;
    onClick: () => void;
    disabled?: boolean;
    danger?: boolean;
}

export default function QuestionBlock({
    field,
    position,
    total,
    state,
    selected,
    premium,
    typeLabel,
    tagNames,
    hasRule,
    declineEnabled,
    companionsEnabled,
    value,
    onValueChange,
    onOpen,
    onDuplicate,
    onMove,
    onRemove,
    onDragStateChange,
}: QuestionBlockProps) {
    const title = field.label || 'Question sans titre';
    const showIf = field.config.show_if ?? 'attending';
    const chips: string[] = [];

    if (field.is_required && field.type !== 'informational_text') {
        chips.push('Obligatoire');
    }

    if (declineEnabled && showIf !== 'always') {
        chips.push(showIf === 'attending' ? 'Invités présents' : 'Invités qui ne viennent pas');
    }

    if (companionsEnabled && field.config.ask_scope === 'each_attendee') {
        chips.push('À chaque personne');
    }

    if (tagNames.length > 0) {
        chips.push(`Tags : ${tagNames.join(', ')}`);
    }

    if (hasRule) {
        chips.push('Condition');
    }

    // Monter/Descendre doublent le glisser-déposer pour qui navigue au clavier.
    const actions: HeaderAction[] = [
        { icon: 'up', text: 'Monter', onClick: () => onMove(-1), disabled: position === 0 },
        { icon: 'down', text: 'Descendre', onClick: () => onMove(1), disabled: position >= total - 1 },
        { icon: 'trash', text: 'Retirer', onClick: onRemove, danger: true },
    ];

    function handleDragStart(event: DragEvent<HTMLSpanElement>) {
        event.dataTransfer.setData(MOVE_DRAG_TYPE, field.uid);
        event.dataTransfer.effectAllowed = 'move';
        onDragStateChange(true);
    }

    return (
        <article
            aria-label={title}
            className={`relative rounded-card transition ${selected ? 'ring-2 ring-current' : 'ring-1 ring-black/10 hover:ring-black/25'}`}
        >
            <BlockToolbar label={title} onSettings={onOpen} onDuplicate={onDuplicate} />

            <div className="flex flex-wrap items-center gap-2 border-b border-black/10 px-4 py-2">
                <span
                    draggable
                    onDragStart={handleDragStart}
                    onDragEnd={() => onDragStateChange(false)}
                    title="Glisser pour déplacer"
                    className="cursor-grab opacity-50 active:cursor-grabbing"
                >
                    <BuilderIcon name="grip" className="h-4 w-4" />
                </span>
                <button type="button" onClick={onOpen} className="font-label text-[11px] tracking-[0.12em] uppercase opacity-70 hover:opacity-100">
                    {typeLabel}
                </button>
                {premium && <PremiumBadge compact />}
                {chips.map((chip) => (
                    <span key={chip} className="rounded-pill bg-black/5 px-2 py-0.5 text-[11px]">
                        {chip}
                    </span>
                ))}
                <span className="ml-auto flex items-center gap-1">
                    {!state.visible && <span className="mr-2 text-[11px] opacity-70">Masquée dans cette simulation</span>}
                    {actions.map((action) => (
                        <button
                            key={action.text}
                            type="button"
                            onClick={action.onClick}
                            disabled={action.disabled}
                            title={action.text}
                            aria-label={`${action.text} : ${title}`}
                            className={`rounded-pill p-1.5 transition hover:bg-black/5 disabled:cursor-not-allowed disabled:opacity-25 ${
                                action.danger ? 'text-danger' : 'opacity-70 hover:opacity-100'
                            }`}
                        >
                            <BuilderIcon name={action.icon} className="h-4 w-4" />
                        </button>
                    ))}
                </span>
            </div>

            <div className={`px-5 py-5 ${state.visible ? '' : 'opacity-45'}`}>
                <FieldPreview field={field} required={state.required} value={value} onChange={onValueChange} />
            </div>
        </article>
    );
}
