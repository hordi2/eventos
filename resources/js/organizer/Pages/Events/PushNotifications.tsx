import { Head, usePage, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import Textarea from '../../Components/Textarea';
import EventLayout from '../../Layouts/EventLayout';

interface NotificationRow {
    id: number;
    title: string;
    body: string;
    recipientCount: number;
    sentAt: string | null;
}

interface Props {
    event: { id: number; title: string };
    notifications: NotificationRow[];
    subscriberCount: number;
    pushConfigured: boolean;
}

/**
 * Notifications push de l'application participant (D9) : sans charge utile
 * (RFC 8292), leur contenu ne vit que dans ce fil et dans celui, in-app, de
 * la page hub du participant — jamais dans le push lui-même.
 */
export default function PushNotifications({ event, notifications, subscriberCount, pushConfigured }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const form = useForm({ title: '', body: '' });

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        form.post(`/events/${event.id}/notifications-push`, { onSuccess: () => form.reset() });
    }

    return (
        <EventLayout title="Notifications push" eyebrow={event.title}>
            <Head title="Notifications push" />

            {!pushConfigured && (
                <p className="mb-8 rounded-card bg-bg px-5 py-4 text-sm text-ink-soft ring-1 ring-line">
                    L'envoi de notifications push n'est pas configuré sur cette installation.
                </p>
            )}

            <p className="mb-8 text-sm text-ink-soft">
                {subscriberCount === 0
                    ? "Aucun participant n'est abonné pour le moment : la proposition d'activer les notifications apparaît sur leur application, une fois installée."
                    : `${subscriberCount} participant${subscriberCount > 1 ? 's' : ''} recevront vos prochaines notifications.`}
            </p>

            <form onSubmit={submit} className="mb-8 rounded-card bg-bg p-5 ring-1 ring-line">
                <h2 className="mb-1 font-serif text-lg italic">Diffuser une notification</h2>
                <p className="mb-5 text-sm text-ink-soft">
                    Un rappel bref : le titre et le message apparaissent sur l'appareil du participant, et dans le fil de
                    son application.
                </p>

                <InputError message={errors.push} />

                <div className="mt-4 grid gap-5">
                    <div>
                        <InputLabel htmlFor="title">Titre</InputLabel>
                        <TextInput
                            id="title"
                            maxLength={80}
                            value={form.data.title}
                            onChange={(changeEvent) => form.setData('title', changeEvent.target.value)}
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div>
                        <InputLabel htmlFor="body">Message</InputLabel>
                        <Textarea
                            id="body"
                            maxLength={200}
                            value={form.data.body}
                            onChange={(changeEvent) => form.setData('body', changeEvent.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                </div>

                <Button type="submit" disabled={form.processing || !pushConfigured} className="mt-5 w-auto px-6 py-2">
                    {form.processing ? 'Envoi…' : 'Envoyer'}
                </Button>
            </form>

            <h2 className="mb-3 font-serif text-lg italic">Déjà diffusées</h2>
            {notifications.length === 0 ? (
                <p className="text-sm text-ink-soft">Aucune notification envoyée pour cet événement.</p>
            ) : (
                <ul className="space-y-2">
                    {notifications.map((notification) => (
                        <li key={notification.id} className="rounded-card bg-bg px-5 py-3 ring-1 ring-line">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <span className="text-ink">{notification.title}</span>
                                <span className="text-sm text-ink-soft tabular-nums">
                                    {notification.recipientCount} destinataire{notification.recipientCount > 1 ? 's' : ''}
                                    {notification.sentAt && ` · ${new Date(notification.sentAt).toLocaleString('fr-FR')}`}
                                </span>
                            </div>
                            <p className="mt-1 text-sm text-ink-soft">{notification.body}</p>
                        </li>
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}
