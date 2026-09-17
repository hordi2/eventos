import { Head } from '@inertiajs/react';
import EventLayout from '../../Layouts/EventLayout';

interface MealOption {
    label: string;
    count: number;
    quota: number | null;
    remaining: number | null;
}

interface MealQuestion {
    key: string;
    label: string;
    options: MealOption[];
}

interface Person {
    name: string;
    registrationName: string;
    isCompanion: boolean;
    statusLabel: string;
    meals: Record<string, string>;
}

interface MealsPageProps {
    event: { id: number; title: string };
    questions: MealQuestion[];
    people: Person[];
    expected: number;
}

const HEADER_CELL = 'px-4 py-3 font-normal whitespace-nowrap';

export default function Meals({ event, questions, people, expected }: MealsPageProps) {
    return (
        <EventLayout title="Préférences alimentaires">
            <Head title={`Préférences alimentaires — ${event.title}`} />

            <p className="mb-8 max-w-2xl text-sm text-ink-soft">
                De quoi commander les repas : une ligne par personne attendue, accompagnants compris. Les inscriptions annulées et les réponses « Je ne peux pas
                venir » sont écartées.
            </p>

            {questions.length === 0 ? (
                <p className="text-sm text-ink-soft">Votre formulaire ne pose aucune question « Menu / repas ».</p>
            ) : (
                <>
                    <section className="mb-12">
                        <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">
                            {expected > 1 ? `${expected} personnes attendues` : `${expected} personne attendue`}
                        </h2>
                        <ul className="grid gap-4 sm:grid-cols-2">
                            {questions.map((question) => (
                                <li key={question.key} className="rounded-card border border-line bg-bg p-5">
                                    <p className="mb-4 font-medium text-ink">{question.label}</p>
                                    <ul className="space-y-2 text-sm">
                                        {question.options.map((option) => (
                                            <li key={option.label} className="flex items-baseline justify-between gap-3">
                                                <span className="min-w-0 text-ink">{option.label}</span>
                                                <span className="whitespace-nowrap text-ink-soft tabular-nums">
                                                    {option.count}
                                                    {option.quota !== null && ` / ${option.quota}`}
                                                    {option.remaining !== null && ` · ${option.remaining} restant${option.remaining > 1 ? 's' : ''}`}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section>
                        <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Par personne</h2>

                        {people.length === 0 ? (
                            <p className="text-sm text-ink-soft">Aucune personne attendue pour l'instant.</p>
                        ) : (
                            <div className="overflow-x-auto rounded-card border border-line bg-bg">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-line font-label text-[11px] tracking-[0.12em] text-ink-soft uppercase">
                                        <tr>
                                            <th scope="col" className={HEADER_CELL}>
                                                Personne
                                            </th>
                                            <th scope="col" className={HEADER_CELL}>
                                                Statut
                                            </th>
                                            {questions.map((question) => (
                                                <th key={question.key} scope="col" className={HEADER_CELL}>
                                                    {question.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {people.map((person, index) => (
                                            <tr key={`${person.registrationName}-${index}`}>
                                                <td className="px-4 py-3">
                                                    <span className="block font-medium text-ink">{person.name}</span>
                                                    {person.isCompanion && (
                                                        <span className="block text-xs text-ink-soft">avec {person.registrationName}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-ink-soft">{person.statusLabel}</td>
                                                {questions.map((question) => (
                                                    <td key={question.key} className="px-4 py-3 text-ink-soft">
                                                        {person.meals[question.key] || '—'}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </>
            )}
        </EventLayout>
    );
}
