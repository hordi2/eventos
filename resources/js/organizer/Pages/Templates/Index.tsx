import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface TemplateRow {
    id: number;
    name: string;
    summary: string | null;
    category: string;
    publisher: string | null;
    mine: boolean;
    usesCount: number;
    fieldCount: number;
    blockCount: number;
}

interface Props {
    templates: TemplateRow[];
    categories: { value: string; label: string }[];
    category: string;
    myEvents: { id: number; title: string }[];
}

/**
 * Bibliothèque de modèles communautaire (D11) : ce que les organisateurs ont
 * publié, et de quoi monter un événement en une fois.
 */
export default function Index({ templates, categories, category, myEvents }: Props) {
    const [open, setOpen] = useState<number | null>(null);
    const form = useForm({ title: '', start_at: '' });
    const publishForm = useForm({ event_id: String(myEvents[0]?.id ?? ''), name: '', summary: '' });

    function publish(event: FormEvent) {
        event.preventDefault();
        publishForm.post(`/events/${publishForm.data.event_id}/modele`, {
            onSuccess: () => publishForm.reset('name', 'summary'),
        });
    }

    function use(template: TemplateRow, event: FormEvent) {
        event.preventDefault();
        form.post(`/modeles/${template.id}/utiliser`);
    }

    return (
        <OrganizerLayout title="Bibliothèque de modèles" eyebrow="Communauté">
            <Head title="Bibliothèque de modèles" />

            <p className="mb-6 text-sm text-ink-soft">
                Des événements complets — formulaire, page, réglages — publiés par d'autres organisateurs. Ils ne
                contiennent ni invités, ni dates, ni montants.
            </p>

            {myEvents.length > 0 && (
                <form onSubmit={publish} className="mb-8 rounded-card bg-bg p-5 ring-1 ring-line">
                    <h2 className="mb-1 font-serif text-lg italic">Publier un de mes événements</h2>
                    <p className="mb-5 text-sm text-ink-soft">
                        Sa structure seulement : le formulaire, la page et les réglages. Ni vos invités, ni vos dates, ni
                        vos montants, ni vos images.
                    </p>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel htmlFor="event_id">Événement</InputLabel>
                            <Select
                                id="event_id"
                                value={publishForm.data.event_id}
                                onChange={(event) => publishForm.setData('event_id', event.target.value)}
                            >
                                {myEvents.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.title}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <InputLabel htmlFor="template_name">Nom du modèle</InputLabel>
                            <TextInput
                                id="template_name"
                                value={publishForm.data.name}
                                onChange={(event) => publishForm.setData('name', event.target.value)}
                                maxLength={120}
                            />
                            <InputError message={publishForm.errors.name} />
                        </div>
                        <div>
                            <InputLabel htmlFor="template_summary">En une phrase</InputLabel>
                            <TextInput
                                id="template_summary"
                                value={publishForm.data.summary}
                                onChange={(event) => publishForm.setData('summary', event.target.value)}
                                maxLength={500}
                            />
                        </div>
                    </div>

                    <Button type="submit" disabled={publishForm.processing} className="mt-5 w-auto px-6 py-2">
                        {publishForm.processing ? 'Publication…' : 'Publier dans la bibliothèque'}
                    </Button>
                </form>
            )}

            <div className="mb-8 w-64">
                <InputLabel htmlFor="categorie">Filtrer par type</InputLabel>
                <Select
                    id="categorie"
                    value={category}
                    onChange={(event) =>
                        router.get('/modeles', event.target.value ? { categorie: event.target.value } : {}, { preserveState: true })
                    }
                >
                    <option value="">Tous les types</option>
                    {categories.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </div>

            {templates.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun modèle</p>
                    <p className="text-sm text-ink-soft">Publiez le vôtre depuis la liste de contrôle d'un événement.</p>
                </div>
            ) : (
                <ul className="space-y-3">
                    {templates.map((template) => (
                        <li key={template.id} className="rounded-card bg-bg p-5 ring-1 ring-line">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium text-ink">{template.name}</p>
                                    {template.summary && <p className="mt-1 text-sm text-ink-soft">{template.summary}</p>}
                                    <p className="mt-2 text-xs text-ink-soft">
                                        {template.category} · {template.fieldCount} questions · {template.blockCount} blocs ·
                                        utilisé {template.usesCount} fois
                                        {template.publisher && ` · par ${template.publisher}`}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    {template.mine && (
                                        <button
                                            type="button"
                                            onClick={() => router.patch(`/modeles/${template.id}`, { is_published: false })}
                                            className="text-sm text-danger underline hover:no-underline"
                                        >
                                            Retirer
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setOpen(open === template.id ? null : template.id)}
                                        className="inline-flex min-h-9 items-center rounded-pill border border-line px-4 py-1.5 text-sm text-ink hover:border-ink"
                                    >
                                        Utiliser ce modèle
                                    </button>
                                </div>
                            </div>

                            {open === template.id && (
                                <form
                                    onSubmit={(event) => use(template, event)}
                                    className="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-[1fr_auto_auto]"
                                >
                                    <div>
                                        <InputLabel htmlFor={`title_${template.id}`}>Titre de votre événement</InputLabel>
                                        <TextInput
                                            id={`title_${template.id}`}
                                            value={form.data.title}
                                            onChange={(event) => form.setData('title', event.target.value)}
                                        />
                                        <InputError message={form.errors.title} />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor={`start_${template.id}`}>Quand</InputLabel>
                                        <TextInput
                                            id={`start_${template.id}`}
                                            type="datetime-local"
                                            value={form.data.start_at}
                                            onChange={(event) => form.setData('start_at', event.target.value)}
                                        />
                                        <InputError message={form.errors.start_at} />
                                    </div>
                                    <Button type="submit" disabled={form.processing} className="w-auto self-end px-6 py-2">
                                        {form.processing ? 'Création…' : 'Créer'}
                                    </Button>
                                </form>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </OrganizerLayout>
    );
}
