import { type DragEvent } from 'react';
import BuilderIcon from './BuilderIcon';
import { PALETTE_DRAG_TYPE, type PaletteEntry } from './logic';
import PremiumBadge from './PremiumBadge';

interface PaletteItemProps {
    entry: PaletteEntry;
    onActivate: (entry: PaletteEntry) => void;
    onDragStateChange: (dragging: boolean) => void;
}

export default function PaletteItem({ entry, onActivate, onDragStateChange }: PaletteItemProps) {
    const draggable = ['field', 'custom', 'subEvents', 'donation'].includes(entry.action.kind);

    function handleDragStart(event: DragEvent<HTMLButtonElement>) {
        event.dataTransfer.setData(PALETTE_DRAG_TYPE, entry.id);
        event.dataTransfer.effectAllowed = 'copy';
        onDragStateChange(true);
    }

    return (
        <li>
            <button
                type="button"
                draggable={draggable}
                onDragStart={draggable ? handleDragStart : undefined}
                onDragEnd={() => onDragStateChange(false)}
                onClick={() => onActivate(entry)}
                className={`flex w-full items-center gap-3 rounded-control border border-line bg-bg px-4 py-3 text-left text-ink transition-colors duration-200 hover:border-ink ${
                    draggable ? 'cursor-grab active:cursor-grabbing' : ''
                }`}
            >
                <BuilderIcon name={entry.icon} className="h-5 w-5 text-ink-soft" />
                <span className="min-w-0 flex-1 text-sm font-medium">{entry.label}</span>
                {entry.premium && <PremiumBadge compact />}
            </button>
        </li>
    );
}
