import { router } from '@inertiajs/react';
import { useState } from 'react';
import SubEventForm from './SubEventForm';
import { type SubEventRow } from './types';

interface SubEventCardProps {
    eventId: number;
    subEvent: SubEventRow;
}

export default function SubEventCard({ eventId, subEvent }: SubEventCardProps) {
    const [editing, setEditing] = useState(false);
    const full = subEvent.capacity !== null && subEvent.people >= subEvent.capacity;

    function remove() {
        if (!window.confirm(`Supprimer la session « ${subEvent.title} » ?`)) {
            return;
        }

        router.delete(`/events/${eventId}/sub-events/${subEvent.id}`, { preserveScroll: true });
    }

    return (
        <li className="rounded-card border border-line bg-bg p-5">
            {editing ? (
                <SubEventForm eventId={eventId} subEvent={subEvent} onDone={() => setEditing(false)} />
            ) : (
                <div className="flex flex-wrap items-start gap-4">
                    <div className="min-w-0 flex-1">
                        <h3 className="font-sans text-base font-semibold text-ink">{subEvent.title}</h3>
                        <p className="mt-1 text-sm text-ink-soft first-letter:uppercase">{subEvent.schedule}</p>
                        <p className="mt-3 text-sm text-ink">
                            <span className="font-medium tabular-nums">{subEvent.people}</span>
                            {subEvent.capacity !== null ? ` / ${subEvent.capacity} places tenues` : ' places tenues (capacité illimitée)'}
                            {subEvent.waitlisted > 0 && <span className="text-ink-soft"> · {subEvent.waitlisted} en liste d'attente</span>}
                        </p>
                        {full && (
                            <p className="mt-1 text-xs text-ink-soft">
                                {subEvent.allowWaitlist ? "Complète : les nouveaux inscrits passent en liste d'attente." : 'Complète : elle ne peut plus être choisie.'}
                            </p>
                        )}
                        {subEvent.conflicts.length > 0 && (
                            <p className="mt-2 rounded-control bg-danger-bg px-3 py-2 text-xs text-danger">
                                Même horaire que {subEvent.conflicts.map((title) => `« ${title} »`).join(', ')} : un invité ne pourra pas choisir les deux.
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={subEvent.checkInUrl} className="rounded-pill border border-line px-4 py-1.5 text-sm font-medium text-ink hover:border-ink">
                            Accueil
                        </a>
                        <button type="button" onClick={() => setEditing(true)} className="rounded-pill border border-line px-4 py-1.5 text-sm font-medium text-ink hover:border-ink">
                            Modifier
                        </button>
                        <button type="button" onClick={remove} className="rounded-pill px-4 py-1.5 text-sm font-medium text-danger hover:bg-danger-bg">
                            Supprimer
                        </button>
                    </div>
                </div>
            )}
        </li>
    );
}
