import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface ContactDraft {
    id: number;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone_e164: string | null;
    company: string | null;
    job_title: string | null;
    preferred_language: string | null;
    preferred_channel: string | null;
    household_name: string | null;
    email_consent: boolean;
    email_consent_source: string | null;
    email_consent_at: string | null;
    sms_consent: boolean;
    sms_consent_source: string | null;
    sms_consent_at: string | null;
    whatsapp_consent: boolean;
    whatsapp_consent_source: string | null;
    whatsapp_consent_at: string | null;
    tag_ids: number[];
}

interface HistoryEntry {
    id: number;
    event_id: number;
    status: string;
    registered_at: string;
}

interface TagOption {
    id: number;
    name: string;
    color: string;
}

const STATUS_LABELS: Record<string, string> = {
    confirmed: 'Confirmée',
    waitlisted: 'Liste d\'attente',
    cancelled: 'Annulée',
};

function ConsentField({
    label,
    consent,
    source,
    at,
    onChange,
}: {
    label: string;
    consent: boolean;
    source: string | null;
    at: string | null;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="mb-4 flex items-start justify-between gap-4 border-b border-line pb-4">
            <div>
                <label className="flex items-center gap-2 text-sm text-ink">
                    <Checkbox checked={consent} onChange={(e) => onChange(e.target.checked)} />
                    {label}
                </label>
                {at && (
                    <p className="mt-1 text-xs text-ink-soft">
                        {source ?? 'source inconnue'} — {new Date(at).toLocaleString('fr-FR')}
                    </p>
                )}
            </div>
        </div>
    );
}

export default function ContactForm({
    contact,
    history,
    availableTags = [],
}: {
    contact: ContactDraft | null;
    history?: HistoryEntry[];
    availableTags?: TagOption[];
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        first_name: contact?.first_name ?? '',
        last_name: contact?.last_name ?? '',
        email: contact?.email ?? '',
        phone_e164: contact?.phone_e164 ?? '',
        company: contact?.company ?? '',
        job_title: contact?.job_title ?? '',
        preferred_language: contact?.preferred_language ?? '',
        preferred_channel: contact?.preferred_channel ?? '',
        household_name: contact?.household_name ?? '',
        email_consent: contact?.email_consent ?? false,
        sms_consent: contact?.sms_consent ?? false,
        whatsapp_consent: contact?.whatsapp_consent ?? false,
        tag_ids: contact?.tag_ids ?? [],
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        if (contact) {
            patch(`/contacts/${contact.id}`);
        } else {
            post('/contacts');
        }
    }

    function toggleTag(tagId: number) {
        setData('tag_ids', data.tag_ids.includes(tagId) ? data.tag_ids.filter((id) => id !== tagId) : [...data.tag_ids, tagId]);
    }

    return (
        <OrganizerLayout
            title={contact ? contact.first_name || contact.email || `Contact #${contact.id}` : 'Nouveau contact'}
            eyebrow="Contacts"
        >
            <Head title={contact ? 'Modifier le contact' : 'Nouveau contact'} />

            <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <form onSubmit={handleSubmit}>
                    <div className="mb-5 grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="first_name">Prénom</InputLabel>
                            <TextInput id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                            <InputError message={errors.first_name} />
                        </div>
                        <div>
                            <InputLabel htmlFor="last_name">Nom</InputLabel>
                            <TextInput id="last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                            <InputError message={errors.last_name} />
                        </div>
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="email">E-mail</InputLabel>
                        <TextInput id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        <InputError message={errors.email} />
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="phone_e164">Téléphone</InputLabel>
                        <TextInput id="phone_e164" value={data.phone_e164} onChange={(e) => setData('phone_e164', e.target.value)} />
                        <InputError message={errors.phone_e164} />
                    </div>

                    <div className="mb-5 grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="company">Entreprise</InputLabel>
                            <TextInput id="company" value={data.company} onChange={(e) => setData('company', e.target.value)} />
                        </div>
                        <div>
                            <InputLabel htmlFor="job_title">Fonction</InputLabel>
                            <TextInput id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                        </div>
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="household_name">Foyer / groupe</InputLabel>
                        <TextInput
                            id="household_name"
                            value={data.household_name}
                            onChange={(e) => setData('household_name', e.target.value)}
                            placeholder="Ex. Famille Mbuyi"
                        />
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="preferred_channel">Canal préféré</InputLabel>
                        <Select
                            id="preferred_channel"
                            value={data.preferred_channel}
                            onChange={(e) => setData('preferred_channel', e.target.value)}
                        >
                            <option value="">—</option>
                            <option value="email">E-mail</option>
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                        </Select>
                    </div>

                    {availableTags.length > 0 && (
                        <div className="mb-8">
                            <InputLabel>Tags</InputLabel>
                            <div className="flex flex-wrap gap-2">
                                {availableTags.map((tag) => {
                                    const active = data.tag_ids.includes(tag.id);

                                    return (
                                        <button
                                            key={tag.id}
                                            type="button"
                                            onClick={() => toggleTag(tag.id)}
                                            className={`inline-flex items-center gap-1.5 rounded-pill border px-3 py-1.5 text-sm ${
                                                active ? 'border-ink text-ink' : 'border-line text-ink-soft'
                                            }`}
                                        >
                                            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: tag.color }} />
                                            {tag.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Consentements</h2>

                    <ConsentField
                        label="E-mail"
                        consent={data.email_consent}
                        source={contact?.email_consent_source ?? null}
                        at={contact?.email_consent_at ?? null}
                        onChange={(value) => setData('email_consent', value)}
                    />
                    <ConsentField
                        label="SMS"
                        consent={data.sms_consent}
                        source={contact?.sms_consent_source ?? null}
                        at={contact?.sms_consent_at ?? null}
                        onChange={(value) => setData('sms_consent', value)}
                    />
                    <ConsentField
                        label="WhatsApp"
                        consent={data.whatsapp_consent}
                        source={contact?.whatsapp_consent_source ?? null}
                        at={contact?.whatsapp_consent_at ?? null}
                        onChange={(value) => setData('whatsapp_consent', value)}
                    />

                    <div className="mt-8">
                        <Button type="submit" disabled={processing}>
                            {contact ? 'Enregistrer' : 'Créer le contact'}
                        </Button>
                    </div>
                </form>

                {contact && (
                    <div>
                        <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Historique de participation</h2>
                        {history && history.length > 0 ? (
                            <ul className="space-y-3">
                                {history.map((entry) => (
                                    <li key={entry.id} className="flex items-center justify-between rounded-card border border-line px-4 py-3">
                                        <div>
                                            <p className="text-sm text-ink">Événement #{entry.event_id}</p>
                                            <p className="text-xs text-ink-soft">{new Date(entry.registered_at).toLocaleDateString('fr-FR')}</p>
                                        </div>
                                        <Badge variant={entry.status === 'cancelled' ? 'neutral' : 'success'}>
                                            {STATUS_LABELS[entry.status] ?? entry.status}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-ink-soft">Aucune inscription pour l'instant.</p>
                        )}
                    </div>
                )}
            </div>
        </OrganizerLayout>
    );
}
