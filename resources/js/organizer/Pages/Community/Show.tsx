import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface Post {
    id: number;
    body: string;
    author: string;
    created_at: string | null;
}

interface Topic {
    id: number;
    title: string;
    body: string;
    category_label: string;
    author: string;
    created_at: string | null;
}

export default function Show({ topic, posts }: { topic: Topic; posts: Post[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        post(`/community/${topic.id}/reponses`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <OrganizerLayout title={topic.title} eyebrow={topic.category_label}>
            <Head title={topic.title} />

            <div className="mb-10 rounded-card border border-line p-6">
                <p className="mb-4 text-sm text-ink-soft">
                    Par {topic.author}
                    {topic.created_at && ` · ${new Date(topic.created_at).toLocaleDateString('fr-FR')}`}
                </p>
                <p className="whitespace-pre-wrap text-ink">{topic.body}</p>
            </div>

            <h2 className="mb-4 font-serif text-xl italic">
                {posts.length} {posts.length > 1 ? 'réponses' : 'réponse'}
            </h2>

            <ul className="mb-10 space-y-4">
                {posts.map((reply) => (
                    <li key={reply.id} className="rounded-card bg-bg-deep p-5">
                        <p className="mb-2 text-sm text-ink-soft">
                            {reply.author}
                            {reply.created_at && ` · ${new Date(reply.created_at).toLocaleDateString('fr-FR')}`}
                        </p>
                        <p className="whitespace-pre-wrap text-ink">{reply.body}</p>
                    </li>
                ))}
            </ul>

            <form onSubmit={submit} className="max-w-xl">
                <h2 className="mb-3 font-serif text-xl italic">Répondre</h2>
                <textarea
                    rows={5}
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    className="mb-2 w-full rounded-control border border-line px-3 py-2.5 text-sm text-ink"
                    placeholder="Votre réponse..."
                />
                <InputError message={errors.body} />
                <Button type="submit" disabled={processing} className="mt-3">
                    Publier la réponse
                </Button>
            </form>
        </OrganizerLayout>
    );
}
