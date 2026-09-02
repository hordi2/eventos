import { Head, Link, usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';
import Badge from '../Components/Badge';
import OrganizerLayout from '../Layouts/OrganizerLayout';
import { type SharedProps } from '../types';

interface EventSummary {
    id: number;
    title: string;
    status: string;
    start_at_formatted: string;
}

interface ImportSummary {
    id: number;
    filename: string;
    status: string;
}

interface Services {
    contacts: { canView: boolean; canCreate: boolean; count: number };
    events: { canView: boolean; canCreate: boolean; count: number; recent: EventSummary[] };
    imports: { canView: boolean; count: number; recent: ImportSummary[] };
    auditLog: { canView: boolean; count: number };
}

const EVENT_STATUS_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    archived: 'Archivé',
};

const IMPORT_STATUS_LABELS: Record<string, string> = {
    mapping: 'Correspondance des colonnes',
    queued: 'En attente',
    processing: 'En cours',
    completed: 'Terminé',
    failed: 'Échec',
};

function ServiceCard({
    title,
    count,
    countLabel,
    actions,
    children,
}: {
    title: string;
    count: number;
    countLabel: string;
    actions: ReactNode;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-card border border-line bg-bg p-6">
            <div className="flex items-baseline justify-between gap-4">
                <h2 className="font-serif text-xl font-medium text-ink italic">{title}</h2>
                <p className="shrink-0 text-right">
                    <span className="text-2xl text-ink">{count}</span>{' '}
                    <span className="font-label text-xs tracking-[0.08em] text-ink-soft uppercase">{countLabel}</span>
                </p>
            </div>

            {children && <div className="mt-5 space-y-3">{children}</div>}

            <div className="mt-6 flex flex-wrap gap-4 border-t border-line pt-5">{actions}</div>
        </div>
    );
}

function CardAction({ href, primary = false, children }: { href: string; primary?: boolean; children: ReactNode }) {
    return (
        <Link
            href={href}
            className={
                primary
                    ? 'inline-flex min-h-9 items-center rounded-pill bg-ink px-4 py-2 font-sans text-sm font-medium text-bg'
                    : 'font-label text-xs tracking-[0.1em] text-ink-soft uppercase hover:text-ink'
            }
        >
            {children}
        </Link>
    );
}

export default function Dashboard({ services }: { services: Services }) {
    const { auth } = usePage<SharedProps>().props;

    return (
        <OrganizerLayout title={`Bienvenue, ${auth.user?.name}`} eyebrow="Tableau de bord">
            <Head title="Tableau de bord" />

            <p className="max-w-lg text-ink-soft">
                Vue d'ensemble de ton organisation : les services déjà en place et où tu en es sur chacun.
            </p>

            <div className="mt-10 grid gap-6 sm:grid-cols-2">
                {services.contacts.canView && (
                    <ServiceCard
                        title="Contacts"
                        count={services.contacts.count}
                        countLabel="contacts"
                        actions={
                            <>
                                <CardAction href="/contacts" primary>
                                    Voir tous les contacts
                                </CardAction>
                                {services.contacts.canCreate && <CardAction href="/contacts/create">Ajouter un contact</CardAction>}
                                {services.contacts.canCreate && <CardAction href="/contact-imports/create">Importer des contacts</CardAction>}
                            </>
                        }
                    />
                )}

                {services.events.canView && (
                    <ServiceCard
                        title="Événements"
                        count={services.events.count}
                        countLabel="événements"
                        actions={services.events.canCreate && <CardAction href="/events/create" primary>Créer un événement</CardAction>}
                    >
                        {services.events.recent.length === 0 ? (
                            <p className="text-sm text-ink-soft">Aucun événement pour l'instant.</p>
                        ) : (
                            services.events.recent.map((event) => (
                                <Link
                                    key={event.id}
                                    href={`/events/${event.id}/edit`}
                                    className="flex items-center justify-between gap-3 text-sm hover:text-ink"
                                >
                                    <span className="truncate text-ink">{event.title}</span>
                                    <span className="flex shrink-0 items-center gap-2 text-ink-soft">
                                        {event.start_at_formatted}
                                        <Badge variant={event.status === 'published' ? 'success' : 'neutral'}>
                                            {EVENT_STATUS_LABELS[event.status] ?? event.status}
                                        </Badge>
                                    </span>
                                </Link>
                            ))
                        )}
                    </ServiceCard>
                )}

                {services.imports.canView && (
                    <ServiceCard
                        title="Imports de contacts"
                        count={services.imports.count}
                        countLabel="imports"
                        actions={
                            <CardAction href="/contact-imports/create" primary>
                                Importer un fichier
                            </CardAction>
                        }
                    >
                        {services.imports.recent.length === 0 ? (
                            <p className="text-sm text-ink-soft">Aucun import pour l'instant.</p>
                        ) : (
                            services.imports.recent.map((contactImport) => (
                                <Link
                                    key={contactImport.id}
                                    href={`/contact-imports/${contactImport.id}`}
                                    className="flex items-center justify-between gap-3 text-sm hover:text-ink"
                                >
                                    <span className="truncate text-ink">{contactImport.filename}</span>
                                    <Badge
                                        variant={
                                            contactImport.status === 'failed'
                                                ? 'danger'
                                                : contactImport.status === 'completed'
                                                  ? 'success'
                                                  : 'neutral'
                                        }
                                    >
                                        {IMPORT_STATUS_LABELS[contactImport.status] ?? contactImport.status}
                                    </Badge>
                                </Link>
                            ))
                        )}
                    </ServiceCard>
                )}

                {services.auditLog.canView && (
                    <ServiceCard
                        title="Journal d'audit"
                        count={services.auditLog.count}
                        countLabel="entrées"
                        actions={
                            <CardAction href="/audit-log" primary>
                                Voir le journal
                            </CardAction>
                        }
                    >
                        <p className="text-sm text-ink-soft">Export, suppression, remboursement, changement de permission… tout est journalisé.</p>
                    </ServiceCard>
                )}
            </div>
        </OrganizerLayout>
    );
}
