import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Modal from '../../Components/Modal';
import Select from '../../Components/Select';
import Table from '../../Components/Table';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface Tier {
    id: number;
    name: string;
    amount_minor: number;
    currency: string;
    quantity: number | null;
    remaining: number | null;
    starts_at: string | null;
    ends_at: string | null;
}

interface TicketTypeRow {
    id: number;
    name: string;
    description: string | null;
    is_free: boolean;
    currency: string;
    min_per_order: number;
    max_per_order: number | null;
    total_quantity: number | null;
    remaining: number | null;
    vat_mode: string;
    vat_rate_bp: number;
    fees_absorbed: boolean;
    is_active: boolean;
    tiers: Tier[];
}

interface VatModeOption {
    value: string;
    label: string;
}

// Devises sans subdivision (§4.2 CLAUDE.md, même liste que Money::format()
// côté serveur) : un montant saisi dans ces devises est déjà en unité
// mineure, pas de conversion ×100 à faire.
const ZERO_DECIMAL_CURRENCIES = new Set(['XOF', 'XAF']);

function toMinorUnits(amount: string, currency: string): number {
    const value = Number(amount.replace(',', '.')) || 0;

    return ZERO_DECIMAL_CURRENCIES.has(currency) ? Math.round(value) : Math.round(value * 100);
}

function formatAmount(minor: number, currency: string): string {
    const divisor = ZERO_DECIMAL_CURRENCIES.has(currency) ? 1 : 100;

    return `${(minor / divisor).toLocaleString('fr-FR', { minimumFractionDigits: divisor === 1 ? 0 : 2 })} ${currency}`;
}

interface TierDraft {
    name: string;
    amount: string;
    quantity: string;
    starts_at: string;
    ends_at: string;
}

function emptyTier(): TierDraft {
    return { name: '', amount: '', quantity: '', starts_at: '', ends_at: '' };
}

function AddTierForm({ ticketTypeId, currency }: { ticketTypeId: number; currency: string }) {
    const [tier, setTier] = useState<TierDraft>(emptyTier());
    const [processing, setProcessing] = useState(false);

    function submit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);

        router.post(
            `/ticket-types/${ticketTypeId}/price-tiers`,
            {
                name: tier.name,
                amount_minor: toMinorUnits(tier.amount, currency),
                quantity: tier.quantity === '' ? null : Number(tier.quantity),
                starts_at: tier.starts_at || null,
                ends_at: tier.ends_at || null,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setTier(emptyTier()),
            },
        );
    }

    return (
        <form onSubmit={submit} className="mt-2 flex flex-wrap items-center gap-2">
            <TextInput
                placeholder="Nom du palier"
                value={tier.name}
                onChange={(e) => setTier({ ...tier, name: e.target.value })}
                className="w-32"
            />
            <TextInput
                placeholder={`Prix (${currency})`}
                value={tier.amount}
                onChange={(e) => setTier({ ...tier, amount: e.target.value })}
                className="w-24"
            />
            <TextInput
                placeholder="Quota"
                type="number"
                min={1}
                value={tier.quantity}
                onChange={(e) => setTier({ ...tier, quantity: e.target.value })}
                className="w-20"
            />
            <button type="submit" disabled={processing || tier.name.trim() === ''} className="text-xs text-ink underline underline-offset-2 disabled:opacity-50">
                Ajouter
            </button>
        </form>
    );
}

function EditTicketTypeModal({
    ticketType,
    vatModes,
    onClose,
}: {
    ticketType: TicketTypeRow;
    vatModes: VatModeOption[];
    onClose: () => void;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        name: ticketType.name,
        description: ticketType.description ?? '',
        min_per_order: ticketType.min_per_order,
        max_per_order: ticketType.max_per_order === null ? '' : String(ticketType.max_per_order),
        total_quantity: ticketType.total_quantity === null ? '' : String(ticketType.total_quantity),
        vat_mode: ticketType.vat_mode,
        vat_rate_bp: ticketType.vat_rate_bp,
        fees_absorbed: ticketType.fees_absorbed,
        is_active: ticketType.is_active,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        patch(`/ticket-types/${ticketType.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <Modal open onClose={onClose} title={`Modifier « ${ticketType.name} »`}>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="edit_name">Nom</InputLabel>
                    <TextInput id="edit_name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    <InputError message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="edit_description">Description</InputLabel>
                    <TextInput id="edit_description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="edit_min">Min. par commande</InputLabel>
                        <TextInput
                            id="edit_min"
                            type="number"
                            min={1}
                            value={data.min_per_order}
                            onChange={(e) => setData('min_per_order', Number(e.target.value))}
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="edit_max">Max. par commande</InputLabel>
                        <TextInput
                            id="edit_max"
                            type="number"
                            min={1}
                            value={data.max_per_order}
                            onChange={(e) => setData('max_per_order', e.target.value)}
                        />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="edit_total_quantity">Quota global (vide = illimité)</InputLabel>
                    <TextInput
                        id="edit_total_quantity"
                        type="number"
                        min={0}
                        value={data.total_quantity}
                        onChange={(e) => setData('total_quantity', e.target.value)}
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="edit_vat_mode">TVA</InputLabel>
                        <Select id="edit_vat_mode" value={data.vat_mode} onChange={(e) => setData('vat_mode', e.target.value)}>
                            {vatModes.map((mode) => (
                                <option key={mode.value} value={mode.value}>
                                    {mode.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    {data.vat_mode !== 'none' && (
                        <div>
                            <InputLabel htmlFor="edit_vat_rate">Taux (%)</InputLabel>
                            <TextInput
                                id="edit_vat_rate"
                                type="number"
                                step="0.01"
                                min={0}
                                value={data.vat_rate_bp / 100}
                                onChange={(e) => setData('vat_rate_bp', Math.round(Number(e.target.value) * 100))}
                            />
                        </div>
                    )}
                </div>

                <div>
                    <InputLabel>Frais de paiement</InputLabel>
                    <div className="flex gap-6">
                        <label className="flex items-center gap-2 text-sm text-ink">
                            <input type="radio" checked={data.fees_absorbed === true} onChange={() => setData('fees_absorbed', true)} />
                            Absorbés
                        </label>
                        <label className="flex items-center gap-2 text-sm text-ink">
                            <input type="radio" checked={data.fees_absorbed === false} onChange={() => setData('fees_absorbed', false)} />
                            Répercutés
                        </label>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox id="edit_is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                    <InputLabel htmlFor="edit_is_active" className="mb-0">Actif (visible à l&apos;achat)</InputLabel>
                </div>

                <div className="flex gap-3">
                    <Button type="submit" disabled={processing} className="w-auto">
                        Enregistrer
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose} className="w-auto">
                        Annuler
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({
    event,
    ticketTypes,
    vatModes,
}: {
    event: { id: number; title: string; currency: string };
    ticketTypes: TicketTypeRow[];
    vatModes: VatModeOption[];
}) {
    const { data, setData, processing, errors, reset } = useForm<{
        name: string;
        description: string;
        is_free: boolean;
        currency: string;
        min_per_order: number;
        max_per_order: string;
        total_quantity: string;
        vat_mode: string;
        vat_rate_bp: number;
        fees_absorbed: boolean | null;
        tiers: TierDraft[];
    }>({
        name: '',
        description: '',
        is_free: false,
        currency: event.currency,
        min_per_order: 1,
        max_per_order: '',
        total_quantity: '',
        vat_mode: 'none',
        vat_rate_bp: 0,
        fees_absorbed: null,
        tiers: [emptyTier()],
    });

    const [editingTicketType, setEditingTicketType] = useState<TicketTypeRow | null>(null);

    function addTierRow() {
        setData('tiers', [...data.tiers, emptyTier()]);
    }

    function removeTierRow(index: number) {
        setData('tiers', data.tiers.filter((_, i) => i !== index));
    }

    function updateTierRow(index: number, field: keyof TierDraft, value: string) {
        setData(
            'tiers',
            data.tiers.map((tier, i) => (i === index ? { ...tier, [field]: value } : tier)),
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        router.post(
            `/events/${event.id}/ticket-types`,
            {
                name: data.name,
                description: data.description || null,
                is_free: data.is_free,
                currency: data.currency,
                min_per_order: data.min_per_order,
                max_per_order: data.max_per_order === '' ? null : Number(data.max_per_order),
                total_quantity: data.total_quantity === '' ? null : Number(data.total_quantity),
                vat_mode: data.vat_mode,
                vat_rate_bp: data.vat_mode === 'none' ? 0 : data.vat_rate_bp,
                fees_absorbed: data.fees_absorbed,
                tiers: data.is_free
                    ? []
                    : data.tiers
                          .filter((tier) => tier.name.trim() !== '')
                          .map((tier) => ({
                              name: tier.name,
                              amount_minor: toMinorUnits(tier.amount, data.currency),
                              quantity: tier.quantity === '' ? null : Number(tier.quantity),
                              starts_at: tier.starts_at || null,
                              ends_at: tier.ends_at || null,
                          })),
            },
            {
                onSuccess: () => reset(),
                preserveScroll: true,
            },
        );
    }

    function deleteTicketType(ticketType: TicketTypeRow) {
        if (confirm(`Supprimer le type de billet « ${ticketType.name} » ?`)) {
            router.delete(`/ticket-types/${ticketType.id}`, { preserveScroll: true });
        }
    }

    function deleteTier(tier: Tier) {
        if (confirm(`Supprimer le palier « ${tier.name} » ?`)) {
            router.delete(`/price-tiers/${tier.id}`, { preserveScroll: true });
        }
    }

    return (
        <OrganizerLayout title="Types de billets" eyebrow={event.title}>
            <Head title="Types de billets" />

            <div className="mb-8 flex justify-end gap-4">
                <Link href={`/events/${event.id}/automations`} className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase hover:text-ink">
                    Automatisations
                </Link>
                <Link href={`/events/${event.id}/segments`} className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase hover:text-ink">
                    Segments
                </Link>
            </div>

            <form onSubmit={submit} className="mb-10 space-y-6 rounded-card border border-line bg-bg p-6">
                <h2 className="font-serif text-lg text-ink italic">Nouveau type de billet</h2>

                <div className="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="name">Nom</InputLabel>
                        <TextInput id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex items-end gap-2">
                        <Checkbox
                            id="is_free"
                            checked={data.is_free}
                            onChange={(e) => setData('is_free', e.target.checked)}
                        />
                        <InputLabel htmlFor="is_free" className="mb-0">Billet gratuit</InputLabel>
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="description">Description (optionnel)</InputLabel>
                    <TextInput id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                </div>

                <div className="grid gap-6 sm:grid-cols-3">
                    <div>
                        <InputLabel htmlFor="min_per_order">Quantité min. par commande</InputLabel>
                        <TextInput
                            id="min_per_order"
                            type="number"
                            min={1}
                            value={data.min_per_order}
                            onChange={(e) => setData('min_per_order', Number(e.target.value))}
                        />
                        <InputError message={errors.min_per_order} />
                    </div>
                    <div>
                        <InputLabel htmlFor="max_per_order">Quantité max. par commande (optionnel)</InputLabel>
                        <TextInput
                            id="max_per_order"
                            type="number"
                            min={1}
                            value={data.max_per_order}
                            onChange={(e) => setData('max_per_order', e.target.value)}
                        />
                        <InputError message={errors.max_per_order} />
                    </div>
                    <div>
                        <InputLabel htmlFor="total_quantity">Quota global (optionnel, illimité si vide)</InputLabel>
                        <TextInput
                            id="total_quantity"
                            type="number"
                            min={0}
                            value={data.total_quantity}
                            onChange={(e) => setData('total_quantity', e.target.value)}
                        />
                    </div>
                </div>

                {!data.is_free && (
                    <div className="grid gap-6 sm:grid-cols-3">
                        <div>
                            <InputLabel htmlFor="currency">Devise</InputLabel>
                            <TextInput id="currency" value={data.currency} onChange={(e) => setData('currency', e.target.value.toUpperCase())} maxLength={3} />
                        </div>
                        <div>
                            <InputLabel htmlFor="vat_mode">TVA</InputLabel>
                            <Select id="vat_mode" value={data.vat_mode} onChange={(e) => setData('vat_mode', e.target.value)}>
                                {vatModes.map((mode) => (
                                    <option key={mode.value} value={mode.value}>
                                        {mode.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        {data.vat_mode !== 'none' && (
                            <div>
                                <InputLabel htmlFor="vat_rate_bp">Taux de TVA (%)</InputLabel>
                                <TextInput
                                    id="vat_rate_bp"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.vat_rate_bp / 100}
                                    onChange={(e) => setData('vat_rate_bp', Math.round(Number(e.target.value) * 100))}
                                />
                            </div>
                        )}
                    </div>
                )}

                <div>
                    <InputLabel>Frais de paiement — choix explicite requis</InputLabel>
                    <div className="flex gap-6">
                        <label className="flex items-center gap-2 text-sm text-ink">
                            <input
                                type="radio"
                                name="fees_absorbed"
                                checked={data.fees_absorbed === true}
                                onChange={() => setData('fees_absorbed', true)}
                            />
                            Absorbés par l&apos;organisateur
                        </label>
                        <label className="flex items-center gap-2 text-sm text-ink">
                            <input
                                type="radio"
                                name="fees_absorbed"
                                checked={data.fees_absorbed === false}
                                onChange={() => setData('fees_absorbed', false)}
                            />
                            Répercutés sur l&apos;acheteur
                        </label>
                    </div>
                    <InputError message={errors.fees_absorbed} />
                </div>

                {!data.is_free && (
                    <div>
                        <div className="mb-2.5 flex items-center justify-between">
                            <InputLabel className="mb-0">Paliers de tarification</InputLabel>
                            <button type="button" onClick={addTierRow} className="text-sm text-ink underline underline-offset-2">
                                + Ajouter un palier
                            </button>
                        </div>

                        <div className="space-y-3">
                            {data.tiers.map((tier, index) => (
                                <div key={index} className="grid grid-cols-1 gap-3 rounded-card border border-line p-4 sm:grid-cols-5">
                                    <TextInput
                                        placeholder="Nom (ex. Early bird)"
                                        value={tier.name}
                                        onChange={(e) => updateTierRow(index, 'name', e.target.value)}
                                    />
                                    <TextInput
                                        placeholder={`Prix (${data.currency})`}
                                        value={tier.amount}
                                        onChange={(e) => updateTierRow(index, 'amount', e.target.value)}
                                    />
                                    <TextInput
                                        placeholder="Quota (optionnel)"
                                        type="number"
                                        min={1}
                                        value={tier.quantity}
                                        onChange={(e) => updateTierRow(index, 'quantity', e.target.value)}
                                    />
                                    <TextInput
                                        type="datetime-local"
                                        value={tier.starts_at}
                                        onChange={(e) => updateTierRow(index, 'starts_at', e.target.value)}
                                    />
                                    <div className="flex gap-2">
                                        <TextInput
                                            type="datetime-local"
                                            value={tier.ends_at}
                                            onChange={(e) => updateTierRow(index, 'ends_at', e.target.value)}
                                        />
                                        {data.tiers.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeTierRow(index)}
                                                className="shrink-0 text-sm text-danger"
                                                aria-label="Supprimer ce palier"
                                            >
                                                ×
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <InputError message={errors.name} />
                    </div>
                )}

                <Button type="submit" disabled={processing || data.fees_absorbed === null} className="w-auto">
                    Créer le type de billet
                </Button>
            </form>

            <Table
                rowKey={(row) => row.id}
                emptyMessage="Aucun type de billet pour l'instant."
                columns={[
                    {
                        key: 'name',
                        header: 'Nom',
                        render: (row) => (
                            <div>
                                <span className="text-ink">{row.name}</span>
                                {!row.is_active && (
                                    <Badge variant="neutral" >
                                        Inactif
                                    </Badge>
                                )}
                            </div>
                        ),
                    },
                    {
                        key: 'price',
                        header: 'Tarif',
                        render: (row) =>
                            row.is_free ? (
                                <Badge variant="success">Gratuit</Badge>
                            ) : (
                                <div className="space-y-1">
                                    {row.tiers.map((tier) => (
                                        <div key={tier.id} className="flex items-center gap-2">
                                            <span>{tier.name} — {formatAmount(tier.amount_minor, tier.currency)}</span>
                                            <span className="text-xs text-ink-soft">
                                                {tier.remaining === null ? 'illimité' : `${tier.remaining} restant(s)`}
                                            </span>
                                            <button type="button" onClick={() => deleteTier(tier)} className="text-xs text-danger">
                                                Supprimer
                                            </button>
                                        </div>
                                    ))}
                                    <AddTierForm ticketTypeId={row.id} currency={row.currency} />
                                </div>
                            ),
                    },
                    {
                        key: 'quota',
                        header: 'Quota global',
                        render: (row) => (row.remaining === null ? 'Illimité' : `${row.remaining} restant(s) sur ${row.total_quantity}`),
                    },
                    {
                        key: 'fees',
                        header: 'Frais',
                        render: (row) => (row.fees_absorbed ? 'Absorbés' : 'Répercutés'),
                    },
                    {
                        key: 'actions',
                        header: '',
                        render: (row) => (
                            <div className="flex gap-3">
                                <button type="button" onClick={() => setEditingTicketType(row)} className="text-sm text-ink hover:opacity-80">
                                    Modifier
                                </button>
                                <button type="button" onClick={() => deleteTicketType(row)} className="text-sm text-danger hover:opacity-80">
                                    Supprimer
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={ticketTypes}
            />

            {editingTicketType && (
                <EditTicketTypeModal
                    ticketType={editingTicketType}
                    vatModes={vatModes}
                    onClose={() => setEditingTicketType(null)}
                />
            )}
        </OrganizerLayout>
    );
}
