import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Modal from '../../Components/Modal';
import Textarea from '../../Components/Textarea';
import EventLayout from '../../Layouts/EventLayout';

interface PendingRegistration {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    people: number;
    companions: string[];
    registeredAt: string;
    answers: Record<string, string>;
    sessions: string[];
}

interface ApprovalsPageProps {
    event: { id: number; title: string; requiresApproval: boolean };
    registrations: PendingRegistration[];
    canDecide: boolean;
    settingsUrl: string;
}

function people(count: number): string {
    return count > 1 ? `${count} personnes` : '1 personne';
}

/**
 * Événement en validation manuelle : chaque demande attend d'être acceptée
 * ou refusée. La place est retenue en attendant ; refuser la libère.
 */
export default function Approvals({ event, registrations, canDecide, settingsUrl }: ApprovalsPageProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [rejecting, setRejecting] = useState<PendingRegistration | null>(null);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    function approve(registration: PendingRegistration) {
        router.post(`/registrations/${registration.id}/approve`, {}, { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
    }

    function reject() {
        if (rejecting === null) {
            return;
        }

        router.post(
            `/registrations/${rejecting.id}/reject`,
            { reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setRejecting(null);
                    setReason('');
                },
            },
        );
    }

    return (
        <EventLayout title="Inscriptions à valider" eyebrow={event.title}>
            <Head title={`Inscriptions à valider — ${event.title}`} />

            <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                {event.requiresApproval ? (
                    <>
                        Chaque réponse attend votre accord. La place de l'invité est retenue en attendant ; refuser la libère et fait avancer la liste
                        d'attente. <Link href={settingsUrl} className="text-ink underline hover:no-underline">Changer ce réglage</Link>.
                    </>
                ) : (
                    <>
                        La validation manuelle est désactivée, mais ces demandes l'attendaient encore.{' '}
                        <Link href={settingsUrl} className="text-ink underline hover:no-underline">Voir le réglage</Link>.
                    </>
                )}
            </p>

            {errors.decision && (
                <div role="alert" className="mb-5 rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">
                    {errors.decision}
                </div>
            )}

            {registrations.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucune demande en attente</p>
                    <p className="text-sm text-ink-soft">Les nouvelles réponses apparaîtront ici, en attendant votre accord.</p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {registrations.map((registration) => (
                        <li key={registration.id} className="rounded-card border border-line bg-bg p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="text-lg text-ink">{registration.name}</h2>
                                    <p className="text-sm text-ink-soft">
                                        {registration.email || registration.phone || 'Sans coordonnées'} · {people(registration.people)} · {registration.registeredAt}
                                    </p>
                                </div>
                                {canDecide && (
                                    <div className="flex flex-wrap gap-2">
                                        <Button type="button" onClick={() => approve(registration)} disabled={processing} className="w-auto px-6 py-2">
                                            Accepter
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => setRejecting(registration)}
                                            disabled={processing}
                                            className="w-auto px-6 py-2"
                                        >
                                            Refuser
                                        </Button>
                                    </div>
                                )}
                            </div>

                            {registration.companions.length > 0 && (
                                <p className="mt-3 text-sm text-ink-soft">Avec : {registration.companions.join(', ')}</p>
                            )}

                            {registration.sessions.length > 0 && (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {registration.sessions.map((session) => (
                                        <Badge key={session}>{session}</Badge>
                                    ))}
                                </div>
                            )}

                            {Object.keys(registration.answers).length > 0 && (
                                <dl className="mt-4 space-y-1 border-t border-line pt-4 text-sm">
                                    {Object.entries(registration.answers).map(([label, value]) => (
                                        <div key={label} className="flex flex-wrap gap-x-2">
                                            <dt className="text-ink-soft">{label} :</dt>
                                            <dd className="text-ink">{value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Modal open={rejecting !== null} onClose={() => setRejecting(null)} title="Refuser cette demande" showCloseButton>
                <div className="space-y-4">
                    <p className="text-sm text-ink-soft">
                        {rejecting?.name} sera prévenu par e-mail et sa place sera libérée. Le motif est facultatif : il figurera dans le message.
                    </p>
                    <Textarea value={reason} onChange={(changeEvent) => setReason(changeEvent.target.value)} maxLength={500} rows={3} />
                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button type="button" variant="secondary" onClick={() => setRejecting(null)} className="sm:w-auto">
                            Annuler
                        </Button>
                        <Button type="button" onClick={reject} disabled={processing} className="sm:w-auto">
                            Refuser la demande
                        </Button>
                    </div>
                </div>
            </Modal>
        </EventLayout>
    );
}
