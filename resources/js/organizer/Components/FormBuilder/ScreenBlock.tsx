import { type PropsWithChildren } from 'react';
import Toggle from '../Toggle';
import BuilderIcon, { type BuilderIconName } from './BuilderIcon';

interface ScreenBlockProps {
    title: string;
    icon: BuilderIconName;
    selected: boolean;
    onOpen: () => void;
    toggle?: { label: string; checked: boolean; onChange: (checked: boolean) => void };
    muted?: boolean;
    mutedNote?: string;
}

/**
 * Écran fixe du parcours (bienvenue, coordonnées, confirmation, refus) : une
 * barre d'outils de l'organisateur au-dessus d'un aperçu aux couleurs du thème.
 */
export default function ScreenBlock({ title, icon, selected, onOpen, toggle, muted = false, mutedNote, children }: PropsWithChildren<ScreenBlockProps>) {
    return (
        <section aria-label={title} className={`overflow-hidden rounded-card transition ${selected ? 'ring-2 ring-current' : 'ring-1 ring-black/10'}`}>
            <header className="flex flex-wrap items-center gap-3 border-b border-line bg-bg px-4 py-2.5 text-ink">
                <BuilderIcon name={icon} className="h-5 w-5 text-ink-soft" />
                <button type="button" onClick={onOpen} className="min-w-0 flex-1 text-left font-sans text-sm font-semibold hover:underline">
                    {title}
                </button>
                {toggle && (
                    <span className="flex items-center gap-2 text-sm text-ink-soft">
                        {toggle.label}
                        <Toggle checked={toggle.checked} onChange={toggle.onChange} label={`${toggle.label} : ${title}`} />
                    </span>
                )}
                <button
                    type="button"
                    onClick={onOpen}
                    aria-label={`Réglages : ${title}`}
                    className="rounded-pill p-1.5 text-ink-soft transition hover:bg-bg-alt hover:text-ink"
                >
                    <BuilderIcon name="gear" className="h-4.5 w-4.5" />
                </button>
            </header>
            <div className={`px-5 py-8 sm:px-8 ${muted ? 'opacity-45' : ''}`}>{children}</div>
            {muted && mutedNote && <p className="border-t border-line bg-bg px-4 py-2 text-xs text-ink-soft">{mutedNote}</p>}
        </section>
    );
}
