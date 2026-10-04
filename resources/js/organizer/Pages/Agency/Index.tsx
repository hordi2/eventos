import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';

interface ClientRow {
    id: number;
    name: string;
    slug: string;
    eventCount: number;
    nextEventTitle: string | null;
    nextEventDate: string | null;
    registrationCount: number;
    revenue: string;
    managedSince: string | null;
}

interface Props {
    agency: { name: string };
    period: { from: string | null; to: string | null };
    portfolio: {
        eventCount: number;
        registrationCount: number;
        revenue: string;
        clients: ClientRow[];
    };
}

/**
 * Portail agence (D10) : les comptes clients du portefeuille, leur activité,
 * et le total consolidé. Un compte rendu à son client lui reste entier.
 */
export default function Index({ agency, period, portfolio }: Props) {
    const [from, setFrom] = useState(period.from ?? '');
    const [to, setTo] = useState(period.to ?? '');
    const query = from || to ? `?du=${from}&au=${to}` : '';

    function applyPeriod(event: FormEvent) {
        event.preventDefault();
        router.get('/clients', { du: from || undefined, au: to || undefined }, { preserveState: true });
    }

    const form = useForm({ name: '' });
    const [releasing, setReleasing] = useState<number | null>(null);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/clients', { onSuccess: () => form.reset('name') });
    }

    function release(client: ClientRow) {
        setReleasing(client.id);
        router.delete(`/clients/${client.id}`, { onFinish: () => setReleasing(null) });
    }

    return (
        <SettingsLayout title="Mes clients" active="clients">
            <Head title="Mes clients" />

            <p className="mb-8 text-sm text-ink-soft">
                Les comptes que {agency.name} gère pour ses clients. Chacun est un compte à part entière : vous pouvez le
                rendre à son client à tout moment, il gardera ses événements, ses contacts et son historique.
            </p>

            {/* La période refacturée : c'est elle que l'agence joint à sa facture. */}
            <form onSubmit={applyPeriod} className="mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <InputLabel htmlFor="du">Du</InputLabel>
                    <TextInput id="du" type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
                </div>
                <div>
                    <InputLabel htmlFor="au">Au</InputLabel>
                    <TextInput id="au" type="date" value={to} onChange={(event) => setTo(event.target.value)} />
                </div>
                <Button type="submit" variant="secondary" className="w-auto px-5 py-2">
                    Afficher
                </Button>
                <a
                    href={`/clients/export${query}`}
                    className="inline-flex min-h-10 items-center rounded-pill border border-line px-4 py-2 text-sm text-ink hover:border-ink"
                >
                    Exporter le portefeuille
                </a>
            </form>

            <div className="mb-8 grid gap-4 sm:grid-cols-3">
                {[
                    { label: 'Événements', value: String(portfolio.eventCount) },
                    { label: 'Inscrits', value: String(portfolio.registrationCount) },
                    { label: 'Recettes', value: portfolio.revenue },
                ].map((tile) => (
                    <div key={tile.label} className="rounded-card bg-bg p-5 ring-1 ring-line">
                        <p className="font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">{tile.label}</p>
                        <p className="mt-2 font-serif text-2xl text-ink tabular-nums">{tile.value}</p>
                    </div>
                ))}
            </div>

            <form onSubmit={submit} className="mb-10 flex flex-wrap items-end gap-3 rounded-card bg-bg p-5 ring-1 ring-line">
                <div className="min-w-[16rem] flex-1">
                    <InputLabel htmlFor="name">Ouvrir le compte d'un client</InputLabel>
                    <TextInput
                        id="name"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        maxLength={120}
                        placeholder="Fondation Lumière"
                    />
                    <InputError message={form.errors.name} />
                </div>
                <Button type="submit" disabled={form.processing} className="w-auto px-6 py-2">
                    {form.processing ? 'Ouverture…' : 'Ouvrir le compte'}
                </Button>
            </form>

            {portfolio.clients.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun compte client</p>
                    <p className="text-sm text-ink-soft">Ouvrez le premier compte ci-dessus : vous y entrerez d'office.</p>
                </div>
            ) : (
                <ul className="space-y-3">
                    {portfolio.clients.map((client) => (
                        <li key={client.id} className="rounded-card bg-bg p-5 ring-1 ring-line">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="font-medium text-ink">{client.name}</p>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        {client.nextEventTitle
                                            ? `Prochain événement : ${client.nextEventTitle} — ${client.nextEventDate}`
                                            : 'Aucun événement à venir'}
                                    </p>
                                    {client.managedSince && (
                                        <p className="mt-1 text-xs text-ink-soft">Confié depuis le {client.managedSince}</p>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-6 text-sm text-ink-soft">
                                    <span className="tabular-nums">{client.eventCount} événements</span>
                                    <span className="tabular-nums">{client.registrationCount} inscrits</span>
                                    <span className="tabular-nums text-ink">{client.revenue}</span>
                                    <a
                                        href={`/clients/${client.id}/releve.pdf${query}`}
                                        className="text-sm text-ink underline underline-offset-2 hover:no-underline"
                                    >
                                        Relevé
                                    </a>
                                    <button
                                        type="button"
                                        onClick={() => release(client)}
                                        disabled={releasing === client.id}
                                        className="text-sm text-danger underline hover:no-underline disabled:opacity-40"
                                    >
                                        {releasing === client.id ? 'Restitution…' : 'Rendre le compte'}
                                    </button>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </SettingsLayout>
    );
}
