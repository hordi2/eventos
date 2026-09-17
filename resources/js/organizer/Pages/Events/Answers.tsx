import { Head } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import EventLayout from '../../Layouts/EventLayout';

interface Breakdown {
    label: string;
    count: number;
    remaining: number | null;
}

interface Question {
    key: string;
    label: string;
    type: string;
    answered: number;
    breakdown: Breakdown[];
    totals: string[];
    samples: string[];
    link: string | null;
}

interface Guest {
    id: number;
    name: string;
    email: string;
    status: 'confirmed' | 'waitlisted' | 'cancelled' | 'declined';
    statusLabel: string;
    registeredAt: string;
    answers: Record<string, string>;
}

interface AnswersPageProps {
    event: { id: number; title: string };
    questions: Question[];
    guests: Guest[];
    totalGuests: number;
    shownGuests: number;
}

const STATUS_VARIANTS: Record<Guest['status'], 'neutral' | 'success' | 'danger'> = {
    confirmed: 'success',
    waitlisted: 'neutral',
    cancelled: 'danger',
    declined: 'neutral',
};

const HEADER_CELL = 'px-4 py-3 font-normal whitespace-nowrap';

export default function Answers({ event, questions, guests, totalGuests, shownGuests }: AnswersPageProps) {
    return (
        <EventLayout title="Réponses aux questions">
            <Head title={`Réponses aux questions — ${event.title}`} />

            <p className="mb-8 max-w-2xl text-sm text-ink-soft">
                Ce que vos invités ont répondu à chaque question de votre formulaire. Les réponses des accompagnants sont indiquées avec leur prénom.
            </p>

            {questions.length === 0 ? (
                <p className="text-sm text-ink-soft">Votre formulaire ne pose aucune question pour l'instant.</p>
            ) : (
                <>
                    <section className="mb-12">
                        <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Par question</h2>
                        <ul className="grid gap-4 sm:grid-cols-2">
                            {questions.map((question) => (
                                <li key={question.key} className="rounded-card border border-line bg-bg p-5">
                                    <p className="font-medium text-ink">{question.label}</p>
                                    <p className="mt-0.5 text-xs text-ink-soft">
                                        {question.type} · {question.answered > 1 ? `${question.answered} réponses` : `${question.answered} réponse`}
                                    </p>

                                    {question.breakdown.length > 0 && (
                                        <ul className="mt-4 space-y-2 text-sm">
                                            {question.breakdown.map((line) => (
                                                <li key={line.label} className="flex items-baseline justify-between gap-3">
                                                    <span className="min-w-0 text-ink">{line.label}</span>
                                                    <span className="whitespace-nowrap text-ink-soft tabular-nums">
                                                        {line.count}
                                                        {line.remaining !== null && ` · ${line.remaining} restant${line.remaining > 1 ? 's' : ''}`}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}

                                    {question.totals.length > 0 && (
                                        <p className="mt-4 text-sm font-medium text-ink tabular-nums">{question.totals.join(' · ')}</p>
                                    )}

                                    {question.samples.length > 0 && (
                                        <ul className="mt-4 space-y-1 text-sm text-ink-soft">
                                            {question.samples.map((sample, index) => (
                                                <li key={index} className="line-clamp-2">
                                                    {sample}
                                                </li>
                                            ))}
                                        </ul>
                                    )}

                                    {question.link && (
                                        <a href={question.link} className="mt-4 inline-block text-sm font-medium text-ink underline underline-offset-2">
                                            Voir les fichiers reçus
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section>
                        <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Par invité</h2>

                        {guests.length === 0 ? (
                            <p className="text-sm text-ink-soft">Aucune inscription pour l'instant.</p>
                        ) : (
                            <>
                                <div className="overflow-x-auto rounded-card border border-line bg-bg">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-line font-label text-[11px] tracking-[0.12em] text-ink-soft uppercase">
                                            <tr>
                                                <th scope="col" className={HEADER_CELL}>
                                                    Invité
                                                </th>
                                                <th scope="col" className={HEADER_CELL}>
                                                    Statut
                                                </th>
                                                <th scope="col" className={HEADER_CELL}>
                                                    Inscrit le
                                                </th>
                                                {questions.map((question) => (
                                                    <th key={question.key} scope="col" className={HEADER_CELL}>
                                                        {question.label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-line">
                                            {guests.map((guest) => (
                                                <tr key={guest.id} className="align-top">
                                                    <td className="px-4 py-3">
                                                        <span className="block font-medium text-ink">{guest.name}</span>
                                                        <span className="block text-xs text-ink-soft">{guest.email}</span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant={STATUS_VARIANTS[guest.status]}>{guest.statusLabel}</Badge>
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap text-ink-soft tabular-nums">{guest.registeredAt}</td>
                                                    {questions.map((question) => (
                                                        <td key={question.key} className="px-4 py-3 whitespace-pre-line text-ink-soft">
                                                            {guest.answers[question.key] ?? '—'}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {totalGuests > shownGuests && (
                                    <p className="mt-3 text-xs text-ink-soft">
                                        {shownGuests} inscriptions les plus récentes sur {totalGuests}. Passez par un export pour la liste complète.
                                    </p>
                                )}
                            </>
                        )}
                    </section>
                </>
            )}
        </EventLayout>
    );
}
