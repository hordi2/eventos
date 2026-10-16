import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

interface OrderRow {
    id: number;
    buyerName: string;
    buyerEmail: string;
    status: 'pending' | 'payment_on_site' | 'paid' | 'failed' | 'expired' | 'refunded';
    total: string;
    refunded: string;
    hasRefund: boolean;
    remainingRefundable: string;
    canRefund: boolean;
    provider: string | null;
    paidAt: string | null;
}

interface Props {
    event: { id: number; title: string };
    orders: OrderRow[];
}

const STATUS_LABELS: Record<OrderRow['status'], string> = {
    pending: 'En attente',
    payment_on_site: 'Paiement à l\'arrivée',
    paid: 'Payée',
    failed: 'Échouée',
    expired: 'Expirée',
    refunded: 'Remboursée',
};

const PROVIDER_LABELS: Record<string, string> = {
    stripe: 'Carte',
    flutterwave: 'Mobile Money',
    cash: 'Espèces',
};

const CARD = 'rounded-card border border-line bg-bg p-5';

/**
 * Commandes d'un événement (M5.5) : ce qu'elles portent, et leur
 * remboursement total ou partiel. Un remboursement espèces ne touche aucun
 * prestataire — c'est à l'organisateur de rendre l'argent lui-même.
 */
export default function Orders({ event, orders }: Props) {
    return (
        <EventLayout title="Commandes" eyebrow={event.title}>
            <Head title={`Commandes — ${event.title}`} />

            {orders.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucune commande pour l'instant</p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {orders.map((order) => (
                        <OrderCard key={order.id} event={event} order={order} />
                    ))}
                </ul>
            )}
        </EventLayout>
    );
}

function OrderCard({ event, order }: { event: Props['event']; order: OrderRow }) {
    const [amount, setAmount] = useState('');
    const [processing, setProcessing] = useState(false);

    function submitRefund(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        setProcessing(true);
        router.post(
            `/events/${event.id}/orders/${order.id}/refund`,
            { amount: amount || undefined },
            { preserveScroll: true, onFinish: () => setProcessing(false), onSuccess: () => setAmount('') },
        );
    }

    return (
        <li className={CARD}>
            <div className="mb-2 flex flex-wrap items-center gap-3">
                <p className="text-ink">{order.buyerName}</p>
                <span className="text-sm text-ink-soft">{order.buyerEmail}</span>
                <Badge variant={order.status === 'paid' ? 'success' : order.status === 'refunded' ? 'neutral' : order.status === 'failed' || order.status === 'expired' ? 'danger' : 'neutral'}>
                    {STATUS_LABELS[order.status]}
                </Badge>
                {order.provider && <span className="text-xs text-ink-soft">{PROVIDER_LABELS[order.provider] ?? order.provider}</span>}
            </div>

            <div className="flex flex-wrap gap-6 text-sm text-ink-soft tabular-nums">
                <span>Total : <span className="text-ink">{order.total}</span></span>
                {order.hasRefund && <span>Remboursé : <span className="text-ink">{order.refunded}</span></span>}
            </div>

            {order.canRefund && (
                <form onSubmit={submitRefund} className="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
                    <div>
                        <label className="mb-1 block text-xs text-ink-soft">
                            Montant à rembourser (vide = tout, {order.remainingRefundable})
                        </label>
                        <TextInput
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            placeholder={order.remainingRefundable}
                            className="w-40"
                        />
                    </div>
                    <Button type="submit" disabled={processing} className="w-auto px-5 py-2 text-sm">
                        {processing ? 'Remboursement…' : 'Rembourser'}
                    </Button>
                </form>
            )}
        </li>
    );
}
