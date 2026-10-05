import { Head, router } from '@inertiajs/react';
import EventLayout from '../../Layouts/EventLayout';

interface ReportRow {
    id: number;
    status: string;
    statusLabel: string;
    reason: string | null;
    reportedAt: string | null;
    reporter: string;
    sender: string;
    body: string | null;
    removed: boolean;
    suspended: boolean;
    handledBy: string | null;
}

interface Props {
    event: { id: number; title: string };
    reports: ReportRow[];
}

/**
 * Modération de la messagerie entre participants (D8). On ne voit ici que
 * les messages signalés — jamais les conversations entières.
 */
export default function Moderation({ event, reports }: Props) {
    function decide(report: ReportRow, decision: 'remove' | 'suspend' | 'dismiss') {
        router.patch(`/events/${event.id}/moderation/${report.id}`, { decision });
    }

    return (
        <EventLayout title="Modération" eyebrow={event.title}>
            <Head title="Modération" />

            <p className="mb-8 text-sm text-ink-soft">
                Les messages que vos participants vous ont signalés. Vous ne voyez que ceux-là : le reste de leurs
                conversations ne vous est pas montré.
            </p>

            {reports.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun signalement</p>
                    <p className="text-sm text-ink-soft">Vos participants n'ont rien eu à vous soumettre.</p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {reports.map((report) => (
                        <li key={report.id} className="rounded-card bg-bg p-5 ring-1 ring-line">
                            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-3">
                                <p className="text-ink">
                                    <span className="font-medium">{report.sender}</span>, signalé par {report.reporter}
                                </p>
                                <p className="font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">
                                    {report.statusLabel}
                                    {report.handledBy && ` · ${report.handledBy}`}
                                </p>
                            </div>

                            {report.reason && <p className="mb-3 text-sm text-ink-soft">Motif : {report.reason}</p>}

                            <blockquote className="mb-3 rounded-control border border-line bg-bg-alt px-4 py-3 text-sm text-ink">
                                {report.body ?? 'Message introuvable.'}
                            </blockquote>

                            <p className="mb-4 text-xs text-ink-soft">
                                {report.reportedAt}
                                {report.removed && ' · message retiré'}
                                {report.suspended && ' · envoi suspendu pour son auteur'}
                            </p>

                            {report.status === 'open' && (
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => decide(report, 'remove')}
                                        className="inline-flex min-h-9 items-center rounded-pill bg-ink px-4 py-1.5 text-sm text-bg"
                                    >
                                        Retirer le message
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => decide(report, 'suspend')}
                                        className="inline-flex min-h-9 items-center rounded-pill border border-danger px-4 py-1.5 text-sm text-danger"
                                    >
                                        Suspendre son auteur
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => decide(report, 'dismiss')}
                                        className="inline-flex min-h-9 items-center rounded-pill border border-line px-4 py-1.5 text-sm text-ink"
                                    >
                                        Classer sans suite
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}
