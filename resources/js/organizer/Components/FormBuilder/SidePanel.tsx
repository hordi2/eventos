import { type PropsWithChildren, type ReactNode } from 'react';
import BuilderIcon, { type BuilderIconName } from './BuilderIcon';

interface SidePanelProps {
    title: string;
    icon: BuilderIconName;
    onClose?: () => void;
    footer?: ReactNode;
}

/**
 * Panneau de gauche du constructeur : la palette, ou les réglages du bloc,
 * de l'écran ou du thème en cours de modification.
 */
export default function SidePanel({ title, icon, onClose, footer, children }: PropsWithChildren<SidePanelProps>) {
    return (
        <section aria-label={title} className="rounded-card border border-line bg-bg">
            <header className="flex items-start gap-3 border-b border-line px-5 py-4">
                <BuilderIcon name={icon} className="mt-0.5 h-5 w-5 text-ink-soft" />
                <h2 className="min-w-0 flex-1 font-sans text-lg leading-snug font-semibold text-ink">{title}</h2>
                {onClose && (
                    <button type="button" onClick={onClose} aria-label="Fermer le panneau" className="text-ink-soft hover:text-ink">
                        <BuilderIcon name="close" />
                    </button>
                )}
            </header>
            <div className="space-y-7 px-5 py-5">{children}</div>
            {footer && <footer className="flex flex-wrap items-center gap-3 border-t border-line px-5 py-4">{footer}</footer>}
        </section>
    );
}
