import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import Textarea from '../../Components/Textarea';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface EventTypeOption {
    value: string;
    label: string;
}

interface VenueOption {
    id: number;
    name: string;
    address: string;
}

interface EventDraft {
    id: number;
    title: string;
    subtitle: string | null;
    description: string | null;
    type: string;
    startAt: string;
    endAt: string;
    timezone: string;
    venueId: number | null;
    venueName: string | null;
    venueAddress: string | null;
}

interface CreateEventPageProps {
    event: EventDraft | null;
    eventTypes: EventTypeOption[];
    timezones: Record<string, string>;
    venues: VenueOption[];
}

function toDatetimeLocalValue(isoString: string, timeZone: string): string {
    const date = new Date(isoString);
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date);

    const part = (type: string) => parts.find((p) => p.type === type)?.value ?? '00';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

function formatInEventTimezone(isoString: string, timeZone: string): string {
    return new Date(isoString).toLocaleString('fr-FR', { timeZone, dateStyle: 'long', timeStyle: 'short' });
}

function addHoursToLocalValue(value: string, hours: number): string {
    if (!value) {
        return value;
    }

    const [datePart, timePart] = value.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    const shifted = new Date(year, month - 1, day, hour + hours, minute);

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${shifted.getFullYear()}-${pad(shifted.getMonth() + 1)}-${pad(shifted.getDate())}T${pad(shifted.getHours())}:${pad(shifted.getMinutes())}`;
}

export default function CreateEvent({ event, eventTypes, timezones, venues }: CreateEventPageProps) {
    const [step, setStep] = useState<1 | 2 | 3>(event ? 3 : 1);
    const [endTouched, setEndTouched] = useState(Boolean(event));
    const [venueMode, setVenueMode] = useState<'none' | 'existing' | 'new'>(event?.venueId ? 'existing' : 'none');
    const [showDuplicate, setShowDuplicate] = useState(false);

    const duplicateForm = useForm({ new_start_at: '' });

    function submitDuplicate(formEvent: FormEvent) {
        formEvent.preventDefault();
        duplicateForm.post(`/events/${event!.id}/duplicate`);
    }

    const { data, setData, post, patch, processing, errors } = useForm({
        type: event?.type ?? '',
        title: event?.title ?? '',
        subtitle: event?.subtitle ?? '',
        description: event?.description ?? '',
        start_at: event ? toDatetimeLocalValue(event.startAt, event.timezone) : '',
        end_at: event ? toDatetimeLocalValue(event.endAt, event.timezone) : '',
        timezone: event?.timezone ?? 'Africa/Kinshasa',
        venue_id: event?.venueId ? String(event.venueId) : '',
        venue_name: '',
        venue_address: '',
        venue_access_instructions: '',
        venue_parking_info: '',
    });

    function selectVenueMode(mode: 'none' | 'existing' | 'new') {
        setVenueMode(mode);
        setData('venue_id', '');
        setData('venue_name', '');
        setData('venue_address', '');
        setData('venue_access_instructions', '');
        setData('venue_parking_info', '');
    }

    function handleStartChange(value: string) {
        setData('start_at', value);

        if (!endTouched) {
            setData('end_at', addHoursToLocalValue(value, 3));
        }
    }

    function submitEssentials(formEvent: FormEvent) {
        formEvent.preventDefault();

        const options = { onSuccess: () => setStep(3 as const) };

        if (event) {
            patch(`/events/${event.id}`, options);
        } else {
            post('/events', options);
        }
    }

    return (
        <OrganizerLayout title="Créer un événement" eyebrow="Nouvel événement">
            <Head title="Créer un événement" />

            <div className="mb-10 flex items-center gap-3">
                <Badge variant={step === 1 ? 'success' : 'neutral'}>1. Type</Badge>
                <Badge variant={step === 2 ? 'success' : 'neutral'}>2. Informations</Badge>
                <Badge variant={step === 3 ? 'success' : 'neutral'}>3. Confirmation</Badge>
            </div>

            {step === 1 && (
                <div className="space-y-8">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {eventTypes.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => setData('type', option.value)}
                                className={`rounded-card border px-4 py-3 text-left font-sans text-sm transition-colors duration-300 ${
                                    data.type === option.value
                                        ? 'border-accent text-ink'
                                        : 'border-line text-ink-soft hover:border-ink'
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>

                    <Button type="button" disabled={!data.type} onClick={() => setStep(2)}>
                        Suivant
                    </Button>
                </div>
            )}

            {step === 2 && (
                <form onSubmit={submitEssentials} className="space-y-6">
                    <div>
                        <InputLabel htmlFor="title">Titre</InputLabel>
                        <TextInput
                            id="title"
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            autoFocus
                            required
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div>
                        <InputLabel htmlFor="subtitle">Sous-titre (optionnel)</InputLabel>
                        <TextInput
                            id="subtitle"
                            type="text"
                            value={data.subtitle}
                            onChange={(e) => setData('subtitle', e.target.value)}
                        />
                        <InputError message={errors.subtitle} />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="start_at">Début</InputLabel>
                            <TextInput
                                id="start_at"
                                type="datetime-local"
                                value={data.start_at}
                                onChange={(e) => handleStartChange(e.target.value)}
                                required
                            />
                            <InputError message={errors.start_at} />
                        </div>

                        <div>
                            <InputLabel htmlFor="end_at">Fin (optionnel)</InputLabel>
                            <TextInput
                                id="end_at"
                                type="datetime-local"
                                value={data.end_at}
                                onChange={(e) => {
                                    setEndTouched(true);
                                    setData('end_at', e.target.value);
                                }}
                            />
                            <InputError message={errors.end_at} />
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="timezone">Fuseau horaire</InputLabel>
                        <Select id="timezone" value={data.timezone} onChange={(e) => setData('timezone', e.target.value)} required>
                            {Object.entries(timezones).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                        <InputError message={errors.timezone} />
                    </div>

                    <div>
                        <InputLabel>Lieu (optionnel)</InputLabel>
                        <div className="flex flex-wrap gap-2">
                            <VenueModeButton active={venueMode === 'none'} onClick={() => selectVenueMode('none')}>
                                Aucun
                            </VenueModeButton>
                            {venues.length > 0 && (
                                <VenueModeButton active={venueMode === 'existing'} onClick={() => selectVenueMode('existing')}>
                                    Lieu déjà saisi
                                </VenueModeButton>
                            )}
                            <VenueModeButton active={venueMode === 'new'} onClick={() => selectVenueMode('new')}>
                                Nouveau lieu
                            </VenueModeButton>
                        </div>

                        {venueMode === 'existing' && (
                            <div className="mt-3">
                                <Select
                                    id="venue_id"
                                    value={data.venue_id}
                                    onChange={(e) => setData('venue_id', e.target.value)}
                                >
                                    <option value="">Choisir un lieu…</option>
                                    {venues.map((venue) => (
                                        <option key={venue.id} value={venue.id}>
                                            {venue.name} — {venue.address}
                                        </option>
                                    ))}
                                </Select>
                                <InputError message={errors.venue_id} />
                            </div>
                        )}

                        {venueMode === 'new' && (
                            <div className="mt-3 space-y-4">
                                <div>
                                    <InputLabel htmlFor="venue_name">Nom du lieu</InputLabel>
                                    <TextInput
                                        id="venue_name"
                                        type="text"
                                        value={data.venue_name}
                                        onChange={(e) => setData('venue_name', e.target.value)}
                                    />
                                    <InputError message={errors.venue_name} />
                                </div>

                                <div>
                                    <InputLabel htmlFor="venue_address">Adresse</InputLabel>
                                    <Textarea
                                        id="venue_address"
                                        value={data.venue_address}
                                        onChange={(e) => setData('venue_address', e.target.value)}
                                    />
                                    <InputError message={errors.venue_address} />
                                </div>

                                <div>
                                    <InputLabel htmlFor="venue_access_instructions">
                                        Instructions d'accès (optionnel)
                                    </InputLabel>
                                    <Textarea
                                        id="venue_access_instructions"
                                        value={data.venue_access_instructions}
                                        onChange={(e) => setData('venue_access_instructions', e.target.value)}
                                    />
                                </div>

                                <div>
                                    <InputLabel htmlFor="venue_parking_info">Parking (optionnel)</InputLabel>
                                    <Textarea
                                        id="venue_parking_info"
                                        value={data.venue_parking_info}
                                        onChange={(e) => setData('venue_parking_info', e.target.value)}
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    <div>
                        <InputLabel htmlFor="description">Description (optionnel)</InputLabel>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="flex gap-4">
                        <Button type="button" variant="secondary" onClick={() => setStep(1)}>
                            Retour
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Suivant
                        </Button>
                    </div>
                </form>
            )}

            {step === 3 && event && (
                <div className="space-y-8">
                    <dl className="space-y-4 border-t border-line pt-6">
                        <Row label="Titre" value={event.title} />
                        {event.subtitle && <Row label="Sous-titre" value={event.subtitle} />}
                        <Row label="Type" value={eventTypes.find((t) => t.value === data.type)?.label ?? data.type} />
                        <Row label="Début" value={formatInEventTimezone(event.startAt, event.timezone)} />
                        <Row label="Fin" value={formatInEventTimezone(event.endAt, event.timezone)} />
                        <Row label="Fuseau horaire" value={timezones[event.timezone] ?? event.timezone} />
                        {event.venueName && <Row label="Lieu" value={event.venueName} />}
                    </dl>

                    <div className="flex flex-wrap gap-4">
                        <Button type="button" variant="secondary" onClick={() => setStep(2)}>
                            Modifier
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => setShowDuplicate((v) => !v)}>
                            Dupliquer
                        </Button>
                        <Link
                            href="/dashboard"
                            className="inline-flex min-h-11 items-center justify-center gap-2.5 rounded-pill bg-ink px-8 py-4 font-sans text-[14.5px] font-medium text-bg transition-all duration-300 hover:-translate-y-0.5 hover:opacity-90"
                        >
                            Terminer
                        </Link>
                    </div>

                    {showDuplicate && (
                        <form onSubmit={submitDuplicate} className="space-y-4 border-t border-line pt-6">
                            <div>
                                <InputLabel htmlFor="new_start_at">Nouvelle date de début</InputLabel>
                                <TextInput
                                    id="new_start_at"
                                    type="datetime-local"
                                    value={duplicateForm.data.new_start_at}
                                    onChange={(e) => duplicateForm.setData('new_start_at', e.target.value)}
                                    required
                                />
                                <InputError message={duplicateForm.errors.new_start_at} />
                                <p className="mt-2 text-sm text-ink-soft">
                                    Toutes les dates (fin, sous-événements…) seront décalées du même écart, pas
                                    copiées telles quelles.
                                </p>
                            </div>

                            <Button type="submit" disabled={duplicateForm.processing}>
                                Confirmer la duplication
                            </Button>
                        </form>
                    )}
                </div>
            )}
        </OrganizerLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase">{label}</dt>
            <dd className="text-right text-ink">{value}</dd>
        </div>
    );
}

function VenueModeButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-pill border px-4 py-2 font-sans text-sm transition-colors duration-300 ${
                active ? 'border-accent text-ink' : 'border-line text-ink-soft hover:border-ink'
            }`}
        >
            {children}
        </button>
    );
}
