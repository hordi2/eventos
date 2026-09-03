import { Head, Link } from '@inertiajs/react';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface SegmentSummary {
    value: string;
    label: string;
    count: number;
}

export default function Index({ event, segments }: { event: { id: number; title: string }; segments: SegmentSummary[] }) {
    return (
        <OrganizerLayout title="Segments" eyebrow={event.title}>
            <Head title="Segments" />

            <p className="mb-8 max-w-lg text-sm text-ink-soft">
                Chaque segment est recalculé à chaque consultation, jamais figé : il reflète toujours l'état actuel des inscriptions.
            </p>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {segments.map((segment) => (
                    <Link
                        key={segment.value}
                        href={`/events/${event.id}/segments/${segment.value}`}
                        className="rounded-card border border-line bg-bg p-6 hover:border-ink"
                    >
                        <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">{segment.label}</p>
                        <p className="mt-2 text-3xl text-ink">{segment.count}</p>
                    </Link>
                ))}
            </div>
        </OrganizerLayout>
    );
}
