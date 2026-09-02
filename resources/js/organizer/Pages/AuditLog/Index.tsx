import { Head, Link } from '@inertiajs/react';
import OrganizerLayout from '../../Layouts/OrganizerLayout';
import Table from '../../Components/Table';

interface AuditLogRow {
    id: number;
    action: string;
    causer_name: string | null;
    subject_type: string | null;
    ip_address: string | null;
    created_at: string | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export default function Index({ logs }: { logs: Paginated<AuditLogRow> }) {
    return (
        <OrganizerLayout title="Journal d'audit" eyebrow="Sécurité">
            <Head title="Journal d'audit" />

            <div className="mb-6 flex justify-end">
                <a
                    href="/audit-log/export"
                    className="inline-flex min-h-11 items-center gap-2.5 rounded-pill bg-ink px-6 py-3 font-sans text-sm font-medium text-bg"
                >
                    Exporter en CSV
                </a>
            </div>

            <Table
                rowKey={(log) => log.id}
                emptyMessage="Aucune entrée pour l'instant."
                columns={[
                    {
                        key: 'date',
                        header: 'Date',
                        render: (log) => (log.created_at ? new Date(log.created_at).toLocaleString('fr-FR') : '—'),
                    },
                    { key: 'action', header: 'Action', render: (log) => <span className="text-ink">{log.action}</span> },
                    { key: 'causer', header: 'Auteur', render: (log) => log.causer_name ?? '—' },
                    { key: 'subject', header: 'Sujet', render: (log) => log.subject_type ?? '—' },
                    { key: 'ip', header: 'Adresse IP', render: (log) => log.ip_address ?? '—' },
                ]}
                rows={logs.data}
            />

            {logs.last_page > 1 && (
                <nav className="mt-6 flex gap-2">
                    {logs.links.map((link, index) => (
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
