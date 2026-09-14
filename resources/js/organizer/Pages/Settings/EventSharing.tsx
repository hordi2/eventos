import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CollaboratorModal, {
    type PermissionOption,
    type ShareableEvent,
    type SharedCollaborator,
} from '../../Components/CollaboratorModal';
import OrganizerLayout from '../../Layouts/OrganizerLayout';
import { type SharedProps } from '../../types';

interface Props {
    collaborators: SharedCollaborator[];
    events: ShareableEvent[];
    permissionOptions: PermissionOption[];
}

type Dialog = { collaborator: SharedCollaborator | null } | null;

const STATUS_MESSAGES: Record<string, string> = {
    'collaborator-invited': 'Invitation envoyée.',
    'collaborator-updated': 'Accès mis à jour.',
    'collaborator-invitation-resent': 'Invitation renvoyée : le lien du précédent e-mail ne fonctionne plus.',
    'collaborator-removed': 'Collaborateur retiré : il n’a plus accès à vos événements.',
};

const STATUS_BADGES: Record<SharedCollaborator['status'], { label: string; className: string }> = {
    active: { label: 'Actif', className: 'bg-success-bg text-success' },
    pending: { label: 'Invitation envoyée', className: 'bg-bg-alt text-ink-soft' },
    expired: { label: 'Invitation expirée', className: 'bg-danger-bg text-danger' },
};

function PlusCircleIcon() {
    return (
        <svg viewBox="0 0 24 24" className="h-5 w-5 stroke-current" fill="none" strokeWidth="1.6" strokeLinecap="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v8M8 12h8" />
        </svg>
    );
}

function SharingIllustration() {
    return (
        <svg viewBox="0 0 240 180" className="h-40 w-auto" aria-hidden="true">
            <circle cx="30" cy="92" r="2" className="fill-line" />
            <circle cx="212" cy="120" r="2" className="fill-line" />
            <circle cx="118" cy="172" r="2" className="fill-line" />
            <rect x="56" y="30" width="128" height="128" rx="10" className="fill-bg stroke-line" strokeWidth="1.5" />
            <circle cx="92" cy="72" r="15" className="fill-accent/15" />
            <circle cx="92" cy="68" r="5" className="fill-accent/60" />
            <path d="M83 81a9 7 0 0 1 18 0Z" className="fill-accent/60" />
            <circle cx="120" cy="68" r="15" className="fill-ink-soft/15" />
            <circle cx="120" cy="64" r="5" className="fill-ink-soft/60" />
            <path d="M111 77a9 7 0 0 1 18 0Z" className="fill-ink-soft/60" />
            <circle cx="148" cy="72" r="15" className="fill-success/15" />
            <circle cx="148" cy="68" r="5" className="fill-success/60" />
            <path d="M139 81a9 7 0 0 1 18 0Z" className="fill-success/60" />
            <rect x="76" y="104" width="88" height="3" rx="1.5" className="fill-line" />
            <rect x="76" y="116" width="22" height="6" rx="3" className="fill-accent/30" />
            <rect x="106" y="116" width="18" height="6" rx="3" className="fill-ink-soft/20" />
            <rect x="132" y="116" width="24" height="6" rx="3" className="fill-success/30" />
            <rect x="76" y="134" width="64" height="3" rx="1.5" className="fill-line" />
            <circle cx="182" cy="34" r="15" className="fill-success" />
            <path d="M182 27v14M175 34h14" className="stroke-bg" strokeWidth="2.5" strokeLinecap="round" />
        </svg>
    );
}

export default function EventSharing({ collaborators, events, permissionOptions }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [dialog, setDialog] = useState<Dialog>(null);

    const eventTitles = new Map(events.map((event) => [event.id, event.title]));
    const permissionLabels = new Map(permissionOptions.map((option) => [option.value, option.label]));

    function resend(collaborator: SharedCollaborator) {
        router.post(`/settings/event-sharing/${collaborator.id}/resend`, {}, { preserveScroll: true });
    }

    function remove(collaborator: SharedCollaborator) {
        if (confirm(`Retirer ${collaborator.email} ? Cette personne perdra immédiatement l'accès à vos événements.`)) {
            router.delete(`/settings/event-sharing/${collaborator.id}`, { preserveScroll: true });
        }
    }

    const addButton = (
        <button
            type="button"
            onClick={() => setDialog({ collaborator: null })}
            className="inline-flex items-center gap-3 rounded-pill bg-ink px-7 py-3 text-sm font-medium text-bg hover:opacity-90"
        >
            <PlusCircleIcon />
            Ajouter un collaborateur
        </button>
    );

    return (
        <OrganizerLayout
            title="Partage d'événements"
            subtitle="Invitez des collaborateurs et du personnel d'accueil pour vous aider à gérer vos événements."
            closeHref="/dashboard"
        >
            <Head title="Partage d'événements" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <section className="rounded-card border border-line bg-bg">
                {collaborators.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-16 text-center">
                        <SharingIllustration />
                        <h2 className="mt-8 font-medium text-ink">Aucun collaborateur pour l'instant</h2>
                        <p className="mt-2 max-w-md text-sm text-ink-soft">
                            Invitez des membres de votre équipe pour vous aider à gérer vos événements. Attribuez un accès
                            administrateur ou en lecture seule, événement par événement.
                        </p>
                        <div className="mt-8">{addButton}</div>
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-line px-6 py-5">
                            <p className="text-sm text-ink-soft">
                                {collaborators.length} collaborateur{collaborators.length > 1 ? 's' : ''}
                            </p>
                            {addButton}
                        </div>

                        <ul className="divide-y divide-line">
                            {collaborators.map((collaborator) => {
                                const badge = STATUS_BADGES[collaborator.status];

                                return (
                                    <li key={collaborator.id} className="flex flex-wrap items-start justify-between gap-4 px-6 py-5">
                                        <div className="flex min-w-0 flex-1 gap-4">
                                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-bg-deep font-serif text-ink italic">
                                                {(collaborator.name ?? collaborator.email).charAt(0).toUpperCase()}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="flex flex-wrap items-center gap-2">
                                                    <span className="truncate font-medium text-ink">
                                                        {collaborator.name ?? collaborator.email}
                                                    </span>
                                                    <span className={`rounded-pill px-2.5 py-0.5 text-xs ${badge.className}`}>{badge.label}</span>
                                                </p>
                                                {collaborator.name && <p className="truncate text-sm text-ink-soft">{collaborator.email}</p>}
                                                <ul className="mt-3 flex flex-wrap gap-2">
                                                    {collaborator.permissions.map((permission) => (
                                                        <li
                                                            key={permission.event_id}
                                                            className="rounded-pill border border-line px-3 py-1 text-xs text-ink-soft"
                                                        >
                                                            {eventTitles.get(permission.event_id)} ·{' '}
                                                            <span className="text-ink">{permissionLabels.get(permission.permission)}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 flex-wrap gap-x-5 gap-y-2 text-sm">
                                            <button
                                                type="button"
                                                onClick={() => setDialog({ collaborator })}
                                                className="text-ink underline underline-offset-4"
                                            >
                                                Modifier
                                            </button>
                                            {collaborator.status !== 'active' && (
                                                <button
                                                    type="button"
                                                    onClick={() => resend(collaborator)}
                                                    className="text-ink underline underline-offset-4"
                                                >
                                                    Renvoyer l'invitation
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => remove(collaborator)}
                                                className="text-danger underline underline-offset-4"
                                            >
                                                Retirer
                                            </button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                )}
            </section>

            {dialog && (
                <CollaboratorModal
                    collaborator={dialog.collaborator}
                    events={events}
                    permissionOptions={permissionOptions}
                    onClose={() => setDialog(null)}
                />
            )}
        </OrganizerLayout>
    );
}
