import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import Textarea from '../../Components/Textarea';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

type Kind = 'expense' | 'income';

interface Line {
    id: number;
    kind: Kind;
    category: string;
    categoryLabel: string;
    label: string;
    supplier: string | null;
    note: string | null;
    planned: string;
    actual: string | null;
    plannedInput: string;
    actualInput: string;
    isOverrun: boolean;
}

interface Budget {
    currency: string;
    totals: {
        plannedExpenses: string;
        actualExpenses: string;
        plannedIncomes: string;
        actualIncomes: string;
        ticketingRevenue: string;
    };
    result: { amount: string; isPositive: boolean };
    toCover: { amount: string; isCovered: boolean; ticketsToSell: number | null };
    overrunCount: number;
    lines: Line[];
}

interface BudgetPageProps {
    event: { id: number; title: string; currency: string };
    budget: Budget;
    categories: { value: string; label: string }[];
    ticketsUrl: string;
}

const CARD = 'rounded-card border border-line bg-bg p-5';

/**
 * Suivi budgétaire de l'événement (D7) : ce qui est prévu, ce qui est
 * engagé, ce que rapporte la billetterie, et ce qu'il reste à couvrir.
 */
export default function Budget({ event, budget, categories, ticketsUrl }: BudgetPageProps) {
    const [adding, setAdding] = useState<Kind | null>(null);
    const [editing, setEditing] = useState<Line | null>(null);

    function close() {
        setAdding(null);
        setEditing(null);
    }

    return (
        <EventLayout title="Budget et rentabilité" eyebrow={event.title}>
            <Head title={`Budget — ${event.title}`} />

            <section className="mb-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Tile label="Dépenses prévues" value={budget.totals.plannedExpenses} hint={`Engagé : ${budget.totals.actualExpenses}`} />
                <Tile label="Billetterie encaissée" value={budget.totals.ticketingRevenue} hint={`Autres recettes : ${budget.totals.actualIncomes}`} />
                <Tile
                    label="Résultat"
                    value={budget.result.amount}
                    hint={budget.result.isPositive ? 'Recettes encaissées moins dépenses engagées' : 'Les dépenses engagées dépassent les recettes'}
                    tone={budget.result.isPositive ? 'good' : 'bad'}
                />
                <Tile
                    label="Reste à couvrir"
                    value={budget.toCover.isCovered ? 'Couvert' : budget.toCover.amount}
                    hint={
                        budget.toCover.isCovered
                            ? 'Le budget est couvert.'
                            : budget.toCover.ticketsToSell === null
                              ? 'Aucun billet en vente pour le chiffrer.'
                              : `Soit ${budget.toCover.ticketsToSell} billet${budget.toCover.ticketsToSell > 1 ? 's' : ''} à vendre.`
                    }
                    tone={budget.toCover.isCovered ? 'good' : 'neutral'}
                />
            </section>

            {budget.overrunCount > 0 && (
                <p className="mb-8 rounded-card border border-danger/40 bg-danger-bg px-4 py-3 text-sm text-danger">
                    {budget.overrunCount === 1
                        ? 'Un poste dépasse le montant prévu.'
                        : `${budget.overrunCount} postes dépassent le montant prévu.`}{' '}
                    Ils sont signalés dans la liste ci-dessous.
                </p>
            )}

            {(adding !== null || editing !== null) && (
                <LineForm
                    key={editing?.id ?? adding ?? 'new'}
                    event={event}
                    categories={categories}
                    kind={editing?.kind ?? adding ?? 'expense'}
                    line={editing}
                    onDone={close}
                />
            )}

            <LineTable
                title="Dépenses"
                empty="Aucun poste de dépense. Commencez par le lieu et le traiteur."
                lines={budget.lines.filter((line) => line.kind === 'expense')}
                event={event}
                onAdd={() => {
                    close();
                    setAdding('expense');
                }}
                onEdit={(line) => {
                    close();
                    setEditing(line);
                }}
            />

            <LineTable
                title="Recettes hors billetterie"
                empty="Aucune recette prévue hors billetterie : sponsors, subventions, partenariats."
                lines={budget.lines.filter((line) => line.kind === 'income')}
                event={event}
                onAdd={() => {
                    close();
                    setAdding('income');
                }}
                onEdit={(line) => {
                    close();
                    setEditing(line);
                }}
            />

            <p className="mt-10 text-sm text-ink-soft">
                Ce que rapporte la billetterie n'est pas à saisir : il est lu dans les commandes payées.{' '}
                <Link href={ticketsUrl} className="text-ink underline hover:no-underline">
                    Billets et tarifs
                </Link>
                .
            </p>
        </EventLayout>
    );
}

function Tile({ label, value, hint, tone = 'neutral' }: { label: string; value: string; hint: string; tone?: 'neutral' | 'good' | 'bad' }) {
    const valueClass = tone === 'bad' ? 'text-danger' : tone === 'good' ? 'text-success' : 'text-ink';

    return (
        <div className={CARD}>
            <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">{label}</p>
            <p className={`mt-2 font-serif text-2xl ${valueClass}`}>{value}</p>
            <p className="mt-1 text-xs text-ink-soft">{hint}</p>
        </div>
    );
}

interface LineTableProps {
    title: string;
    empty: string;
    lines: Line[];
    event: { id: number };
    onAdd: () => void;
    onEdit: (line: Line) => void;
}

function LineTable({ title, empty, lines, event, onAdd, onEdit }: LineTableProps) {
    return (
        <section className="mb-10">
            <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                <h2 className="font-serif text-xl italic">{title}</h2>
                <button type="button" onClick={onAdd} className="text-sm text-ink underline hover:no-underline">
                    + Ajouter un poste
                </button>
            </div>

            {lines.length === 0 ? (
                <p className="rounded-card border border-dashed border-line bg-bg px-6 py-8 text-center text-sm text-ink-soft">{empty}</p>
            ) : (
                <ul className="space-y-2">
                    {lines.map((line) => (
                        <li key={line.id} className={`${CARD} flex flex-wrap items-start justify-between gap-4`}>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-3">
                                    <p className="text-ink">{line.label}</p>
                                    <Badge>{line.categoryLabel}</Badge>
                                    {line.isOverrun && <Badge variant="danger">Dépassement</Badge>}
                                </div>
                                {(line.supplier || line.note) && (
                                    <p className="text-sm text-ink-soft">{[line.supplier, line.note].filter(Boolean).join(' · ')}</p>
                                )}
                            </div>
                            <div className="flex items-start gap-6">
                                <div className="text-right tabular-nums">
                                    <p className="text-ink">{line.actual ?? '—'}</p>
                                    <p className="text-xs text-ink-soft">prévu {line.planned}</p>
                                </div>
                                <div className="flex gap-4 text-sm">
                                    <button type="button" onClick={() => onEdit(line)} className="text-ink underline hover:no-underline">
                                        Modifier
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (window.confirm(`Retirer « ${line.label} » du budget ?`)) {
                                                router.delete(`/events/${event.id}/budget/${line.id}`, { preserveScroll: true });
                                            }
                                        }}
                                        className="text-danger underline hover:no-underline"
                                    >
                                        Retirer
                                    </button>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

interface LineFormProps {
    event: { id: number; currency: string };
    categories: { value: string; label: string }[];
    kind: Kind;
    line: Line | null;
    onDone: () => void;
}

function LineForm({ event, categories, kind, line, onDone }: LineFormProps) {
    const form = useForm({
        kind: line?.kind ?? kind,
        category: line?.category ?? (kind === 'income' ? 'sponsoring' : 'venue'),
        label: line?.label ?? '',
        supplier: line?.supplier ?? '',
        note: line?.note ?? '',
        planned: line?.plannedInput ?? '',
        actual: line?.actualInput ?? '',
    });

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();

        if (line === null) {
            form.post(`/events/${event.id}/budget`, { preserveScroll: true, onSuccess: onDone });
        } else {
            form.patch(`/events/${event.id}/budget/${line.id}`, { preserveScroll: true, onSuccess: onDone });
        }
    }

    return (
        <form onSubmit={submit} className={`${CARD} mb-8`}>
            <h2 className="mb-4 font-serif text-xl italic">
                {line === null ? (kind === 'income' ? 'Nouvelle recette' : 'Nouveau poste de dépense') : 'Modifier le poste'}
            </h2>

            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="label">Intitulé</InputLabel>
                    <TextInput id="label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} required placeholder="Location de la salle" />
                    <InputError message={form.errors.label} />
                </div>
                <div>
                    <InputLabel htmlFor="category">Poste</InputLabel>
                    <Select id="category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                        {categories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label}
                            </option>
                        ))}
                    </Select>
                    <InputError message={form.errors.category} />
                </div>
                <div>
                    <InputLabel htmlFor="planned">Prévu ({event.currency})</InputLabel>
                    <TextInput id="planned" value={form.data.planned} onChange={(e) => form.setData('planned', e.target.value)} required placeholder="1500" inputMode="decimal" />
                    <InputError message={form.errors.planned} />
                </div>
                <div>
                    <InputLabel htmlFor="actual">Réalisé ({event.currency})</InputLabel>
                    <TextInput id="actual" value={form.data.actual} onChange={(e) => form.setData('actual', e.target.value)} placeholder="Laissez vide tant que rien n'est engagé" inputMode="decimal" />
                    <InputError message={form.errors.actual} />
                </div>
                <div>
                    <InputLabel htmlFor="supplier">{kind === 'income' ? 'Source (optionnel)' : 'Prestataire (optionnel)'}</InputLabel>
                    <TextInput id="supplier" value={form.data.supplier} onChange={(e) => form.setData('supplier', e.target.value)} />
                    <InputError message={form.errors.supplier} />
                </div>
                <div className="sm:col-span-2">
                    <InputLabel htmlFor="note">Note (optionnel)</InputLabel>
                    <Textarea id="note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} rows={2} maxLength={2000} />
                    <InputError message={form.errors.note} />
                </div>
            </div>

            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="secondary" onClick={onDone} className="sm:w-auto">
                    Annuler
                </Button>
                <Button type="submit" disabled={form.processing} className="sm:w-auto">
                    Enregistrer
                </Button>
            </div>
        </form>
    );
}
