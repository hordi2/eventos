import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface ApiToken {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string | null;
}

interface WebhookRow {
    id: number;
    url: string;
    subscribed_events: string[];
    is_active: boolean;
    last_delivery_at: string | null;
    last_delivery_status: string | null;
}

interface EventOption {
    value: string;
    label: string;
}

interface Props {
    tokens: ApiToken[];
    webhooks: WebhookRow[];
    availableEvents: EventOption[];
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) : 'jamais';
}

export default function Integrations({ tokens, webhooks, availableEvents }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [showTokenSecret, setShowTokenSecret] = useState(true);
    const [showWebhookSecret, setShowWebhookSecret] = useState(true);

    const tokenForm = useForm({ name: '' });
    const webhookForm = useForm<{ url: string; subscribed_events: string[] }>({ url: '', subscribed_events: [] });

    function submitToken(event: FormEvent) {
        event.preventDefault();
        setShowTokenSecret(true);
        tokenForm.post('/settings/api/tokens', {
            preserveScroll: true,
            onSuccess: () => tokenForm.reset(),
        });
    }

    function revokeToken(tokenId: number) {
        if (confirm('Révoquer cette clé API ? Toute intégration qui l\'utilise cessera de fonctionner immédiatement.')) {
            router.delete(`/settings/api/tokens/${tokenId}`, { preserveScroll: true });
        }
    }

    function toggleEvent(eventValue: string) {
        const current = webhookForm.data.subscribed_events;
        webhookForm.setData(
            'subscribed_events',
            current.includes(eventValue) ? current.filter((v) => v !== eventValue) : [...current, eventValue],
        );
    }

    function submitWebhook(event: FormEvent) {
        event.preventDefault();
        setShowWebhookSecret(true);
        webhookForm.post('/settings/api/webhooks', {
            preserveScroll: true,
            onSuccess: () => webhookForm.reset(),
        });
    }

    function toggleWebhookActive(webhook: WebhookRow) {
        router.patch(
            `/settings/api/webhooks/${webhook.id}`,
            { url: webhook.url, subscribed_events: webhook.subscribed_events, is_active: !webhook.is_active },
            { preserveScroll: true },
        );
    }

    function deleteWebhook(webhookId: number) {
        if (confirm('Supprimer ce webhook ? Les livraisons en attente ne seront pas envoyées.')) {
            router.delete(`/settings/api/webhooks/${webhookId}`, { preserveScroll: true });
        }
    }

    return (
        <SettingsLayout title="Paramètres" active="integrations">
            <Head title="Intégrations & API" />

            <section className="mb-14">
                <h2 className="mb-1 font-serif text-xl italic">Clés API</h2>
                <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                    Donnent accès aux mêmes points d'entrée que l'application mobile de check-in (invités, check-ins). À utiliser pour vos
                    propres scripts ou intégrations — jamais partagées telles quelles dans un outil tiers non maîtrisé.
                </p>

                {flash.plainToken && showTokenSecret && (
                    <div className="mb-6 rounded-card border border-accent/30 bg-accent/5 p-4">
                        <p className="mb-2 text-sm font-medium">
                            Copiez cette clé maintenant — elle ne sera plus jamais affichée en clair.
                        </p>
                        <code className="block truncate rounded-control bg-bg-deep px-3 py-2 text-sm">{flash.plainToken}</code>
                        <button type="button" onClick={() => setShowTokenSecret(false)} className="mt-2 text-sm underline underline-offset-2">
                            J'ai copié la clé
                        </button>
                    </div>
                )}

                {tokens.length > 0 && (
                    <ul className="mb-6 space-y-2">
                        {tokens.map((token) => (
                            <li key={token.id} className="flex items-center justify-between rounded-card border border-line px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">{token.name}</p>
                                    <p className="text-xs text-ink-soft">
                                        Créée le {formatDate(token.created_at)} · dernière utilisation : {formatDate(token.last_used_at)}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => revokeToken(token.id)}
                                    className="text-sm text-danger underline underline-offset-2"
                                >
                                    Révoquer
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <form onSubmit={submitToken} className="max-w-md">
                    <div className="mb-5">
                        <InputLabel htmlFor="token_name">Nom de la clé</InputLabel>
                        <TextInput
                            id="token_name"
                            placeholder="Ex. Automatisation Zapier"
                            value={tokenForm.data.name}
                            onChange={(e) => tokenForm.setData('name', e.target.value)}
                        />
                        <InputError message={tokenForm.errors.name} />
                    </div>
                    <Button type="submit" disabled={tokenForm.processing}>
                        Créer
                    </Button>
                </form>
            </section>

            <section>
                <h2 className="mb-1 font-serif text-xl italic">Webhooks sortants</h2>
                <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                    Notifie une URL de votre choix quand un événement se produit — pour connecter Itaza à Zapier ou à un script. Chaque
                    envoi est signé (en-tête <code>X-Itaza-Signature</code>) pour que vous puissiez vérifier qu'il vient bien d'Itaza.
                </p>

                {flash.plainSecret && showWebhookSecret && (
                    <div className="mb-6 rounded-card border border-accent/30 bg-accent/5 p-4">
                        <p className="mb-2 text-sm font-medium">
                            Copiez ce secret maintenant — il sert à vérifier la signature des envois et ne sera plus jamais affiché.
                        </p>
                        <code className="block truncate rounded-control bg-bg-deep px-3 py-2 text-sm">{flash.plainSecret}</code>
                        <button
                            type="button"
                            onClick={() => setShowWebhookSecret(false)}
                            className="mt-2 text-sm underline underline-offset-2"
                        >
                            J'ai copié le secret
                        </button>
                    </div>
                )}

                {webhooks.length > 0 && (
                    <ul className="mb-8 space-y-3">
                        {webhooks.map((webhook) => (
                            <li key={webhook.id} className="rounded-card border border-line p-4">
                                <div className="mb-2 flex items-start justify-between gap-4">
                                    <code className="text-sm break-all">{webhook.url}</code>
                                    <div className="flex shrink-0 gap-3">
                                        <button
                                            type="button"
                                            onClick={() => toggleWebhookActive(webhook)}
                                            className="text-sm underline underline-offset-2"
                                        >
                                            {webhook.is_active ? 'Désactiver' : 'Activer'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => deleteWebhook(webhook.id)}
                                            className="text-sm text-danger underline underline-offset-2"
                                        >
                                            Supprimer
                                        </button>
                                    </div>
                                </div>
                                <div className="mb-2 flex flex-wrap gap-1.5">
                                    {webhook.subscribed_events.map((eventValue) => (
                                        <Badge key={eventValue} variant="neutral">
                                            {availableEvents.find((e) => e.value === eventValue)?.label ?? eventValue}
                                        </Badge>
                                    ))}
                                    <Badge variant={webhook.is_active ? 'success' : 'neutral'}>
                                        {webhook.is_active ? 'Actif' : 'Inactif'}
                                    </Badge>
                                </div>
                                <p className="text-xs text-ink-soft">
                                    Dernière livraison :{' '}
                                    {webhook.last_delivery_at
                                        ? `${formatDate(webhook.last_delivery_at)} (${webhook.last_delivery_status})`
                                        : 'aucune pour le moment'}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}

                <form onSubmit={submitWebhook} className="max-w-md">
                    <div className="mb-5">
                        <InputLabel htmlFor="webhook_url">URL à notifier</InputLabel>
                        <TextInput
                            id="webhook_url"
                            type="url"
                            placeholder="https://..."
                            value={webhookForm.data.url}
                            onChange={(e) => webhookForm.setData('url', e.target.value)}
                        />
                        <InputError message={webhookForm.errors.url} />
                    </div>

                    <div className="mb-5">
                        <InputLabel>Événements</InputLabel>
                        <div className="space-y-2">
                            {availableEvents.map((option) => (
                                <label key={option.value} className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={webhookForm.data.subscribed_events.includes(option.value)}
                                        onChange={() => toggleEvent(option.value)}
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                        <InputError message={webhookForm.errors.subscribed_events} />
                    </div>

                    <Button type="submit" disabled={webhookForm.processing} className="w-auto">
                        Ajouter le webhook
                    </Button>
                </form>
            </section>
        </SettingsLayout>
    );
}
