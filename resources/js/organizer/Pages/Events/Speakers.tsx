import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Textarea from '../../Components/Textarea';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

type SlotStatus = 'pending' | 'confirmed' | 'declined';

interface Support {
    name: string;
    size: string;
    status: string;
    isClean: boolean;
}

interface Speaker {
    id: number;
    name: string;
    role: string | null;
    company: string | null;
    email: string | null;
    bio: string | null;
    photoUrl: string | null;
    websiteUrl: string | null;
    linkedinUrl: string | null;
    sessionIds: number[];
    sessions: string[];
    portalUrl: string;
    status: SlotStatus;
    responseNote: string | null;
    support: Support | null;
}

interface Session {
    id: number;
    title: string;
    room: string | null;
    day: string;
    time: string;
    speakers: string[];
}

interface SpeakersPageProps {
    event: { id: number; title: string; speakerBriefing: string | null };
    speakers: Speaker[];
    sessions: Session[];
    subEventsUrl: string;
}

const CARD = 'rounded-card border border-line bg-bg p-5';

const STATUS: Record<SlotStatus, { label: string; variant: 'neutral' | 'success' | 'danger' }> = {
    pending: { label: 'Sans réponse', variant: 'neutral' },
    confirmed: { label: 'Créneau confirmé', variant: 'success' },
    declined: { label: 'Ne viendra pas', variant: 'danger' },
};

/**
 * Intervenants de l'événement (D6) : leur fiche pour la page publique, les
 * sessions où ils parlent, et leur portail — lien personnel, réponse au
 * créneau et support déposé. Les sessions elles-mêmes se règlent dans
 * « Événements secondaires ».
 */
export default function Speakers({ event, speakers, sessions, subEventsUrl }: SpeakersPageProps) {
    const [editing, setEditing] = useState<Speaker | null>(null);
    const [creating, setCreating] = useState(false);

    function close() {
        setEditing(null);
        setCreating(false);
    }

    return (
        <EventLayout title="Intervenants" eyebrow={event.title}>
            <Head title={`Intervenants — ${event.title}`} />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Leur fiche s'affiche sur la page de l'événement, avec le programme. Les horaires et les salles se règlent dans{' '}
                    <Link href={subEventsUrl} className="text-ink underline hover:no-underline">
                        Événements secondaires
                    </Link>
                    .
                </p>
                {!creating && editing === null && (
                    <Button type="button" onClick={() => setCreating(true)} className="w-auto px-6 py-2">
                        + Nouvel intervenant
                    </Button>
                )}
            </div>

            {(creating || editing !== null) && (
                <SpeakerForm key={editing?.id ?? 'new'} event={event} sessions={sessions} speaker={editing} onDone={close} />
            )}

            {speakers.length === 0 ? (
                <div className="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Aucun intervenant</p>
                    <p className="text-sm text-ink-soft">Ajoutez celles et ceux qui prendront la parole.</p>
                </div>
            ) : (
                <ul className="space-y-4">
                    {speakers.map((speaker) => (
                        <li key={speaker.id} className={CARD}>
                            <div className="flex flex-wrap items-start gap-4">
                                {speaker.photoUrl ? (
                                    <img src={speaker.photoUrl} alt="" className="h-16 w-16 shrink-0 rounded-full object-cover" />
                                ) : (
                                    <span aria-hidden="true" className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-bg-alt font-serif text-xl text-ink-soft">
                                        {speaker.name.slice(0, 1)}
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <p className="text-lg text-ink">{speaker.name}</p>
                                        <Badge variant={STATUS[speaker.status].variant}>{STATUS[speaker.status].label}</Badge>
                                    </div>
                                    <p className="text-sm text-ink-soft">{[speaker.role, speaker.company].filter(Boolean).join(' · ') || '—'}</p>
                                    {speaker.sessions.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {speaker.sessions.map((session) => (
                                                <Badge key={session}>{session}</Badge>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div className="flex gap-4 text-sm">
                                    <button type="button" onClick={() => setEditing(speaker)} className="text-ink underline hover:no-underline">
                                        Modifier
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (window.confirm(`Retirer ${speaker.name} du programme ?`)) {
                                                router.delete(`/events/${event.id}/intervenants/${speaker.id}`, { preserveScroll: true });
                                            }
                                        }}
                                        className="text-danger underline hover:no-underline"
                                    >
                                        Retirer
                                    </button>
                                </div>
                            </div>

                            <SpeakerPortal event={event} speaker={speaker} />
                        </li>
                    ))}
                </ul>
            )}

            <BriefingForm event={event} />

            <section className="mt-12">
                <h2 className="mb-1 font-serif text-xl italic">Programme</h2>
                <p className="mb-4 text-sm text-ink-soft">Tel qu'il s'affichera pour vos invités, session par session.</p>

                {sessions.length === 0 ? (
                    <p className="rounded-card border border-dashed border-line bg-bg px-6 py-8 text-center text-sm text-ink-soft">
                        Aucune session pour l'instant.{' '}
                        <Link href={subEventsUrl} className="text-ink underline hover:no-underline">
                            En créer une
                        </Link>
                        .
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {sessions.map((session) => (
                            <li key={session.id} className={`${CARD} flex flex-wrap items-baseline gap-x-4 gap-y-1`}>
                                <span className="font-medium text-ink">{session.time}</span>
                                <span className="text-ink">{session.title}</span>
                                {session.room && <span className="text-sm text-ink-soft">{session.room}</span>}
                                {session.speakers.length > 0 && <span className="text-sm text-ink-soft">avec {session.speakers.join(', ')}</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </EventLayout>
    );
}

interface SpeakerPortalProps {
    event: { id: number };
    speaker: Speaker;
}

/**
 * Portail de l'intervenant vu par l'organisateur : le lien à lui envoyer,
 * son message et le support qu'il a déposé.
 */
function SpeakerPortal({ event, speaker }: SpeakerPortalProps) {
    const [copied, setCopied] = useState(false);

    async function copyLink() {
        await navigator.clipboard.writeText(speaker.portalUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <div className="mt-5 border-t border-line pt-4">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Espace intervenant</span>
                <button type="button" onClick={() => void copyLink()} className="text-ink underline hover:no-underline">
                    {copied ? 'Lien copié' : 'Copier le lien'}
                </button>
                <button
                    type="button"
                    onClick={() => router.post(`/events/${event.id}/intervenants/${speaker.id}/lien`, {}, { preserveScroll: true })}
                    className="text-ink underline hover:no-underline"
                    disabled={speaker.email === null}
                    title={speaker.email === null ? "Ajoutez son adresse e-mail pour lui envoyer son lien" : undefined}
                >
                    Envoyer par e-mail
                </button>
                <button
                    type="button"
                    onClick={() => {
                        if (window.confirm("Renouveler ce lien ? L'ancien cessera aussitôt de fonctionner.")) {
                            router.post(`/events/${event.id}/intervenants/${speaker.id}/lien/renouveler`, {}, { preserveScroll: true });
                        }
                    }}
                    className="text-ink-soft underline hover:no-underline"
                >
                    Renouveler le lien
                </button>
            </div>

            {speaker.responseNote && <p className="mt-3 text-sm text-ink-soft italic">« {speaker.responseNote} »</p>}

            <p className="mt-3 text-sm text-ink-soft">
                {speaker.support === null ? (
                    'Aucun support déposé.'
                ) : (
                    <>
                        Support :{' '}
                        {speaker.support.isClean ? (
                            <a href={`/events/${event.id}/intervenants/${speaker.id}/support`} className="text-ink underline hover:no-underline">
                                {speaker.support.name}
                            </a>
                        ) : (
                            <span className="text-ink">{speaker.support.name}</span>
                        )}{' '}
                        <span className="text-xs">
                            ({speaker.support.size} · {speaker.support.status})
                        </span>
                    </>
                )}
            </p>
        </div>
    );
}

/**
 * Informations pratiques communes : elles s'affichent sur le portail de
 * chaque intervenant.
 */
function BriefingForm({ event }: { event: { id: number; speakerBriefing: string | null } }) {
    const form = useForm({ speaker_briefing: event.speakerBriefing ?? '' });

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        form.post(`/events/${event.id}/intervenants/informations`, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className={`${CARD} mt-12`}>
            <h2 className="mb-1 font-serif text-xl italic">Informations pratiques</h2>
            <p className="mb-4 text-sm text-ink-soft">
                Heure d'arrivée, accueil, matériel disponible, personne à contacter : chaque intervenant les lit sur son espace.
            </p>
            <Textarea
                id="speaker_briefing"
                value={form.data.speaker_briefing}
                onChange={(changeEvent) => form.setData('speaker_briefing', changeEvent.target.value)}
                rows={5}
                maxLength={5000}
            />
            <InputError message={form.errors.speaker_briefing} />
            <div className="mt-4 flex justify-end">
                <Button type="submit" disabled={form.processing} className="sm:w-auto">
                    Enregistrer
                </Button>
            </div>
        </form>
    );
}

interface SpeakerFormProps {
    event: { id: number };
    sessions: Session[];
    speaker: Speaker | null;
    onDone: () => void;
}

function SpeakerForm({ event, sessions, speaker, onDone }: SpeakerFormProps) {
    const form = useForm({
        name: speaker?.name ?? '',
        role: speaker?.role ?? '',
        company: speaker?.company ?? '',
        email: speaker?.email ?? '',
        bio: speaker?.bio ?? '',
        website_url: speaker?.websiteUrl ?? '',
        linkedin_url: speaker?.linkedinUrl ?? '',
        session_ids: speaker?.sessionIds ?? [],
    });
    const [photoUrl, setPhotoUrl] = useState(speaker?.photoUrl ?? null);
    const [uploading, setUploading] = useState(false);

    function toggleSession(id: number) {
        form.setData('session_ids', form.data.session_ids.includes(id) ? form.data.session_ids.filter((value) => value !== id) : [...form.data.session_ids, id]);
    }

    async function uploadPhoto(file: File) {
        if (speaker === null) {
            return;
        }

        const formData = new FormData();
        formData.append('photo', file);
        setUploading(true);

        try {
            const response = await window.axios.post<{ photo_url: string }>(`/events/${event.id}/intervenants/${speaker.id}/photo`, formData);
            setPhotoUrl(response.data.photo_url);
        } finally {
            setUploading(false);
        }
    }

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();

        if (speaker === null) {
            form.post(`/events/${event.id}/intervenants`, { preserveScroll: true, onSuccess: onDone });
        } else {
            form.patch(`/events/${event.id}/intervenants/${speaker.id}`, { preserveScroll: true, onSuccess: onDone });
        }
    }

    return (
        <form onSubmit={submit} className={`${CARD} mb-6`}>
            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="name">Nom</InputLabel>
                    <TextInput id="name" value={form.data.name} onChange={(changeEvent) => form.setData('name', changeEvent.target.value)} required />
                    <InputError message={form.errors.name} />
                </div>
                <div>
                    <InputLabel htmlFor="role">Fonction (optionnel)</InputLabel>
                    <TextInput id="role" value={form.data.role} onChange={(changeEvent) => form.setData('role', changeEvent.target.value)} placeholder="Directrice des opérations" />
                </div>
                <div>
                    <InputLabel htmlFor="company">Organisation (optionnel)</InputLabel>
                    <TextInput id="company" value={form.data.company} onChange={(changeEvent) => form.setData('company', changeEvent.target.value)} />
                </div>
                <div>
                    <InputLabel htmlFor="email">Adresse e-mail (optionnel)</InputLabel>
                    <TextInput id="email" type="email" value={form.data.email} onChange={(changeEvent) => form.setData('email', changeEvent.target.value)} placeholder="awa@example.com" />
                    <InputError message={form.errors.email} />
                    <p className="mt-1 text-xs text-ink-soft">Elle sert à lui envoyer le lien de son espace intervenant.</p>
                </div>
                <div>
                    <InputLabel htmlFor="website_url">Site web (optionnel)</InputLabel>
                    <TextInput id="website_url" value={form.data.website_url} onChange={(changeEvent) => form.setData('website_url', changeEvent.target.value)} placeholder="https://" />
                    <InputError message={form.errors.website_url} />
                </div>
                <div>
                    <InputLabel htmlFor="linkedin_url">Profil LinkedIn (optionnel)</InputLabel>
                    <TextInput id="linkedin_url" value={form.data.linkedin_url} onChange={(changeEvent) => form.setData('linkedin_url', changeEvent.target.value)} placeholder="https://" />
                    <InputError message={form.errors.linkedin_url} />
                </div>
                <div className="sm:col-span-2">
                    <InputLabel htmlFor="bio">Biographie (optionnel)</InputLabel>
                    <Textarea id="bio" value={form.data.bio} onChange={(changeEvent) => form.setData('bio', changeEvent.target.value)} rows={4} maxLength={2000} />
                </div>
            </div>

            {speaker !== null && (
                <div className="mt-5 flex flex-wrap items-center gap-4">
                    {photoUrl && <img src={photoUrl} alt="" className="h-16 w-16 rounded-full object-cover" />}
                    <div>
                        <InputLabel htmlFor="photo">Photo (optionnel)</InputLabel>
                        <input
                            id="photo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            disabled={uploading}
                            onChange={(changeEvent) => {
                                const file = changeEvent.target.files?.[0];

                                if (file) {
                                    void uploadPhoto(file);
                                }
                            }}
                            className="block text-sm text-ink-soft file:mr-3 file:min-h-9 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-4 file:py-1.5 file:text-sm file:text-ink"
                        />
                    </div>
                </div>
            )}

            {sessions.length > 0 && (
                <fieldset className="mt-5">
                    <legend className="mb-2 block font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Ses sessions</legend>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {sessions.map((session) => (
                            <label key={session.id} className="flex cursor-pointer items-start gap-3 rounded-control border border-line px-4 py-2">
                                <Checkbox checked={form.data.session_ids.includes(session.id)} onChange={() => toggleSession(session.id)} className="mt-1" />
                                <span>
                                    <span className="block text-sm text-ink">{session.title}</span>
                                    <span className="block text-xs text-ink-soft">
                                        {session.time}
                                        {session.room ? ` · ${session.room}` : ''}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}

            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="secondary" onClick={onDone} className="sm:w-auto">
                    Annuler
                </Button>
                <Button type="submit" disabled={form.processing} className="sm:w-auto">
                    {speaker === null ? "Ajouter l'intervenant" : 'Enregistrer'}
                </Button>
            </div>

            {speaker === null && <p className="mt-3 text-xs text-ink-soft">La photo s'ajoute après la création de la fiche.</p>}
        </form>
    );
}
