import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface EventPreference {
    id: number;
    title: string;
    notify_created: boolean;
    notify_updated: boolean;
    notify_cancelled: boolean;
}

interface Props {
    registrationNotificationsEnabled: boolean;
    events: EventPreference[];
}

const STATUS_MESSAGES: Record<string, string> = {
    'notifications-updated': 'Préférences de notification enregistrées.',
};

export default function Notifications({ registrationNotificationsEnabled, events }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [search, setSearch] = useState('');

    function toggleMaster(checked: boolean) {
        router.patch('/settings/notifications', { registration_notifications_enabled: checked }, { preserveScroll: true });
    }

    function toggleEvent(event: EventPreference, field: 'notify_created' | 'notify_updated' | 'notify_cancelled', checked: boolean) {
        router.patch(
            `/settings/notifications/events/${event.id}`,
            {
                notify_created: field === 'notify_created' ? checked : event.notify_created,
                notify_updated: field === 'notify_updated' ? checked : event.notify_updated,
                notify_cancelled: field === 'notify_cancelled' ? checked : event.notify_cancelled,
            },
            { preserveScroll: true },
        );
    }

    const filtered = events.filter((event) => event.title.toLowerCase().includes(search.trim().toLowerCase()));

    return (
        <SettingsLayout title="Paramètres" active="notifications">
            <Head title="Notifications" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <section className="mb-8">
                <label className="flex max-w-md items-center justify-between rounded-card border border-line p-4 text-sm">
                    <span>Notifications d'inscription par e-mail</span>
                    <input
                        type="checkbox"
                        checked={registrationNotificationsEnabled}
                        onChange={(e) => toggleMaster(e.target.checked)}
                        className="h-5 w-5 rounded border-line text-ink focus:ring-ink"
                    />
                </label>
                <p className="mt-2 text-sm text-ink-soft">
                    Réglage général : désactivé, aucune notification n'est envoyée, quels que soient les réglages par
                    événement ci-dessous.
                </p>
            </section>

            <section>
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Rechercher par nom d'événement…"
                    className="mb-4 w-full max-w-md rounded-pill border border-line bg-bg px-4 py-2 text-sm text-ink placeholder:text-ink-soft"
                />

                {filtered.length === 0 ? (
                    <p className="text-sm text-ink-soft">Aucun événement.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[600px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-line font-label text-xs tracking-[0.1em] text-ink-soft uppercase">
                                    <th className="py-2 pr-4">Événement</th>
                                    <th className="py-2 px-4">Nouveau</th>
                                    <th className="py-2 px-4">Modifications</th>
                                    <th className="py-2 pl-4">Annulations</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.map((event) => (
                                    <tr key={event.id} className="border-b border-line">
                                        <td className="py-3 pr-4">{event.title}</td>
                                        {(['notify_created', 'notify_updated', 'notify_cancelled'] as const).map((field) => (
                                            <td key={field} className="py-3 px-4">
                                                <input
                                                    type="checkbox"
                                                    checked={event[field]}
                                                    onChange={(e) => toggleEvent(event, field, e.target.checked)}
                                                    className="h-5 w-5 rounded border-line text-ink focus:ring-ink"
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </SettingsLayout>
    );
}
