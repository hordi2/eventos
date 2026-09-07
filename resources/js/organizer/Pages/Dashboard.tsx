import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import OrganizerLayout from '../Layouts/OrganizerLayout';

interface EventSummary {
    id: number;
    title: string;
    banner_url: string | null;
    lifecycle_status: 'draft' | 'published' | 'live' | 'ended' | 'archived';
    start_at_formatted: string;
    is_past: boolean;
    stats: { confirmed: number; waitlisted: number; cancelled: number };
}

interface Props {
    events: EventSummary[];
    canCreateEvents: boolean;
}

const LIFECYCLE_LABELS: Record<EventSummary['lifecycle_status'], string> = {
    draft: 'Inédit',
    published: 'Publié',
    live: 'En cours',
    ended: 'Terminé',
    archived: 'Archivé',
};

function EventCard({ event }: { event: EventSummary }) {
    const { confirmed, waitlisted, cancelled } = event.stats;
    const total = confirmed + waitlisted + cancelled;
    const isDraft = event.lifecycle_status === 'draft';

    return (
        <Link
            href={`/events/${event.id}/edit`}
            className="block overflow-hidden rounded-card border border-line bg-bg transition hover:border-ink"
        >
            <div className="relative flex h-40 items-center justify-center overflow-hidden bg-bg-deep">
                {event.banner_url ? (
                    <img src={event.banner_url} alt="" className="h-full w-full object-cover" />
                ) : (
                    <span className="font-serif text-5xl text-ink-soft italic">{event.title.charAt(0).toUpperCase()}</span>
                )}
            </div>

            <div className="p-5">
                <p className="mb-1 truncate font-medium text-ink">{event.title}</p>
                <p className="mb-4 text-xs text-ink-soft">{event.start_at_formatted}</p>

                {total > 0 && (
                    <div className="mb-3 flex h-1.5 overflow-hidden rounded-pill bg-bg-deep">
                        {confirmed > 0 && <span className="bg-success" style={{ width: `${(confirmed / total) * 100}%` }} />}
                        {waitlisted > 0 && <span className="bg-danger/40" style={{ width: `${(waitlisted / total) * 100}%` }} />}
                        {cancelled > 0 && <span className="bg-danger" style={{ width: `${(cancelled / total) * 100}%` }} />}
                    </div>
                )}

                <div className="mb-3 flex justify-between text-center">
                    <div>
                        <p className="font-serif text-lg text-success italic">{confirmed}</p>
                        <p className="font-label text-[10px] tracking-[0.08em] text-ink-soft uppercase">Confirmées</p>
                    </div>
                    <div>
                        <p className="font-serif text-lg text-ink italic">{waitlisted}</p>
                        <p className="font-label text-[10px] tracking-[0.08em] text-ink-soft uppercase">Liste d'attente</p>
                    </div>
                    <div>
                        <p className="font-serif text-lg text-danger italic">{cancelled}</p>
                        <p className="font-label text-[10px] tracking-[0.08em] text-ink-soft uppercase">Annulées</p>
                    </div>
                </div>

                <div className="flex items-center gap-1.5 border-t border-line pt-3 text-sm">
                    <span className={`h-2 w-2 rounded-full ${isDraft ? 'bg-ink-soft' : 'bg-success'}`} />
                    <span className="text-ink-soft">{LIFECYCLE_LABELS[event.lifecycle_status]}</span>
                </div>
            </div>
        </Link>
    );
}

export default function Dashboard({ events, canCreateEvents }: Props) {
    const [tab, setTab] = useState<'current' | 'past'>('current');
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const byTab = events.filter((event) => (tab === 'current' ? !event.is_past : event.is_past));

        if (!search.trim()) {
            return byTab;
        }

        const needle = search.trim().toLowerCase();

        return byTab.filter((event) => event.title.toLowerCase().includes(needle));
    }, [events, tab, search]);

    return (
        <OrganizerLayout title="Tableau de bord">
            <Head title="Tableau de bord" />

            <div className="mb-8 flex flex-wrap items-center gap-4">
                <div className="relative flex-1">
                    <svg
                        viewBox="0 0 24 24"
                        className="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 stroke-ink-soft fill-none"
                        strokeWidth="2"
                    >
                        <circle cx="11" cy="11" r="7" />
                        <path d="m21 21-4.3-4.3" strokeLinecap="round" />
                    </svg>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Trouve ton événement..."
                        className="w-full rounded-pill border border-line bg-bg py-3 pr-4 pl-11 text-sm text-ink placeholder:text-ink-soft"
                    />
                </div>

                {canCreateEvents && (
                    <Link href="/events/create" className="shrink-0 rounded-pill bg-ink px-6 py-3 text-sm font-medium text-bg">
                        + Nouvel événement
                    </Link>
                )}
            </div>

            <nav className="mb-8 flex gap-6 border-b border-line">
                <button
                    type="button"
                    onClick={() => setTab('current')}
                    className={`border-b-2 pb-3 text-sm ${tab === 'current' ? 'border-ink text-ink' : 'border-transparent text-ink-soft'}`}
                >
                    Actuel
                </button>
                <button
                    type="button"
                    onClick={() => setTab('past')}
                    className={`border-b-2 pb-3 text-sm ${tab === 'past' ? 'border-ink text-ink' : 'border-transparent text-ink-soft'}`}
                >
                    Événements passés
                </button>
            </nav>

            {filtered.length === 0 ? (
                <p className="text-ink-soft">
                    {events.length === 0
                        ? "Aucun événement pour l'instant."
                        : tab === 'current'
                          ? 'Aucun événement en cours ou à venir.'
                          : 'Aucun événement passé.'}
                </p>
            ) : (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((event) => (
                        <EventCard key={event.id} event={event} />
                    ))}
                </div>
            )}
        </OrganizerLayout>
    );
}
