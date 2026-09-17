import { Head } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import EventLayout from '../../Layouts/EventLayout';

type DonationStatus = 'paid' | 'payment_on_site' | 'pending' | 'failed' | 'expired' | 'refunded';

interface DonationRow {
    id: number;
    donor: string;
    email: string;
    isAnonymous: boolean;
    amount: string;
    cause: string | null;
    status: DonationStatus;
    statusLabel: string;
    promisedAt: string;
    paidAt: string | null;
    guestName: string | null;
}

interface DonationsPageProps {
    event: { id: number; title: string };
    donations: DonationRow[];
    received: string[];
    promised: string[];
}

const STATUS_VARIANTS: Record<DonationStatus, 'neutral' | 'success' | 'danger'> = {
    paid: 'success',
    payment_on_site: 'neutral',
    pending: 'neutral',
    failed: 'danger',
    expired: 'danger',
    refunded: 'danger',
};

const HEADER_CELL = 'px-4 py-3 font-normal whitespace-nowrap';

export default function Donations({ event, donations, received, promised }: DonationsPageProps) {
    return (
        <EventLayout title="Dons et cadeaux">
            <Head title={`Dons et cadeaux — ${event.title}`} />

            <p className="mb-8 max-w-2xl text-sm text-ink-soft">
                Les dons promis dans votre formulaire d'inscription et ceux ajoutés à l'achat d'un billet. Un reçu part par e-mail dès qu'un paiement est reçu.
            </p>

            {donations.length === 0 ? (
                <p className="text-sm text-ink-soft">Aucun don pour l'instant.</p>
            ) : (
                <>
                    <div className="mb-10 grid gap-4 sm:grid-cols-2">
                        <Total label="Reçus" amounts={received} />
                        <Total label="Promis, en attente de paiement" amounts={promised} />
                    </div>

                    <div className="overflow-x-auto rounded-card border border-line bg-bg">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="border-b border-line font-label text-[11px] tracking-[0.12em] text-ink-soft uppercase">
                                <tr>
                                    <th scope="col" className={HEADER_CELL}>
                                        Donateur
                                    </th>
                                    <th scope="col" className={HEADER_CELL}>
                                        Montant
                                    </th>
                                    <th scope="col" className={HEADER_CELL}>
                                        Cause
                                    </th>
                                    <th scope="col" className={HEADER_CELL}>
                                        Paiement
                                    </th>
                                    <th scope="col" className={HEADER_CELL}>
                                        Promis le
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {donations.map((donation) => (
                                    <tr key={donation.id} className="align-top">
                                        <td className="px-4 py-3">
                                            <span className="block font-medium text-ink">{donation.donor}</span>
                                            <span className="block text-xs text-ink-soft">{donation.email}</span>
                                            {donation.isAnonymous && <span className="block text-xs text-ink-soft">souhaite rester anonyme</span>}
                                            {donation.guestName && <span className="block text-xs text-ink-soft">inscrit : {donation.guestName}</span>}
                                        </td>
                                        <td className="px-4 py-3 font-medium whitespace-nowrap text-ink tabular-nums">{donation.amount}</td>
                                        <td className="px-4 py-3 text-ink-soft">{donation.cause ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={STATUS_VARIANTS[donation.status]}>{donation.statusLabel}</Badge>
                                            {donation.paidAt && <span className="mt-1 block text-xs text-ink-soft tabular-nums">{donation.paidAt}</span>}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-ink-soft tabular-nums">{donation.promisedAt}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </EventLayout>
    );
}

function Total({ label, amounts }: { label: string; amounts: string[] }) {
    return (
        <div className="rounded-card border border-line bg-bg p-5">
            <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">{label}</p>
            <p className="mt-2 text-2xl font-medium text-ink tabular-nums">{amounts.length > 0 ? amounts.join(' · ') : '—'}</p>
        </div>
    );
}
