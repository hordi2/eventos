import { Head, router } from '@inertiajs/react';
import { type ChangeEvent, useEffect, useRef, useState } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import Table from '../../Components/Table';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface Guest {
    guest_type: 'attendee' | 'ticket';
    id: number;
    name: string;
}

interface Batch {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    guest_count: number | null;
    created_at: string;
}

interface Props {
    event: { id: number; title: string };
    guests: Guest[];
    hasLogo: boolean;
    batches: Batch[];
}

const STATUS_LABELS: Record<Batch['status'], string> = {
    pending: 'En attente',
    processing: 'En cours',
    completed: 'Prête',
    failed: 'Échouée',
};

export default function Show({ event, guests, hasLogo, batches: initialBatches }: Props) {
    const [batches, setBatches] = useState<Batch[]>(initialBatches);
    const [starting, setStarting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const pollingRef = useRef<number | null>(null);

    const hasPendingBatch = batches.some((batch) => batch.status === 'pending' || batch.status === 'processing');

    useEffect(() => {
        if (!hasPendingBatch) {
            return;
        }

        pollingRef.current = window.setInterval(async () => {
            const pending = batches.filter((batch) => batch.status === 'pending' || batch.status === 'processing');

            await Promise.all(
                pending.map(async (batch) => {
                    const response = await window.axios.get<Batch>(`/events/${event.id}/badges/batches/${batch.id}`);
                    setBatches((current) => current.map((existing) => (existing.id === batch.id ? { ...existing, ...response.data } : existing)));
                }),
            );
        }, 3000);

        return () => {
            if (pollingRef.current !== null) {
                window.clearInterval(pollingRef.current);
            }
        };
    }, [hasPendingBatch, batches, event.id]);

    function handleLogoChange(changeEvent: ChangeEvent<HTMLInputElement>) {
        const file = changeEvent.target.files?.[0];

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('logo', file);

        router.post(`/events/${event.id}/badges/logo`, formData, { preserveScroll: true });
    }

    async function startBatch() {
        setStarting(true);

        try {
            const response = await window.axios.post<Batch>(`/events/${event.id}/badges/batches`);
            setBatches((current) => [response.data, ...current]);
        } finally {
            setStarting(false);
        }
    }

    return (
        <OrganizerLayout title="Badges" eyebrow={event.title}>
            <Head title="Badges" />

            <div className="mb-8">
                <h1 className="text-2xl">Badges</h1>
                <p className="text-ink-soft">{guests.length} invités</p>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Logo de l'événement</h2>
                <InputLabel htmlFor="logo">{hasLogo ? 'Remplacer le logo' : 'Téléverser un logo'}</InputLabel>
                <input
                    id="logo"
                    ref={fileInputRef}
                    type="file"
                    accept="image/png,image/jpeg"
                    onChange={handleLogoChange}
                    className="text-sm text-ink-soft"
                />
                {hasLogo && <p className="mt-2 text-sm text-success">Un logo est déjà configuré pour cet événement.</p>}
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-serif text-lg italic">Planche de badges (Avery)</h2>
                    <Button className="w-auto" onClick={() => void startBatch()} disabled={starting || guests.length === 0}>
                        {starting ? 'Lancement…' : 'Générer pour tous les invités'}
                    </Button>
                </div>

                {batches.length === 0 && <p className="text-sm text-ink-soft">Aucune génération pour l'instant.</p>}

                {batches.length > 0 && (
                    <ul className="space-y-2 text-sm">
                        {batches.map((batch) => (
                            <li key={batch.id} className="flex items-center justify-between border-b border-line py-2 last:border-0">
                                <span>
                                    {new Date(batch.created_at).toLocaleString('fr-FR')} — {STATUS_LABELS[batch.status]}
                                    {batch.guest_count !== null ? ` (${batch.guest_count} badges)` : ''}
                                </span>
                                {batch.status === 'completed' && (
                                    <a
                                        href={`/events/${event.id}/badges/batches/${batch.id}/download`}
                                        className="text-accent underline underline-offset-2"
                                    >
                                        Télécharger le PDF
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Table
                columns={[
                    { key: 'name', header: 'Invité', render: (guest: Guest) => guest.name },
                    {
                        key: 'action',
                        header: '',
                        render: (guest: Guest) => (
                            <a
                                href={`/events/${event.id}/badges/${guest.guest_type}/${guest.id}`}
                                className="text-accent underline underline-offset-2"
                            >
                                Badge individuel (PDF)
                            </a>
                        ),
                    },
                ]}
                rows={guests}
                rowKey={(guest) => `${guest.guest_type}-${guest.id}`}
                emptyMessage="Aucun invité pour l'instant."
            />
        </OrganizerLayout>
    );
}
