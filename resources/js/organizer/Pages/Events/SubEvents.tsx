import { Head, usePage } from '@inertiajs/react';
import SubEventCard from '../../Components/SubEvents/SubEventCard';
import SubEventForm from '../../Components/SubEvents/SubEventForm';
import { type SubEventRow } from '../../Components/SubEvents/types';
import EventLayout from '../../Layouts/EventLayout';
import { type SharedProps } from '../../types';

interface SubEventsPageProps {
    event: { id: number; title: string };
    subEvents: SubEventRow[];
}

export default function SubEvents({ event, subEvents }: SubEventsPageProps) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;

    return (
        <EventLayout title="Événements secondaires">
            <Head title={`Événements secondaires — ${event.title}`} />

            <p className="mb-8 max-w-2xl text-sm text-ink-soft">
                Dîner, ateliers, cérémonie… Chaque session a ses horaires, sa capacité, sa liste d'attente et son accueil. Proposez-les ensuite dans le
                formulaire d'inscription avec le bloc « Événements secondaires » : chaque invité coche celles où il vient, avec ses accompagnants.
            </p>

            {errors.subEvent && (
                <div role="alert" className="mb-6 rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">
                    {errors.subEvent}
                </div>
            )}

            <section className="mb-10 rounded-card border border-line bg-bg p-6">
                <h2 className="mb-5 font-sans text-lg font-semibold text-ink">Nouvelle session</h2>
                <SubEventForm eventId={event.id} />
            </section>

            <section>
                <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">
                    {subEvents.length > 1 ? `${subEvents.length} sessions` : `${subEvents.length} session`}
                </h2>
                {subEvents.length === 0 ? (
                    <p className="text-sm text-ink-soft">Aucune session pour l'instant.</p>
                ) : (
                    <ul className="space-y-4">
                        {subEvents.map((subEvent) => (
                            <SubEventCard key={subEvent.id} eventId={event.id} subEvent={subEvent} />
                        ))}
                    </ul>
                )}
            </section>
        </EventLayout>
    );
}
