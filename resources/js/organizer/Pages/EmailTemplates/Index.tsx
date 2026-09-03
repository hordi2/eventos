import { Head, Link, router } from '@inertiajs/react';
import Table from '../../Components/Table';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface TemplateRow {
    id: number;
    name: string;
    subject: string;
    updated_at: string;
}

export default function Index({ templates }: { templates: TemplateRow[] }) {
    function destroy(template: TemplateRow) {
        if (confirm(`Supprimer le modèle « ${template.name} » ?`)) {
            router.delete(`/email-templates/${template.id}`);
        }
    }

    return (
        <OrganizerLayout title="Modèles d'e-mails" eyebrow="Communications">
            <Head title="Modèles d'e-mails" />

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
