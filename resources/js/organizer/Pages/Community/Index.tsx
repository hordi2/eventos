import { Head, Link } from '@inertiajs/react';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface TopicRow {
    id: number;
    title: string;
    category: string;
    category_label: string;
    author: string;
    posts_count: number;
    created_at: string | null;
}

interface CategoryOption {
    value: string;
    label: string;
    description: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    topics: Paginated<TopicRow>;
    categories: CategoryOption[];
    activeCategory: string | null;
}

export default function Index({ topics, categories, activeCategory }: Props) {
    return (
        <OrganizerLayout title="Communauté" eyebrow="Échangez avec d'autres organisateurs">
            <Head title="Communauté" />

            <div className="mb-10 flex flex-wrap items-center justify-between gap-4">
                <nav className="flex flex-wrap gap-2">
                    <Link
                        href="/community"
                        className={`rounded-pill px-4 py-2 text-sm ${
                            !activeCategory ? 'bg-bg-deep text-ink' : 'text-ink-soft hover:text-ink'
                        }`}
                    >
                        Tous les sujets
                    </Link>
                    {categories.map((category) => (
                        <Link
                            key={category.value}
                            href={`/community?category=${category.value}`}
                            className={`rounded-pill px-4 py-2 text-sm ${
                                activeCategory === category.value ? 'bg-bg-deep text-ink' : 'text-ink-soft hover:text-ink'
                            }`}
                        >
                            {category.label}
                        </Link>
                    ))}
                </nav>

                <Link href="/community/nouveau-sujet" className="rounded-pill bg-ink px-6 py-2.5 text-sm text-bg">
                    Nouveau sujet
                </Link>
            </div>

            {topics.data.length === 0 ? (
                <p className="text-ink-soft">Aucun sujet pour l'instant — soyez le premier à en ouvrir un.</p>
            ) : (
                <ul className="space-y-3">
                    {topics.data.map((topic) => (
                        <li key={topic.id}>
                            <Link
                                href={`/community/${topic.id}`}
                                className="block rounded-card border border-line p-5 hover:border-ink"
                            >
                                <div className="mb-2 flex items-center gap-2">
                                    <span className="rounded-pill bg-bg-deep px-2.5 py-1 font-label text-[11px] tracking-[0.08em] text-ink-soft uppercase">
                                        {topic.category_label}
                                    </span>
                                </div>
                                <p className="mb-1 font-medium text-ink">{topic.title}</p>
                                <p className="text-sm text-ink-soft">
                                    Par {topic.author}
                                    {topic.created_at && ` · ${new Date(topic.created_at).toLocaleDateString('fr-FR')}`}
                                    {' · '}
                                    {topic.posts_count} {topic.posts_count > 1 ? 'réponses' : 'réponse'}
                                </p>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {topics.last_page > 1 && (
                <nav className="mt-8 flex gap-2">
                    {topics.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            className={`rounded-pill px-3.5 py-1.5 text-sm ${
                                link.active ? 'bg-ink text-bg' : 'text-ink-soft hover:text-ink'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </OrganizerLayout>
    );
}
