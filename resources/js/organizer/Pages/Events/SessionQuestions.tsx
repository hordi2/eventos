import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Textarea from '../../Components/Textarea';
import EventLayout from '../../Layouts/EventLayout';

interface QuestionRow {
    id: number;
    body: string;
    upvoteCount: number;
    status: 'pending' | 'answered' | 'hidden';
    statusLabel: string;
    answer: string | null;
    authorName: string;
    createdAt: string;
}

interface Props {
    event: { id: number; title: string };
    questions: QuestionRow[];
}

const CARD = 'rounded-card border border-line bg-bg p-5';

/**
 * Questions au conférencier (D9), côté organisateur : les plus soutenues
 * d'abord, pour répondre d'abord à celles qui comptent pour le plus de
 * monde.
 */
export default function SessionQuestions({ event, questions }: Props) {
    return (
        <EventLayout title="Questions au conférencier" eyebrow={event.title}>
            <Head title={`Questions au conférencier — ${event.title}`} />

            <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                Les participants posent leurs questions depuis l'application et votent pour celles des autres. Les plus
                soutenues paraissent en premier.
            </p>

            {questions.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucune question pour l'instant</p>
                    <p className="text-sm text-ink-soft">Elle paraîtra ici dès qu'un participant en posera une.</p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {questions.map((question) => (
                        <QuestionCard key={question.id} event={event} question={question} />
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}

function QuestionCard({ event, question }: { event: Props['event']; question: QuestionRow }) {
    const [answer, setAnswer] = useState(question.answer ?? '');
    const [processing, setProcessing] = useState(false);

    function submitAnswer(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        setProcessing(true);
        router.post(
            `/events/${event.id}/questions/${question.id}/answer`,
            { answer },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    }

    function toggleHidden() {
        const url = `/events/${event.id}/questions/${question.id}/${question.status === 'hidden' ? 'restore' : 'hide'}`;
        router.post(url, {}, { preserveScroll: true });
    }

    return (
        <li className={CARD}>
            <div className="mb-3 flex flex-wrap items-center gap-3">
                <p className="text-ink">{question.authorName || 'Anonyme'}</p>
                <span className="text-sm text-ink-soft tabular-nums">↑ {question.upvoteCount}</span>
                {question.status === 'answered' && <Badge variant="success">Répondue</Badge>}
                {question.status === 'hidden' && <Badge variant="danger">Masquée</Badge>}
            </div>

            <p className="mb-4 text-ink italic">« {question.body} »</p>

            <form onSubmit={submitAnswer} className="mb-3">
                <Textarea
                    value={answer}
                    onChange={(e) => setAnswer(e.target.value)}
                    maxLength={1000}
                    rows={2}
                    placeholder="Votre réponse (facultatif)…"
                />
                <Button type="submit" disabled={processing} className="mt-2 w-auto px-5 py-2 text-sm">
                    {processing ? 'Enregistrement…' : 'Marquer comme répondue'}
                </Button>
            </form>

            <button type="button" onClick={toggleHidden} className="text-sm text-ink-soft underline hover:no-underline">
                {question.status === 'hidden' ? 'Afficher à nouveau' : 'Masquer'}
            </button>
        </li>
    );
}
