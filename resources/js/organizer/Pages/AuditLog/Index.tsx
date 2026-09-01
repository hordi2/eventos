import { Head, Link } from '@inertiajs/react';

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
        <div className="min-h-screen bg-bg-alt">
            <Head title="Journal d'audit" />

            <header className="border-b border-line bg-bg">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                    <img src="/images/logo.png" alt="Itaza Invitation" className="h-9 w-auto" />
                    <Link href="/dashboard" className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase hover:text-ink">
                        Tableau de bord
                    </Link>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-6 py-16">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="font-label text-xs tracking-[0.28em] text-accent uppercase">Sécurité</p>
                        <h1 className="mt-4 font-serif text-3xl font-medium text-ink italic">Journal d'audit</h1>
                    </div>

                    <a
                        href="/audit-log/export"
                        className="inline-flex items-center gap-2.5 rounded-full bg-ink px-6 py-3 font-sans text-sm font-medium text-white"
                    >
                        Exporter en CSV
                    </a>
                </div>

                <div className="mt-10 overflow-x-auto rounded-2xl bg-bg ring-1 ring-line">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-line font-label text-xs tracking-[0.1em] text-ink-soft uppercase">
                                <th className="px-6 py-4">Date</th>
                                <th className="px-6 py-4">Action</th>
                                <th className="px-6 py-4">Auteur</th>
                                <th className="px-6 py-4">Sujet</th>
                                <th className="px-6 py-4">Adresse IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-b border-line last:border-0">
                                    <td className="px-6 py-4 text-ink-soft">
                                        {log.created_at ? new Date(log.created_at).toLocaleString('fr-FR') : '—'}
                                    </td>
                                    <td className="px-6 py-4 text-ink">{log.action}</td>
                                    <td className="px-6 py-4 text-ink-soft">{log.causer_name ?? '—'}</td>
                                    <td className="px-6 py-4 text-ink-soft">{log.subject_type ?? '—'}</td>
                                    <td className="px-6 py-4 text-ink-soft">{log.ip_address ?? '—'}</td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-10 text-center text-ink-soft">
                                        Aucune entrée pour l'instant.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {logs.last_page > 1 && (
                    <nav className="mt-6 flex gap-2">
                        {logs.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                className={`rounded-full px-3.5 py-1.5 text-sm ${
                                    link.active ? 'bg-ink text-white' : 'text-ink-soft hover:text-ink'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </main>
        </div>
    );
}
