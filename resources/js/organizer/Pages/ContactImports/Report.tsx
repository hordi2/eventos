import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import Badge from '../../Components/Badge';
import Table from '../../Components/Table';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface ImportSummary {
    id: number;
    original_filename: string;
    status: string;
    total_rows: number | null;
    accepted_count: number;
    duplicate_count: number;
    rejected_count: number;
}

interface RowReport {
    row_number: number;
    status: string;
    reason: string | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const STATUS_LABELS: Record<string, string> = {
    mapping: 'Correspondance des colonnes',
    queued: 'En attente',
    processing: 'En cours de traitement',
    completed: 'Terminé',
    failed: 'Échec',
};

const ROW_STATUS_LABELS: Record<string, string> = {
    accepted: 'Créé',
    merged: 'Fusionné',
    skipped: 'Ignoré',
    rejected: 'Rejeté',
};

export default function Report({ import: contactImport, rows }: { import: ImportSummary; rows: Paginated<RowReport> }) {
    const isRunning = contactImport.status === 'queued' || contactImport.status === 'processing';

    useEffect(() => {
        if (!isRunning) {
            return;
        }

        const interval = setInterval(() => {
            router.reload({ only: ['import', 'rows'] });
        }, 2000);

        return () => clearInterval(interval);
    }, [isRunning]);

    return (
        <OrganizerLayout
            title="Rapport d'import"
            eyebrow={contactImport.original_filename}
            nav={
                <Link href="/contacts" className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase hover:text-ink">
                    Tous les contacts
                </Link>
            }
        >
            <Head title="Rapport d'import" />

            <div className="mb-8 flex flex-wrap items-center gap-3">
                <Badge variant={contactImport.status === 'failed' ? 'danger' : contactImport.status === 'completed' ? 'success' : 'neutral'}>
                    {STATUS_LABELS[contactImport.status] ?? contactImport.status}
                </Badge>
                {isRunning && <span className="text-sm text-ink-soft">Traitement en file d'attente, la page se met à jour toute seule…</span>}
            </div>

            <div className="mb-10 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div className="rounded-card border border-line p-4">
                    <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Lignes</p>
                    <p className="mt-1 text-2xl text-ink">{contactImport.total_rows ?? '—'}</p>
                </div>
                <div className="rounded-card border border-line p-4">
                    <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Acceptées</p>
                    <p className="mt-1 text-2xl text-ink">{contactImport.accepted_count}</p>
                </div>
                <div className="rounded-card border border-line p-4">
                    <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Doublons</p>
                    <p className="mt-1 text-2xl text-ink">{contactImport.duplicate_count}</p>
                </div>
                <div className="rounded-card border border-line p-4">
                    <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Rejetées</p>
                    <p className="mt-1 text-2xl text-ink">{contactImport.rejected_count}</p>
                </div>
            </div>

            <Table
                rowKey={(row) => row.row_number}
                emptyMessage="Aucune ligne traitée pour l'instant."
                columns={[
                    { key: 'row', header: 'Ligne', render: (row) => row.row_number },
                    {
                        key: 'status',
                        header: 'Statut',
                        render: (row) => (
                            <Badge variant={row.status === 'rejected' ? 'danger' : row.status === 'accepted' ? 'success' : 'neutral'}>
                                {ROW_STATUS_LABELS[row.status] ?? row.status}
                            </Badge>
                        ),
                    },
                    { key: 'reason', header: 'Motif', render: (row) => row.reason ?? '—' },
                ]}
                rows={rows.data}
            />

            {rows.last_page > 1 && (
                <nav className="mt-6 flex gap-2">
                    {rows.links.map((link, index) => (
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
