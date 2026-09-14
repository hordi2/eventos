import BuilderIcon, { type BuilderIconName } from './BuilderIcon';

interface BlockToolbarProps {
    label: string;
    onSettings: () => void;
    onDuplicate: () => void;
}

interface ToolbarButton {
    icon: BuilderIconName;
    text: string;
    onClick: () => void;
}

/**
 * Boutons ronds à gauche d'un bloc de question. Deux seulement, pour tenir à
 * côté des blocs les plus courts ; déplacer et retirer vivent dans l'en-tête.
 */
export default function BlockToolbar({ label, onSettings, onDuplicate }: BlockToolbarProps) {
    const buttons: ToolbarButton[] = [
        { icon: 'gear', text: 'Réglages', onClick: onSettings },
        { icon: 'copy', text: 'Dupliquer', onClick: onDuplicate },
    ];

    return (
        <div role="toolbar" aria-label={`Actions : ${label}`} className="flex gap-1.5 px-4 pt-3 sm:absolute sm:top-2 sm:-left-12 sm:flex-col sm:p-0">
            {buttons.map((button) => (
                <button
                    key={button.text}
                    type="button"
                    onClick={button.onClick}
                    title={button.text}
                    aria-label={`${button.text} : ${label}`}
                    className="flex h-9 w-9 items-center justify-center rounded-full bg-ink text-bg shadow-sm ring-1 ring-line transition hover:opacity-85"
                >
                    <BuilderIcon name={button.icon} className="h-4 w-4" />
                </button>
            ))}
        </div>
    );
}
