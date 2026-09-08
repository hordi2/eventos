import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Toggle from '../../Components/Toggle';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface EventPreference {
    id: number;
    title: string;
    start_at: string;
    start_at_formatted: string;
    notify_created: boolean;
    notify_updated: boolean;
    notify_cancelled: boolean;
}

interface Props {
    registrationNotificationsEnabled: boolean;
    events: EventPreference[];
}

type PreferenceField = 'notify_created' | 'notify_updated' | 'notify_cancelled';
type SortKey = 'title' | 'start_at';

const STATUS_MESSAGES: Record<string, string> = {
    'notifications-updated': 'Préférences de notification enregistrées.',
};

const COLUMNS: { field: PreferenceField; label: string }[] = [
    { field: 'notify_created', label: 'Nouveau' },
    { field: 'notify_updated', label: 'Modifications' },
    { field: 'notify_cancelled', label: 'Annulations' },
];

function SortIcon() {
    return (
        <svg viewBox="0 0 10 14" className="ml-1 inline-block h-3 w-2.5 stroke-current fill-none" strokeWidth="1.5">
            <path d="M5 1.5 8 5H2l3-3.5Z" />
            <path d="M5 12.5 2 9h6l-3 3.5Z" />
        </svg>
    );
}

export default function Notifications({ registrationNotificationsEnabled, events }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<SortKey>('start_at');
    const [ascending, setAscending] = useState(false);

    function toggleMaster(checked: boolean) {
        router.patch('/settings/notifications', { registration_notifications_enabled: checked }, { preserveScroll: true });
    }

    function savePreference(event: EventPreference, values: Record<PreferenceField, boolean>) {
        router.patch(`/settings/notifications/events/${event.id}`, values, { preserveScroll: true });
    }

    function toggleField(event: EventPreference, field: PreferenceField, checked: boolean) {
        savePreference(event, {
            notify_created: event.notify_created,
            notify_updated: event.notify_updated,
            notify_cancelled: event.notify_cancelled,
            [field]: checked,
        });
    }

    // Interrupteur enveloppe : bascule les trois colonnes d'un coup, comme
    // une case « tout ou rien » pour cet événement.
    function toggleWholeRow(event: EventPreference, checked: boolean) {
        savePreference(event, {
            notify_created: checked,
            notify_updated: checked,
            notify_cancelled: checked,
        });
    }

    function sortBy(key: SortKey) {
        setAscending(key === sortKey ? !ascending : true);
        setSortKey(key);
    }

    const visibleEvents = useMemo(() => {
        const needle = search.trim().toLowerCase();
        const filtered = needle === '' ? events : events.filter((event) => event.title.toLowerCase().includes(needle));

        return [...filtered].sort((a, b) => {
            const comparison = sortKey === 'title' ? a.title.localeCompare(b.title) : a.start_at.localeCompare(b.start_at);

            return ascending ? comparison : -comparison;
        });
    }, [events, search, sortKey, ascending]);

    return (
        <SettingsLayout title="Paramètres" active="notifications">
            <Head title="Notifications" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <div className="mb-6 flex items-center gap-4 border-b border-line pb-6">
                <Toggle
                    checked={registrationNotificationsEnabled}
                    onChange={toggleMaster}
                    label="Notifications d'inscription"
                />
                <span className="text-xl">Notifications d'inscription</span>
            </div>

            <p className="mb-6 text-sm text-ink-soft">
                Réglage général : désactivé, aucune notification n'est envoyée, quels que soient les réglages par
                événement ci-dessous.
            </p>

            <div className="relative mb-6 max-w-md">
                <svg
                    viewBox="0 0 24 24"
                    className="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 stroke-ink-soft fill-none"
                    strokeWidth="2"
                >
                    <circle cx="11" cy="11" r="7" />
                    <path d="m21 21-4.3-4.3" strokeLinecap="round" />
                </svg>
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Recherchez par nom de l'événement…"
                    className="w-full rounded-pill border border-line bg-bg py-2.5 pr-4 pl-11 text-sm text-ink placeholder:text-ink-soft"
                />
            </div>

            {visibleEvents.length === 0 ? (
                <p className="text-sm text-ink-soft">Aucun événement.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr className="border-y border-line font-label text-xs tracking-[0.1em] text-ink-soft uppercase">
                                <th className="w-16 py-3 pr-4">
                                    <svg
                                        viewBox="0 0 24 24"
                                        className="h-4 w-5 stroke-current fill-none"
                                        strokeWidth="1.6"
                                        aria-label="Toutes les notifications de l'événement"
                                    >
                                        <rect x="2" y="5" width="20" height="14" rx="2" />
                                        <path d="m3 7 9 6 9-6" />
                                    </svg>
                                </th>
                                <th className="py-3 pr-4">
                                    <button type="button" onClick={() => sortBy('title')} className="uppercase hover:text-ink">
                                        Nom de l'événement
                                        <SortIcon />
                                    </button>
                                </th>
                                <th className="py-3 pr-4">
                                    <button type="button" onClick={() => sortBy('start_at')} className="uppercase hover:text-ink">
                                        Date de l'événement
                                        <SortIcon />
                                    </button>
                                </th>
                                {COLUMNS.map((column) => (
                                    <th key={column.field} className="w-32 py-3 pr-4 uppercase">
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {visibleEvents.map((event) => {
                                const wholeRow = event.notify_created || event.notify_updated || event.notify_cancelled;

                                return (
                                    <tr key={event.id} className="border-b border-line">
                                        <td className="py-4 pr-4">
                                            <Toggle
                                                checked={wholeRow}
                                                onChange={(checked) => toggleWholeRow(event, checked)}
                                                label={`Toutes les notifications de ${event.title}`}
                                            />
                                        </td>
                                        <td className="py-4 pr-4 text-ink">{event.title}</td>
                                        <td className="py-4 pr-4 text-ink-soft">{event.start_at_formatted}</td>
                                        {COLUMNS.map((column) => (
                                            <td key={column.field} className="py-4 pr-4">
                                                <Toggle
                                                    checked={event[column.field]}
                                                    onChange={(checked) => toggleField(event, column.field, checked)}
                                                    label={`${column.label} — ${event.title}`}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </SettingsLayout>
    );
}
