import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '../../Components/Badge';
import EventIcon from '../../Components/EventIcon';
import EventLayout from '../../Layouts/EventLayout';

interface FormRow {
    id: number;
    name: string;
    isDefault: boolean;
    status: 'draft' | 'published' | 'changes';
    statusLabel: string;
    responses: number;
    url: string;
    isLive: boolean;
    editUrl: string;
    previewUrl: string;
    canDelete: boolean;
}

interface FormsPageProps {
    event: { id: number; title: string };
    forms: FormRow[];
    canCreate: boolean;
}

const SMALL_ACTION = 'inline-flex min-h-9 items-center gap-2 rounded-pill border border-line px-4 py-1.5 text-sm text-ink hover:border-ink';

function plural(count: number, one: string, many: string): string {
    return `${count} ${count > 1 ? many : one}`;
}

/**
 * Les formulaires d'un événement : un par public (invités, bénévoles,
 * exposants…), chacun avec son lien. Celui « par défaut » répond au lien de
 * l'événement et à ses invitations.
 */
export default function FormsIndex({ event, forms, canCreate }: FormsPageProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [copiedId, setCopiedId] = useState<number | null>(null);

    async function copy(form: FormRow) {
        try {
            await navigator.clipboard.writeText(form.url);
            setCopiedId(form.id);
            window.setTimeout(() => setCopiedId((current) => (current === form.id ? null : current)), 2000);
        } catch {
            window.prompt('Copiez ce lien :', form.url);
        }
    }

    function makeDefault(form: FormRow) {
        router.post(`/forms/${form.id}/default`, {}, { preserveScroll: true });
    }

    function remove(form: FormRow) {
        if (window.confirm(`Supprimer le formulaire « ${form.name} » ? Son lien ne fonctionnera plus.`)) {
            router.delete(`/forms/${form.id}`, { preserveScroll: true });
        }
    }

    return (
        <EventLayout title="Formulaires">
            <Head title={`Formulaires — ${event.title}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Un formulaire par public : invités, bénévoles, exposants… Chacun a son propre lien. Le formulaire par défaut répond au lien de
                    l'événement et aux invitations envoyées depuis la liste d'invités.
                </p>
                {canCreate && (
                    <Link
                        href={`/events/${event.id}/form/create`}
                        className="inline-flex min-h-10 items-center gap-2 rounded-pill bg-ink px-6 py-2 text-sm font-medium text-bg hover:opacity-90"
                    >
                        + Nouveau formulaire
                    </Link>
                )}
            </div>

            {errors.form && (
                <div role="alert" className="mb-5 rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">
                    {errors.form}
                </div>
            )}

            {forms.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun formulaire pour l'instant</p>
                    <p className="text-sm text-ink-soft">Créez le formulaire d'inscription : c'est lui que vos invités rempliront.</p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {forms.map((form) => (
                        <li key={form.id} className="rounded-card border border-line bg-bg p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="mb-1.5 flex flex-wrap items-center gap-2">
                                        <Badge variant={form.status === 'published' ? 'success' : 'neutral'}>{form.statusLabel}</Badge>
                                        {form.isDefault && <Badge>Par défaut</Badge>}
                                    </div>
                                    <h2 className="truncate text-lg text-ink">{form.name}</h2>
                                    <p className="text-sm text-ink-soft">{plural(form.responses, 'réponse', 'réponses')}</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Link href={form.editUrl} className={SMALL_ACTION}>
                                        <EventIcon name="form" className="h-4 w-4" />
                                        Modifier
                                    </Link>
                                    <a href={form.previewUrl} target="_blank" rel="noopener" className={SMALL_ACTION}>
                                        <EventIcon name="eye" className="h-4 w-4" />
                                        Prévisualiser
                                    </a>
                                </div>
                            </div>

                            <div className="mt-4 flex flex-col gap-2 border-t border-line pt-4 sm:flex-row sm:items-center">
                                <code className="min-w-0 flex-1 truncate rounded-control bg-bg-alt px-3 py-2 text-xs text-ink-soft">{form.url}</code>
                                {form.isLive ? (
                                    <button type="button" onClick={() => copy(form)} className={SMALL_ACTION}>
                                        <EventIcon name={copiedId === form.id ? 'check' : 'link'} className="h-4 w-4" />
                                        {copiedId === form.id ? 'Lien copié' : 'Copier le lien'}
                                    </button>
                                ) : (
                                    <span className="text-xs text-ink-soft">En ligne une fois le formulaire et l'événement publiés.</span>
                                )}
                            </div>

                            {!form.isDefault && (
                                <div className="mt-3 flex flex-wrap gap-4 text-sm">
                                    <button type="button" onClick={() => makeDefault(form)} className="text-ink underline hover:no-underline">
                                        Utiliser par défaut
                                    </button>
                                    {form.canDelete && (
                                        <button type="button" onClick={() => remove(form)} className="text-danger underline hover:no-underline">
                                            Supprimer
                                        </button>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}
