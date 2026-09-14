import { router, useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import Button from './Button';
import InputError from './InputError';
import InputLabel from './InputLabel';
import TextInput from './TextInput';

export interface N8nWorkflow {
    id: string;
    name: string;
    active: boolean;
    webhook_url: string | null;
}

export interface N8nState {
    connected: boolean;
    base_url: string | null;
    loading: boolean;
    workflows: N8nWorkflow[];
    error: string | null;
    fetched_at: string | null;
}

interface EventOption {
    value: string;
    label: string;
}

interface Props {
    n8n: N8nState;
    availableEvents: EventOption[];
}

const POLL_INTERVAL_MS = 2000;

// La liste est récupérée en file d'attente : au-delà de ce délai on cesse
// d'interroger le serveur plutôt que de le solliciter indéfiniment.
const POLL_TIMEOUT_MS = 30000;

/**
 * Connexion à l'instance n8n du client par clé API : contrairement à
 * Zapier, n8n expose une API publique authentifiée par clé, ce qui permet
 * de brancher un workflow sans recopier son URL de webhook.
 */
export default function N8nConnection({ n8n, availableEvents }: Props) {
    const [selectedEvents, setSelectedEvents] = useState<string[]>([availableEvents[0]?.value].filter(Boolean) as string[]);
    const [pollTimedOut, setPollTimedOut] = useState(false);

    const connectForm = useForm({ base_url: '', api_key: '' });

    useEffect(() => {
        if (!n8n.loading) {
            return;
        }

        const startedAt = Date.now();
        const interval = setInterval(() => {
            if (Date.now() - startedAt > POLL_TIMEOUT_MS) {
                clearInterval(interval);
                setPollTimedOut(true);

                return;
            }

            router.reload({ only: ['n8n'] });
        }, POLL_INTERVAL_MS);

        return () => clearInterval(interval);
    }, [n8n.loading]);

    function submitConnection(event: FormEvent) {
        event.preventDefault();
        connectForm.post('/settings/api/n8n', {
            preserveScroll: true,
            onSuccess: () => connectForm.reset('api_key'),
        });
    }

    function disconnect() {
        if (confirm('Déconnecter n8n ? Les webhooks déjà créés continueront de fonctionner.')) {
            router.delete('/settings/api/n8n', { preserveScroll: true });
        }
    }

    function refresh() {
        setPollTimedOut(false);
        router.post('/settings/api/n8n/refresh', {}, { preserveScroll: true });
    }

    function toggleEvent(value: string) {
        setSelectedEvents((current) =>
            current.includes(value) ? current.filter((v) => v !== value) : [...current, value],
        );
    }

    function connectWorkflow(workflow: N8nWorkflow) {
        if (workflow.webhook_url === null || selectedEvents.length === 0) {
            return;
        }

        router.post(
            '/settings/api/n8n/workflows',
            { webhook_url: workflow.webhook_url, events: selectedEvents },
            { preserveScroll: true },
        );
    }

    if (!n8n.connected) {
        return (
            <form onSubmit={submitConnection} className="max-w-md border-t border-line p-4">
                <p className="mb-4 text-sm text-ink-soft">
                    Dans n8n, ouvrez Réglages → n8n API, créez une clé, puis collez-la ici avec l'adresse de votre
                    instance.
                </p>

                <div className="mb-5">
                    <InputLabel htmlFor="n8n_base_url">Adresse de votre instance n8n</InputLabel>
                    <TextInput
                        id="n8n_base_url"
                        value={connectForm.data.base_url}
                        onChange={(e) => connectForm.setData('base_url', e.target.value)}
                        placeholder="https://n8n.mon-organisation.com"
                    />
                    <InputError message={connectForm.errors.base_url} />
                </div>

                <div className="mb-5">
                    <InputLabel htmlFor="n8n_api_key">Clé API n8n</InputLabel>
                    <TextInput
                        id="n8n_api_key"
                        type="password"
                        value={connectForm.data.api_key}
                        onChange={(e) => connectForm.setData('api_key', e.target.value)}
                    />
                    <InputError message={connectForm.errors.api_key} />
                </div>

                <Button type="submit" disabled={connectForm.processing}>
                    {connectForm.processing ? 'Vérification…' : 'Connecter n8n'}
                </Button>
            </form>
        );
    }

    return (
        <div className="border-t border-line p-4">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-ink">
                    Connecté à <span className="font-medium">{n8n.base_url}</span>
                </p>
                <div className="flex gap-4">
                    <button
                        type="button"
                        onClick={refresh}
                        disabled={n8n.loading && !pollTimedOut}
                        className="text-sm text-ink underline underline-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Rafraîchir
                    </button>
                    <button type="button" onClick={disconnect} className="text-sm text-danger underline underline-offset-2">
                        Déconnecter
                    </button>
                </div>
            </div>

            {n8n.loading && (
                <p className="text-sm text-ink-soft">
                    {pollTimedOut
                        ? 'La liste de vos workflows met plus de temps que prévu à arriver. Réessayez dans un instant avec « Rafraîchir ».'
                        : 'Récupération de vos workflows n8n…'}
                </p>
            )}

            {!n8n.loading && n8n.error !== null && (
                <p className="mb-4 rounded-card bg-danger-bg p-3 text-sm text-danger ring-1 ring-danger/30">{n8n.error}</p>
            )}

            {!n8n.loading && n8n.error === null && (
                <>
                    <p className="mb-2 font-label text-xs tracking-[0.1em] text-ink-soft uppercase">
                        Événements à envoyer
                    </p>
                    <div className="mb-5 flex flex-wrap gap-2">
                        {availableEvents.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => toggleEvent(option.value)}
                                className={`rounded-pill border px-4 py-1.5 text-sm transition-colors ${
                                    selectedEvents.includes(option.value)
                                        ? 'border-accent text-ink'
                                        : 'border-line text-ink-soft hover:border-ink'
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>

                    {n8n.workflows.length === 0 ? (
                        <p className="text-sm text-ink-soft">
                            Aucun workflow dans cette instance. Créez-en un dans n8n avec un nœud « Webhook », puis
                            cliquez sur « Rafraîchir ».
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {n8n.workflows.map((workflow) => (
                                <li
                                    key={workflow.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line px-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm text-ink">{workflow.name}</p>
                                        <p className="text-xs text-ink-soft">
                                            {workflow.webhook_url === null
                                                ? 'Pas de nœud Webhook — rien où envoyer les événements'
                                                : workflow.active
                                                  ? 'Actif dans n8n'
                                                  : 'Inactif dans n8n'}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        disabled={workflow.webhook_url === null || selectedEvents.length === 0}
                                        onClick={() => connectWorkflow(workflow)}
                                        className="shrink-0 rounded-pill border border-line px-5 py-2 text-sm text-ink hover:border-ink disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Brancher
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {n8n.fetched_at !== null && (
                        <p className="mt-3 text-xs text-ink-soft">
                            Liste mise à jour à{' '}
                            {new Date(n8n.fetched_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}.
                        </p>
                    )}
                </>
            )}
        </div>
    );
}
