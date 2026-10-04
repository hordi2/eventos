import { Head, Link, router } from '@inertiajs/react';
import Table from '../../Components/Table';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface TemplateRow {
    id: number;
    name: string;
    subject: string;
    updated_at: string;
}

interface AgencyTemplateRow {
    id: number;
    name: string;
    subject: string;
    agency: string;
}

interface Props {
    templates: TemplateRow[];
    agencyTemplates: AgencyTemplateRow[];
}

export default function Index({ templates, agencyTemplates }: Props) {
    function destroy(template: TemplateRow) {
        if (confirm(`Supprimer le modèle « ${template.name} » ?`)) {
            router.delete(`/email-templates/${template.id}`);
        }
    }

    return (
        <OrganizerLayout title="Modèles d'e-mails" eyebrow="Communications">
            <Head title="Modèles d'e-mails" />

            {/* Modèles partagés par l'agence qui gère ce compte (D10). */}
            {agencyTemplates.length > 0 && (
                <div className="mb-8 rounded-card bg-bg p-5 ring-1 ring-line">
                    <h2 className="mb-1 font-serif text-lg italic">Modèles de {agencyTemplates[0].agency}</h2>
                    <p className="mb-4 text-sm text-ink-soft">
                        Reprenez-en un chez vous : la copie vous appartiendra, vous pourrez l'adapter sans toucher à
                        l'original.
                    </p>
                    <ul className="space-y-2">
                        {agencyTemplates.map((template) => (
                            <li key={template.id} className="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                                <span>
                                    <span className="block text-ink">{template.name}</span>
                                    <span className="block text-sm text-ink-soft">{template.subject}</span>
                                </span>
                                <button
                                    type="button"
                                    onClick={() => router.post(`/email-templates/${template.id}/importer`)}
                                    className="inline-flex min-h-9 items-center rounded-pill border border-line px-4 py-1.5 text-sm text-ink hover:border-ink"
                                >
                                    Reprendre ce modèle
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mb-6 flex justify-end">
                <Link
                    href="/email-templates/create"
                    className="inline-flex min-h-11 items-center gap-2.5 rounded-pill bg-ink px-6 py-3 font-sans text-sm font-medium text-bg"
                >
                    Créer un modèle
                </Link>
            </div>

            <Table
                rowKey={(template) => template.id}
                emptyMessage="Aucun modèle pour l'instant."
                columns={[
                    {
                        key: 'name',
                        header: 'Nom',
                        render: (template) => (
                            <Link href={`/email-templates/${template.id}/edit`} className="text-ink underline-offset-2 hover:underline">
                                {template.name}
                            </Link>
                        ),
                    },
                    { key: 'subject', header: 'Objet', render: (template) => template.subject },
                    {
                        key: 'updated_at',
                        header: 'Modifié',
                        render: (template) => new Date(template.updated_at).toLocaleDateString('fr-FR'),
                    },
                    {
                        key: 'actions',
                        header: '',
                        render: (template) => (
                            <div className="flex justify-end">
                                <button type="button" onClick={() => destroy(template)} className="text-sm text-danger hover:opacity-80">
                                    Supprimer
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={templates}
            />
        </OrganizerLayout>
    );
}
