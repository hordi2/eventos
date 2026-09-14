import { useForm } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';
import InputError from './InputError';
import InputLabel from './InputLabel';
import Modal from './Modal';

export type PermissionValue = 'none' | 'check_in' | 'administrator';

export interface SharedCollaborator {
    id: number;
    email: string;
    name: string | null;
    status: 'active' | 'pending' | 'expired';
    permissions: { event_id: number; permission: PermissionValue }[];
}

export interface ShareableEvent {
    id: number;
    title: string;
    date: string;
}

export interface PermissionOption {
    value: PermissionValue;
    label: string;
}

interface Props {
    collaborator: SharedCollaborator | null;
    events: ShareableEvent[];
    permissionOptions: PermissionOption[];
    onClose: () => void;
}

/**
 * Ajout (collaborator = null) ou modification des accès d'un collaborateur :
 * une permission par événement, « Aucun accès » par défaut.
 */
export default function CollaboratorModal({ collaborator, events, permissionOptions, onClose }: Props) {
    const isEditing = collaborator !== null;
    const [search, setSearch] = useState('');

    const form = useForm<{ email: string; permissions: Record<string, PermissionValue> }>({
        email: collaborator?.email ?? '',
        permissions: Object.fromEntries(
            events.map((event) => [
                String(event.id),
                collaborator?.permissions.find((permission) => permission.event_id === event.id)?.permission ?? 'none',
            ]),
        ),
    });

    const visibleEvents = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return needle === '' ? events : events.filter((event) => event.title.toLowerCase().includes(needle));
    }, [events, search]);

    const hasAccess = Object.values(form.data.permissions).some((permission) => permission !== 'none');
    const canSave = hasAccess && (isEditing || form.data.email.trim() !== '');

    const permissionsError = Object.entries(form.errors).find(([key]) => key.startsWith('permissions'))?.[1];

    function setPermission(eventId: number, permission: PermissionValue) {
        form.setData('permissions', { ...form.data.permissions, [String(eventId)]: permission });
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...(isEditing ? {} : { email: data.email }),
            permissions: Object.entries(data.permissions).map(([eventId, permission]) => ({
                event_id: Number(eventId),
                permission,
            })),
        }));

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (isEditing) {
            form.patch(`/settings/event-sharing/${collaborator.id}`, options);
        } else {
            form.post('/settings/event-sharing', options);
        }
    }

    return (
        <Modal
            open
            onClose={onClose}
            title={isEditing ? 'Modifier les accès' : "Ajouter un collaborateur ou du personnel d'accueil"}
            size="lg"
            showCloseButton
        >
            <form onSubmit={submit}>
                <div className="-mx-8 border-t border-line px-8 pt-6">
                    <InputLabel htmlFor="collaborator_email">Adresse e-mail</InputLabel>
                    <input
                        id="collaborator_email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        placeholder="utilisateur@domaine.com"
                        disabled={isEditing}
                        autoFocus={!isEditing}
                        className="w-full rounded-card border border-line bg-bg px-4 py-3 text-base text-ink placeholder:text-ink-soft focus:border-accent focus:outline-none disabled:bg-bg-alt disabled:text-ink-soft"
                    />
                    <InputError message={form.errors.email} />

                    {events.length === 0 ? (
                        <p className="mt-6 rounded-card bg-bg-alt p-4 text-sm text-ink-soft">
                            Créez d'abord un événement : c'est lui que vous partagerez.
                        </p>
                    ) : (
                        <>
                            <div className="relative mt-6 sm:max-w-xs">
                                <svg
                                    viewBox="0 0 24 24"
                                    className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 stroke-ink-soft"
                                    fill="none"
                                    strokeWidth="2"
                                    aria-hidden="true"
                                >
                                    <circle cx="11" cy="11" r="7" />
                                    <path d="m21 21-4.3-4.3" strokeLinecap="round" />
                                </svg>
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Rechercher un événement…"
                                    aria-label="Rechercher un événement"
                                    className="w-full rounded-card border border-line bg-bg py-2.5 pr-4 pl-10 text-sm text-ink placeholder:text-ink-soft focus:border-accent focus:outline-none"
                                />
                            </div>

                            <div className="mt-4 overflow-hidden rounded-card border border-line">
                                <div className="hidden grid-cols-[1fr_14rem] gap-4 bg-bg-alt px-4 py-3 font-label text-[11px] tracking-[0.14em] text-ink-soft uppercase sm:grid">
                                    <span>Événement</span>
                                    <span className="group relative flex items-center gap-2">
                                        Permissions
                                        <button
                                            type="button"
                                            aria-label="À quoi correspondent les permissions"
                                            className="flex h-4 w-4 items-center justify-center rounded-full border border-ink-soft text-[9px] normal-case"
                                        >
                                            i
                                        </button>
                                        <span
                                            role="tooltip"
                                            className="invisible absolute top-full right-0 z-10 mt-2 w-72 rounded-card border border-line bg-bg p-4 text-xs leading-relaxed tracking-normal text-ink normal-case shadow-lg group-focus-within:visible group-hover:visible"
                                        >
                                            <strong>Lecture seule + check-in</strong> : voit le tableau de bord et les
                                            invités, enregistre les arrivées.
                                            <br />
                                            <strong>Administrateur</strong> : tous les droits sur l'événement, suppression
                                            et remboursements compris.
                                        </span>
                                    </span>
                                </div>

                                <ul className="max-h-72 divide-y divide-line overflow-y-auto">
                                    {visibleEvents.length === 0 && (
                                        <li className="px-4 py-6 text-center text-sm text-ink-soft">Aucun événement trouvé.</li>
                                    )}
                                    {visibleEvents.map((event) => (
                                        <li key={event.id} className="grid gap-2 px-4 py-3 sm:grid-cols-[1fr_14rem] sm:items-center sm:gap-4">
                                            <div className="min-w-0">
                                                <p className="truncate text-ink">{event.title}</p>
                                                <p className="text-xs text-ink-soft">{event.date}</p>
                                            </div>
                                            <div className="relative">
                                                <select
                                                    value={form.data.permissions[String(event.id)] ?? 'none'}
                                                    onChange={(e) => setPermission(event.id, e.target.value as PermissionValue)}
                                                    aria-label={`Permission pour ${event.title}`}
                                                    className="w-full appearance-none rounded-card border border-line bg-bg py-2.5 pr-9 pl-3 text-sm text-ink focus:border-accent focus:outline-none"
                                                >
                                                    {permissionOptions.map((option) => (
                                                        <option key={option.value} value={option.value}>
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <svg
                                                    className="pointer-events-none absolute top-1/2 right-3 h-2.5 w-3.5 -translate-y-1/2 text-ink-soft"
                                                    viewBox="0 0 14 9"
                                                    fill="none"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M1 1l6 6 6-6" stroke="currentColor" strokeWidth="1.5" />
                                                </svg>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </>
                    )}
                    <InputError message={permissionsError} />
                </div>

                <div className="-mx-8 mt-8 -mb-8 flex flex-wrap justify-end gap-3 border-t border-line bg-bg-alt px-8 py-5">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-pill border border-line bg-bg px-6 py-2.5 text-sm text-ink hover:border-ink"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        disabled={!canSave || form.processing}
                        className="rounded-pill bg-ink px-6 py-2.5 text-sm font-medium text-bg hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Enregistrer
                    </button>
                </div>
            </form>
        </Modal>
    );
}
