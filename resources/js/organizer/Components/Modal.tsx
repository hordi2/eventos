import { useEffect, useRef, type PropsWithChildren } from 'react';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    size?: 'md' | 'lg';
    showCloseButton?: boolean;
}

const SIZE_CLASSES: Record<NonNullable<ModalProps['size']>, string> = {
    md: 'max-w-md',
    lg: 'max-w-2xl',
};

/**
 * Ferme au clic sur le fond et à la touche Échap, déplace le focus dans la
 * boîte de dialogue à l'ouverture (WCAG 2.4.3). Ne implémente pas de piège
 * de focus cyclique complet (Tab qui boucle à l'intérieur) — à ajouter si un
 * usage réel du composant l'exige.
 */
export default function Modal({ open, onClose, title, size = 'md', showCloseButton = false, children }: PropsWithChildren<ModalProps>) {
    const dialogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        dialogRef.current?.focus();

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                aria-label="Fermer"
                onClick={onClose}
                className="absolute inset-0 bg-ink/40"
            />
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-title"
                tabIndex={-1}
                className={`relative max-h-full w-full overflow-y-auto rounded-card bg-bg p-8 shadow-xl focus:outline-none ${SIZE_CLASSES[size]}`}
            >
                <div className="flex items-start justify-between gap-4">
                    <h2 id="modal-title" className="font-serif text-xl font-medium text-ink italic">
                        {title}
                    </h2>
                    {showCloseButton && (
                        <button type="button" onClick={onClose} aria-label="Fermer" className="shrink-0 text-ink-soft hover:text-ink">
                            <svg viewBox="0 0 24 24" className="h-5 w-5 stroke-current" fill="none" strokeWidth="1.8" strokeLinecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    )}
                </div>
                <div className="mt-4">{children}</div>
            </div>
        </div>
    );
}
