import { router } from '@inertiajs/react';
import { useState } from 'react';
import useCloseOnClickOutside from '../hooks/useCloseOnClickOutside';
import { type EventNav } from '../types';
import EventIcon from './EventIcon';

const STATUS_LABELS: Record<EventNav['status'], string> = {
    draft: 'Inédit',
    published: 'Publié',
    archived: 'Archivé',
};

const STATUS_DOTS: Record<EventNav['status'], string> = {
    draft: 'bg-ink-soft',
    published: 'bg-success',
    archived: 'bg-line',
};

interface StatusOptionProps {
    title: string;
    description: string;
    selected: boolean;
    onSelect?: () => void;
}

function StatusOption({ title, description, selected, onSelect }: StatusOptionProps) {
    return (
        <button
            type="button"
            role="menuitemradio"
            aria-checked={selected}
            onClick={onSelect}
            disabled={onSelect === undefined}
            className="flex w-full items-start gap-3 rounded-card px-3 py-3 text-left hover:bg-bg-alt disabled:cursor-default disabled:hover:bg-transparent"
        >
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-medium text-ink">{title}</span>
                <span className="mt-0.5 block text-xs text-ink-soft">{description}</span>
            </span>
            {selected && <EventIcon name="check" className="mt-0.5 h-4 w-4 text-success" />}
        </button>
    );
}

/**
 * Menu « Inédit / Publié » de la barre du haut d'un événement.
 */
export default function EventStatusMenu({ nav }: { nav: EventNav }) {
    const [open, setOpen] = useState(false);
    const [copied, setCopied] = useState<'test' | 'public' | null>(null);
    const ref = useCloseOnClickOutside(open, () => setOpen(false));

    const badge = (
        <span className="flex items-center gap-2">
            <span className={`h-2 w-2 rounded-full ${STATUS_DOTS[nav.status]}`} />
            {STATUS_LABELS[nav.status]}
        </span>
    );

    if (!nav.canChangeStatus) {
        return <span className="inline-flex items-center rounded-pill border border-line bg-bg px-4 py-2 text-sm text-ink">{badge}</span>;
    }

    function publish() {
        setOpen(false);
        router.post(`/events/${nav.id}/publish`, {}, { preserveScroll: true });
    }

    function unpublish() {
        setOpen(false);

        if (confirm("Repasser l'événement en « Inédit » ? Sa page publique et les inscriptions seront fermées jusqu'à la prochaine publication.")) {
            router.post(`/events/${nav.id}/unpublish`, {}, { preserveScroll: true });
        }
    }

    async function copy(url: string, kind: 'test' | 'public') {
        await navigator.clipboard.writeText(url);
        setCopied(kind);
        setTimeout(() => setCopied(null), 2000);
    }

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="menu"
                className="inline-flex items-center gap-3 rounded-pill border border-line bg-bg px-4 py-2 text-sm text-ink hover:border-ink"
            >
                {badge}
                <EventIcon name="chevronDown" className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div role="menu" className="absolute top-full right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-card border border-line bg-bg p-2 shadow-lg">
                    <StatusOption
                        title="Publié"
                        description="Le lien public est en ligne et accepte les inscriptions."
                        selected={nav.status === 'published'}
                        onSelect={nav.status === 'draft' ? publish : undefined}
                    />
                    <StatusOption
                        title="Inédit"
                        description="Page publique et inscriptions fermées : visible seulement avec le lien de test."
                        selected={nav.status === 'draft'}
                        onSelect={nav.status === 'published' ? unpublish : undefined}
                    />

                    <div className="my-2 border-t border-line" />

                    {nav.previewUrl && (
                        <button
                            type="button"
                            role="menuitem"
                            onClick={() => copy(nav.previewUrl as string, 'test')}
                            className="flex w-full items-center gap-3 rounded-card px-3 py-2.5 text-left text-sm text-ink hover:bg-bg-alt"
                        >
                            <EventIcon name="link" className="h-4 w-4 text-ink-soft" />
                            {copied === 'test' ? 'Lien de test copié' : 'Copier le lien de test'}
                        </button>
                    )}
                    {nav.publicUrl && (
                        <button
                            type="button"
                            role="menuitem"
                            onClick={() => copy(nav.publicUrl as string, 'public')}
                            className="flex w-full items-center gap-3 rounded-card px-3 py-2.5 text-left text-sm text-ink hover:bg-bg-alt"
                        >
                            <EventIcon name="link" className="h-4 w-4 text-ink-soft" />
                            {copied === 'public' ? 'Lien public copié' : 'Copier le lien public'}
                        </button>
                    )}
                    <span aria-disabled="true" className="flex items-center gap-3 px-3 py-2.5 text-sm text-ink-soft">
                        <EventIcon name="chart" className="h-4 w-4" />
                        <span className="flex-1">Calendrier d'ouverture</span>
                        <span className="rounded-pill border border-line px-2 py-0.5 text-[10px] tracking-[0.08em] uppercase">Bientôt</span>
                    </span>
                </div>
            )}
        </div>
    );
}
