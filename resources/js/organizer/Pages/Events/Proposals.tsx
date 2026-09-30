import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Textarea from '../../Components/Textarea';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

type ProposalStatus = 'pending' | 'accepted' | 'rejected';

interface Proposal {
    id: number;
    proposerName: string;
    proposerEmail: string;
    proposerRole: string | null;
    proposerCompany: string | null;
    proposerBio: string | null;
    title: string;
    summary: string;
    format: string;
    duration: number | null;
    status: ProposalStatus;
    reviewNote: string | null;
    decisionMessage: string | null;
    submittedAt: string;
    speakerId: number | null;
}

interface ProposalsPageProps {
    event: { id: number; title: string };
    call: { isOpen: boolean; intro: string | null; closesAt: string | null; acceptsProposals: boolean };
    proposals: Proposal[];
    publicUrl: string;
    speakersUrl: string;
}

const CARD = 'rounded-card border border-line bg-bg p-5';

const STATUS: Record<ProposalStatus, { label: string; variant: 'neutral' | 'success' | 'danger' }> = {
    pending: { label: 'À évaluer', variant: 'neutral' },
    accepted: { label: 'Retenu', variant: 'success' },
    rejected: { label: 'Refusé', variant: 'danger' },
};

/**
 * Appel à contributions (D6) : les réglages de l'appel, les sujets reçus et
 * leur évaluation. Retenir un sujet crée la fiche intervenant du proposant.
 */
export default function Proposals({ event, call, proposals, publicUrl, speakersUrl }: ProposalsPageProps) {
    const waiting = proposals.filter((proposal) => proposal.status === 'pending').length;

    return (
        <EventLayout title="Appel à contributions" eyebrow={event.title}>
            <Head title={`Appel à contributions — ${event.title}`} />

            <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                Ouvrez l'appel, partagez le lien, puis retenez les sujets qui vous intéressent. Un sujet retenu devient une
                fiche dans{' '}
                <Link href={speakersUrl} className="text-ink underline hover:no-underline">
                    Intervenants et programme
                </Link>
                .
            </p>

            <CallForm event={event} call={call} publicUrl={publicUrl} />

            <section className="mt-12">
                <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 className="font-serif text-xl italic">Sujets proposés</h2>
                    {proposals.length > 0 && (
                        <p className="text-sm text-ink-soft">
                            {proposals.length} au total{waiting > 0 ? `, ${waiting} à évaluer` : ''}
                        </p>
                    )}
                </div>

                {proposals.length === 0 ? (
                    <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                        <p className="mb-1 font-serif text-xl text-ink italic">Aucun sujet reçu</p>
                        <p className="text-sm text-ink-soft">Partagez le lien de l'appel pour recevoir vos premières propositions.</p>
                    </div>
                ) : (
                    <ul className="space-y-4">
                        {proposals.map((proposal) => (
                            <ProposalCard key={proposal.id} event={event} proposal={proposal} speakersUrl={speakersUrl} />
                        ))}
                    </ul>
                )}
            </section>
        </EventLayout>
    );
}

interface CallFormProps {
    event: { id: number };
    call: { isOpen: boolean; intro: string | null; closesAt: string | null; acceptsProposals: boolean };
    publicUrl: string;
}

function CallForm({ event, call, publicUrl }: CallFormProps) {
    const form = useForm({ is_open: call.isOpen, intro: call.intro ?? '', closes_at: call.closesAt ?? '' });
    const [copied, setCopied] = useState(false);

    async function copyLink() {
        await navigator.clipboard.writeText(publicUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        form.post(`/events/${event.id}/appel-a-contributions`, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className={CARD}>
            <h2 className="mb-4 font-serif text-xl italic">L'appel</h2>

            <label className="mb-5 flex cursor-pointer items-start gap-3">
                <Checkbox checked={form.data.is_open} onChange={(changeEvent) => form.setData('is_open', changeEvent.target.checked)} className="mt-1" />
                <span>
                    <span className="block text-sm text-ink">Ouvrir l'appel à contributions</span>
                    <span className="block text-xs text-ink-soft">Tant qu'il est fermé, la page publique n'existe pas.</span>
                </span>
            </label>

            <div className="mb-5">
                <InputLabel htmlFor="intro">Texte de l'appel</InputLabel>
                <p className="mb-2 text-xs text-ink-soft">Ce que vous cherchez, la durée des interventions, vos critères.</p>
                <Textarea id="intro" value={form.data.intro} onChange={(changeEvent) => form.setData('intro', changeEvent.target.value)} rows={4} maxLength={5000} />
                <InputError message={form.errors.intro} />
            </div>

            <div className="mb-5 max-w-xs">
                <InputLabel htmlFor="closes_at">Date limite (optionnel)</InputLabel>
                <TextInput
                    id="closes_at"
                    type="datetime-local"
                    value={form.data.closes_at}
                    onChange={(changeEvent) => form.setData('closes_at', changeEvent.target.value)}
                />
                <InputError message={form.errors.closes_at} />
                <p className="mt-1 text-xs text-ink-soft">Dans le fuseau de l'événement. Passée cette date, la page n'accepte plus rien.</p>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-4 border-t border-line pt-4">
                <div className="text-sm">
                    {call.isOpen ? (
                        <button type="button" onClick={() => void copyLink()} className="text-ink underline hover:no-underline">
                            {copied ? 'Lien copié' : "Copier le lien de l'appel"}
                        </button>
                    ) : (
                        <span className="text-ink-soft">Le lien de l'appel s'affiche une fois l'appel ouvert.</span>
                    )}
                </div>
                <Button type="submit" disabled={form.processing} className="sm:w-auto">
                    Enregistrer
                </Button>
            </div>
        </form>
    );
}

interface ProposalCardProps {
    event: { id: number };
    proposal: Proposal;
    speakersUrl: string;
}

function ProposalCard({ event, proposal, speakersUrl }: ProposalCardProps) {
    const [deciding, setDeciding] = useState(false);

    return (
        <li className={CARD}>
            <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-3">
                        <p className="text-lg text-ink">{proposal.title}</p>
                        <Badge variant={STATUS[proposal.status].variant}>{STATUS[proposal.status].label}</Badge>
                    </div>
                    <p className="text-sm text-ink-soft">
                        {proposal.format}
                        {proposal.duration ? ` · ${proposal.duration} min` : ''} · reçu le {proposal.submittedAt}
                    </p>
                </div>
                <button type="button" onClick={() => setDeciding((value) => !value)} className="text-sm text-ink underline hover:no-underline">
                    {deciding ? 'Fermer' : proposal.status === 'pending' ? 'Évaluer' : 'Revoir la décision'}
                </button>
            </div>

            <p className="mb-4 text-sm whitespace-pre-line text-ink">{proposal.summary}</p>

            <div className="border-t border-line pt-3 text-sm text-ink-soft">
                <p className="text-ink">
                    {proposal.proposerName}
                    {proposal.proposerRole ? ` · ${proposal.proposerRole}` : ''}
                    {proposal.proposerCompany ? ` · ${proposal.proposerCompany}` : ''}
                </p>
                <p>
                    <a href={`mailto:${proposal.proposerEmail}`} className="underline hover:no-underline">
                        {proposal.proposerEmail}
                    </a>
                </p>
                {proposal.proposerBio && <p className="mt-2 whitespace-pre-line">{proposal.proposerBio}</p>}
            </div>

            {proposal.reviewNote && (
                <p className="mt-3 rounded-control bg-bg-alt px-3 py-2 text-sm text-ink-soft">
                    <span className="font-label text-[11px] tracking-[0.08em] uppercase">Note interne</span>
                    <br />
                    {proposal.reviewNote}
                </p>
            )}

            {proposal.speakerId !== null && (
                <p className="mt-3 text-sm text-ink-soft">
                    Fiche intervenant créée —{' '}
                    <Link href={speakersUrl} className="text-ink underline hover:no-underline">
                        la compléter
                    </Link>
                    .
                </p>
            )}

            {deciding && <DecisionForm event={event} proposal={proposal} onDone={() => setDeciding(false)} />}
        </li>
    );
}

interface DecisionFormProps {
    event: { id: number };
    proposal: Proposal;
    onDone: () => void;
}

function DecisionForm({ event, proposal, onDone }: DecisionFormProps) {
    const form = useForm({
        status: proposal.status === 'pending' ? 'accepted' : proposal.status,
        review_note: proposal.reviewNote ?? '',
        decision_message: proposal.decisionMessage ?? '',
    });

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        form.post(`/events/${event.id}/appel-a-contributions/${proposal.id}/decision`, { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="mt-5 border-t border-line pt-5">
            <fieldset className="mb-5">
                <legend className="mb-2 block font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Votre décision</legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    <label className="flex cursor-pointer items-center gap-3 rounded-control border border-line px-4 py-2 text-sm text-ink">
                        <input type="radio" name={`status-${proposal.id}`} value="accepted" checked={form.data.status === 'accepted'} onChange={() => form.setData('status', 'accepted')} />
                        Retenir ce sujet
                    </label>
                    <label className="flex cursor-pointer items-center gap-3 rounded-control border border-line px-4 py-2 text-sm text-ink">
                        <input type="radio" name={`status-${proposal.id}`} value="rejected" checked={form.data.status === 'rejected'} onChange={() => form.setData('status', 'rejected')} />
                        Ne pas le retenir
                    </label>
                </div>
                <InputError message={form.errors.status} />
            </fieldset>

            <div className="mb-5">
                <InputLabel htmlFor={`review_note-${proposal.id}`}>Note interne (optionnel)</InputLabel>
                <p className="mb-2 text-xs text-ink-soft">Pour votre équipe seulement : elle n'est jamais envoyée au proposant.</p>
                <Textarea
                    id={`review_note-${proposal.id}`}
                    value={form.data.review_note}
                    onChange={(changeEvent) => form.setData('review_note', changeEvent.target.value)}
                    rows={3}
                    maxLength={2000}
                />
                <InputError message={form.errors.review_note} />
            </div>

            <div className="mb-5">
                <InputLabel htmlFor={`decision_message-${proposal.id}`}>Message au proposant (optionnel)</InputLabel>
                <p className="mb-2 text-xs text-ink-soft">Il part avec l'e-mail qui annonce votre décision.</p>
                <Textarea
                    id={`decision_message-${proposal.id}`}
                    value={form.data.decision_message}
                    onChange={(changeEvent) => form.setData('decision_message', changeEvent.target.value)}
                    rows={3}
                    maxLength={2000}
                />
                <InputError message={form.errors.decision_message} />
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="secondary" onClick={onDone} className="sm:w-auto">
                    Annuler
                </Button>
                <Button type="submit" disabled={form.processing} className="sm:w-auto">
                    Enregistrer et prévenir
                </Button>
            </div>
        </form>
    );
}
