import { Head, router } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import EventLayout from '../../Layouts/EventLayout';

interface Message {
    id: number;
    author: string;
    message: string;
    isPublished: boolean;
    writtenAt: string;
}

interface GuestBookPageProps {
    event: { id: number; title: string };
    messages: Message[];
}

const CARD = 'rounded-card border border-line bg-bg p-5';

/**
 * Livre d'or (D1) : les mots laissés par les invités. Ils paraissent
 * aussitôt sur la page publique ; c'est ici qu'on en masque un ou qu'on
 * le retire.
 */
export default function GuestBook({ event, messages }: GuestBookPageProps) {
    const hidden = messages.filter((message) => !message.isPublished).length;

    return (
        <EventLayout title="Livre d'or" eyebrow={event.title}>
            <Head title={`Livre d'or — ${event.title}`} />

            <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                Les mots de vos invités paraissent sur votre page dès qu'ils sont écrits. Masquez celui qui n'a rien à y
                faire, ou retirez-le tout à fait.
                {hidden > 0 && ` ${hidden} message${hidden > 1 ? 's sont masqués' : ' est masqué'}.`}
            </p>

            {messages.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun mot pour l'instant</p>
                    <p className="text-sm text-ink-soft">
                        Ajoutez le bloc « Livre d'or » à votre page pour que vos invités puissent en laisser un.
                    </p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {messages.map((message) => (
                        <li key={message.id} className={CARD}>
                            <div className="mb-3 flex flex-wrap items-center gap-3">
                                <p className="text-ink">{message.author}</p>
                                <span className="text-sm text-ink-soft">{message.writtenAt}</span>
                                {!message.isPublished && <Badge variant="danger">Masqué</Badge>}
                            </div>

                            <p className="mb-4 whitespace-pre-line text-ink italic">« {message.message} »</p>

                            <div className="flex gap-4 text-sm">
                                <button
                                    type="button"
                                    onClick={() => router.patch(`/events/${event.id}/livre-d-or/${message.id}`, {}, { preserveScroll: true })}
                                    className="text-ink underline hover:no-underline"
                                >
                                    {message.isPublished ? 'Masquer' : 'Afficher'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (window.confirm(`Retirer le mot de ${message.author} ?`)) {
                                            router.delete(`/events/${event.id}/livre-d-or/${message.id}`, { preserveScroll: true });
                                        }
                                    }}
                                    className="text-danger underline hover:no-underline"
                                >
                                    Retirer
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}
