import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

interface OptionRow {
    id: number;
    label: string;
    voteCount: number;
    percent: number;
}

interface PollRow {
    id: number;
    question: string;
    status: 'draft' | 'open' | 'closed';
    statusLabel: string;
    totalVotes: number;
    options: OptionRow[];
}

interface Props {
    event: { id: number; title: string };
    polls: PollRow[];
}

const CARD = 'rounded-card border border-line bg-bg p-5';

/**
 * Sondages en direct (D9) : une question à choix unique, ouverte puis
 * fermée. Pas de temps réel (WebSocket) : la page invité se recharge toute
 * seule (voir event-polls.blade.php).
 */
export default function LivePolls({ event, polls }: Props) {
    const form = useForm<{ question: string; options: string[] }>({ question: '', options: ['', ''] });

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        form.post(`/events/${event.id}/polls`, {
            onSuccess: () => form.reset(),
        });
    }

    function addOption() {
        if (form.data.options.length >= 8) {
            return;
        }
        form.setData('options', [...form.data.options, '']);
    }

    function removeOption(index: number) {
        if (form.data.options.length <= 2) {
            return;
        }
        form.setData(
            'options',
            form.data.options.filter((_, i) => i !== index),
        );
    }

    function setOption(index: number, value: string) {
        const next = [...form.data.options];
        next[index] = value;
        form.setData('options', next);
    }

    function changeStatus(poll: PollRow, action: 'open' | 'close') {
        router.post(`/events/${event.id}/polls/${poll.id}/${action}`, {}, { preserveScroll: true });
    }

    return (
        <EventLayout title="Sondages en direct" eyebrow={event.title}>
            <Head title={`Sondages en direct — ${event.title}`} />

            <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                Posez une question à choix unique, ouvrez-la pendant l'événement : les participants votent depuis leur
                application, les résultats se mettent à jour au fil des réponses.
            </p>

            <form onSubmit={submit} className={`mb-8 ${CARD}`}>
                <h2 className="mb-4 font-serif text-lg italic">Nouveau sondage</h2>

                <InputLabel htmlFor="question">Question</InputLabel>
                <TextInput
                    id="question"
                    maxLength={280}
                    value={form.data.question}
                    onChange={(e) => form.setData('question', e.target.value)}
                />
                <InputError message={form.errors.question} />

                <div className="mt-4 space-y-2">
                    <InputLabel>Réponses possibles</InputLabel>
                    {form.data.options.map((option, index) => (
                        <div key={index} className="flex items-center gap-2">
                            <TextInput
                                maxLength={120}
                                value={option}
                                onChange={(e) => setOption(index, e.target.value)}
                                placeholder={`Réponse ${index + 1}`}
                            />
                            {form.data.options.length > 2 && (
                                <button
                                    type="button"
                                    onClick={() => removeOption(index)}
                                    className="shrink-0 text-sm text-ink-soft hover:text-danger"
                                    aria-label="Retirer cette réponse"
                                >
                                    &times;
                                </button>
                            )}
                        </div>
                    ))}
                    <InputError message={form.errors.options} />
                    {form.data.options.length < 8 && (
                        <button type="button" onClick={addOption} className="text-sm text-ink underline hover:no-underline">
                            + Ajouter une réponse
                        </button>
                    )}
                </div>

                <Button type="submit" disabled={form.processing} className="mt-5 w-auto px-6 py-2">
                    {form.processing ? 'Création…' : 'Créer le sondage'}
                </Button>
            </form>

            {polls.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun sondage pour l'instant</p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {polls.map((poll) => (
                        <li key={poll.id} className={CARD}>
                            <div className="mb-3 flex flex-wrap items-center gap-3">
                                <p className="text-ink">{poll.question}</p>
                                <Badge variant={poll.status === 'open' ? 'success' : poll.status === 'closed' ? 'neutral' : 'neutral'}>
                                    {poll.statusLabel}
                                </Badge>
                            </div>

                            <ul className="mb-4 space-y-1.5">
                                {poll.options.map((option) => (
                                    <li key={option.id} className="relative overflow-hidden rounded-pill bg-bg-alt px-4 py-1.5 text-sm ring-1 ring-line">
                                        <div className="absolute inset-y-0 left-0 bg-accent/15" style={{ width: `${option.percent}%` }} />
                                        <div className="relative flex items-center justify-between gap-3">
                                            <span className="text-ink">{option.label}</span>
                                            <span className="tabular-nums text-ink-soft">
                                                {option.voteCount} · {option.percent}%
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="flex gap-4 text-sm">
                                {poll.status !== 'open' && (
                                    <button type="button" onClick={() => changeStatus(poll, 'open')} className="text-ink underline hover:no-underline">
                                        {poll.status === 'draft' ? 'Ouvrir' : 'Rouvrir'}
                                    </button>
                                )}
                                {poll.status === 'open' && (
                                    <button type="button" onClick={() => changeStatus(poll, 'close')} className="text-ink underline hover:no-underline">
                                        Fermer
                                    </button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}
