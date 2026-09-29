import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

interface PromoCodeRow {
    id: number;
    code: string;
    kind: string;
    percent: number | null;
    amountMinor: number | null;
    reduction: string;
    maxUses: number | null;
    uses: number;
    startsAt: string | null;
    endsAt: string | null;
    isActive: boolean;
}

interface PromoCodesPageProps {
    event: { id: number; title: string; currency: string; timezone: string };
    promoCodes: PromoCodeRow[];
    kinds: { value: string; label: string }[];
    ticketsUrl: string;
}

// Devises sans subdivision (§4.2 CLAUDE.md) : le montant saisi est déjà en
// unité mineure, pas de conversion ×100.
const ZERO_DECIMAL_CURRENCIES = new Set(['XOF', 'XAF']);

function toMinorUnits(amount: string, currency: string): number {
    const value = Number(amount.replace(',', '.')) || 0;

    return ZERO_DECIMAL_CURRENCIES.has(currency) ? Math.round(value) : Math.round(value * 100);
}

function toMajorText(minor: number | null, currency: string): string {
    if (minor === null) {
        return '';
    }

    return ZERO_DECIMAL_CURRENCIES.has(currency) ? String(minor) : String(minor / 100);
}

function usesLabel(row: PromoCodeRow): string {
    const used = row.uses > 1 ? `${row.uses} utilisations` : `${row.uses} utilisation`;

    return row.maxUses === null ? `${used} · sans limite` : `${used} sur ${row.maxUses}`;
}

/**
 * Codes promo d'un événement : une réduction sur le total des billets d'un
 * panier. Les dons ne sont jamais remisés.
 */
export default function PromoCodesIndex({ event, promoCodes, kinds, ticketsUrl }: PromoCodesPageProps) {
    const [editing, setEditing] = useState<PromoCodeRow | null>(null);
    const [creating, setCreating] = useState(false);

    function close() {
        setEditing(null);
        setCreating(false);
    }

    return (
        <EventLayout title="Codes promo" eyebrow={event.title}>
            <Head title={`Codes promo — ${event.title}`} />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Une réduction sur le total des billets d'un panier : en pourcentage ou en montant. Un don ajouté au panier n'est jamais remisé.{' '}
                    <Link href={ticketsUrl} className="text-ink underline hover:no-underline">
                        Voir les billets
                    </Link>
                    .
                </p>
                {!creating && editing === null && (
                    <Button type="button" onClick={() => setCreating(true)} className="w-auto px-6 py-2">
                        + Nouveau code
                    </Button>
                )}
            </div>

            {(creating || editing !== null) && (
                <PromoCodeForm key={editing?.id ?? 'new'} event={event} kinds={kinds} row={editing} onDone={close} />
            )}

            {promoCodes.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun code promo</p>
                    <p className="text-sm text-ink-soft">Créez-en un pour offrir une réduction à vos invités.</p>
                </div>
            ) : (
                <ul className="space-y-3">
                    {promoCodes.map((row) => (
                        <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-bg p-4">
                            <div className="min-w-0">
                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                    <code className="rounded bg-bg-alt px-2 py-1 font-label text-sm tracking-[0.08em] text-ink">{row.code}</code>
                                    <Badge variant={row.isActive ? 'success' : 'neutral'}>{row.isActive ? 'Actif' : 'Désactivé'}</Badge>
                                </div>
                                <p className="text-sm text-ink-soft">
                                    −{row.reduction} · {usesLabel(row)}
                                    {row.endsAt !== null && ` · jusqu'au ${row.endsAt.replace('T', ' à ')}`}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-4 text-sm">
                                <button type="button" onClick={() => setEditing(row)} className="text-ink underline hover:no-underline">
                                    Modifier
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (window.confirm(`Supprimer le code « ${row.code} » ? Il ne fonctionnera plus.`)) {
                                            router.delete(`/promo-codes/${row.id}`, { preserveScroll: true });
                                        }
                                    }}
                                    className="text-danger underline hover:no-underline"
                                >
                                    Supprimer
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}

interface PromoCodeFormProps {
    event: { id: number; currency: string };
    kinds: { value: string; label: string }[];
    row: PromoCodeRow | null;
    onDone: () => void;
}

function PromoCodeForm({ event, kinds, row, onDone }: PromoCodeFormProps) {
    const form = useForm({
        code: row?.code ?? '',
        kind: row?.kind ?? 'percent',
        percent: row?.percent != null ? String(row.percent) : '10',
        amount: toMajorText(row?.amountMinor ?? null, event.currency),
        // Envoyé au serveur en unité mineure ; « amount » reste la saisie lisible.
        amount_minor: '',
        max_uses: row?.maxUses != null ? String(row.maxUses) : '',
        starts_at: row?.startsAt ?? '',
        ends_at: row?.endsAt ?? '',
        is_active: row?.isActive ?? true,
    });

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();

        const payload = {
            ...form.data,
            percent: form.data.kind === 'percent' ? form.data.percent : '',
            amount_minor: form.data.kind === 'amount' ? String(toMinorUnits(form.data.amount, event.currency)) : '',
        };

        const options = { preserveScroll: true, onSuccess: onDone };

        if (row === null) {
            router.post(`/events/${event.id}/codes-promo`, payload, { ...options, onError: (errors) => form.setError(errors) });
        } else {
            router.patch(`/promo-codes/${row.id}`, payload, { ...options, onError: (errors) => form.setError(errors) });
        }
    }

    return (
        <form onSubmit={submit} className="mb-6 rounded-card border border-line bg-bg p-5">
            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="code">Code</InputLabel>
                    <TextInput
                        id="code"
                        value={form.data.code}
                        onChange={(changeEvent) => form.setData('code', changeEvent.target.value.toUpperCase())}
                        maxLength={40}
                        placeholder="EARLY2026"
                        required
                    />
                    <InputError message={form.errors.code} />
                </div>
                <div>
                    <InputLabel htmlFor="kind">Type de réduction</InputLabel>
                    <Select id="kind" value={form.data.kind} onChange={(changeEvent) => form.setData('kind', changeEvent.target.value)}>
                        {kinds.map((kind) => (
                            <option key={kind.value} value={kind.value}>
                                {kind.label}
                            </option>
                        ))}
                    </Select>
                </div>
                {form.data.kind === 'percent' ? (
                    <div>
                        <InputLabel htmlFor="percent">Pourcentage</InputLabel>
                        <TextInput
                            id="percent"
                            type="number"
                            min={1}
                            max={100}
                            value={form.data.percent}
                            onChange={(changeEvent) => form.setData('percent', changeEvent.target.value)}
                        />
                        <InputError message={form.errors.percent} />
                    </div>
                ) : (
                    <div>
                        <InputLabel htmlFor="amount">Montant ({event.currency})</InputLabel>
                        <TextInput
                            id="amount"
                            value={form.data.amount}
                            onChange={(changeEvent) => form.setData('amount', changeEvent.target.value)}
                            placeholder="5"
                        />
                        <InputError message={form.errors.amount_minor} />
                    </div>
                )}
                <div>
                    <InputLabel htmlFor="max_uses">Nombre d'utilisations (optionnel)</InputLabel>
                    <TextInput
                        id="max_uses"
                        type="number"
                        min={1}
                        placeholder="Sans limite"
                        value={form.data.max_uses}
                        onChange={(changeEvent) => form.setData('max_uses', changeEvent.target.value)}
                    />
                    <InputError message={form.errors.max_uses} />
                </div>
                <div>
                    <InputLabel htmlFor="starts_at">Début (optionnel)</InputLabel>
                    <TextInput
                        id="starts_at"
                        type="datetime-local"
                        value={form.data.starts_at}
                        onChange={(changeEvent) => form.setData('starts_at', changeEvent.target.value)}
                    />
                    <InputError message={form.errors.starts_at} />
                </div>
                <div>
                    <InputLabel htmlFor="ends_at">Fin (optionnel)</InputLabel>
                    <TextInput
                        id="ends_at"
                        type="datetime-local"
                        value={form.data.ends_at}
                        onChange={(changeEvent) => form.setData('ends_at', changeEvent.target.value)}
                    />
                    <InputError message={form.errors.ends_at} />
                </div>
            </div>

            <p className="mt-2 text-xs text-ink-soft">Les dates sont à l'heure de l'événement.</p>

            <label className="mt-4 flex cursor-pointer items-center gap-3 text-sm text-ink">
                <Checkbox checked={form.data.is_active} onChange={(changeEvent) => form.setData('is_active', changeEvent.target.checked)} />
                Code actif
            </label>

            <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="secondary" onClick={onDone} className="sm:w-auto">
                    Annuler
                </Button>
                <Button type="submit" disabled={form.processing} className="sm:w-auto">
                    {row === null ? 'Créer le code' : 'Enregistrer'}
                </Button>
            </div>
        </form>
    );
}
