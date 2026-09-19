import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type KeyboardEvent } from 'react';
import Button from '../Button';
import InputError from '../InputError';
import InputLabel from '../InputLabel';
import Modal from '../Modal';
import Select from '../Select';
import TextInput from '../TextInput';
import { type InviteeRow, type InviteeTag } from './types';

interface InviteeFormModalProps {
    open: boolean;
    onClose: () => void;
    eventId: number;
    invitee: InviteeRow | null;
    groups: string[];
    tags: InviteeTag[];
    maxCompanions: number;
}

const UNLIMITED = 'illimité';

function companionsValue(invitee: InviteeRow | null): string {
    if (invitee === null || invitee.companionsAllowed === 0) {
        return '';
    }

    return invitee.companionsAllowed === null ? UNLIMITED : String(invitee.companionsAllowed);
}

/**
 * Ajout ou modification d'un invité. L'identité corrige la fiche du contact,
 * partagée par tous les événements ; le groupe, les accompagnants et la copie
 * ne concernent que cette invitation.
 */
export default function InviteeFormModal({ open, onClose, eventId, invitee, groups, tags, maxCompanions }: InviteeFormModalProps) {
    const [tagDraft, setTagDraft] = useState('');
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({
        first_name: invitee?.firstName ?? '',
        last_name: invitee?.lastName ?? '',
        email: invitee?.email ?? '',
        phone: invitee?.phone ?? '',
        group_key: invitee?.groupKey ?? '',
        companions: companionsValue(invitee),
        cc_email: invitee?.ccEmail ?? '',
        tags: invitee?.tags.map((tag) => tag.name) ?? ([] as string[]),
        whatsapp_consent: invitee?.whatsappConsent ?? false,
    });
    const formErrors = errors as Record<string, string | undefined>;

    function close() {
        reset();
        clearErrors();
        setTagDraft('');
        onClose();
    }

    function toggleTag(name: string) {
        setData('tags', data.tags.includes(name) ? data.tags.filter((tag) => tag !== name) : [...data.tags, name]);
    }

    function addDraftTags() {
        const names = tagDraft
            .split(',')
            .map((name) => name.trim())
            .filter((name) => name !== '' && !data.tags.includes(name));

        if (names.length > 0) {
            setData('tags', [...data.tags, ...names]);
        }

        setTagDraft('');
    }

    function handleTagKey(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addDraftTags();
        }
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };

        if (invitee) {
            patch(`/events/${eventId}/guest-list/${invitee.id}`, options);
        } else {
            post(`/events/${eventId}/guest-list`, options);
        }
    }

    const suggestions = tags.filter((tag) => !data.tags.includes(tag.name));

    return (
        <Modal open={open} onClose={close} title={invitee ? "Modifier l'invité" : 'Ajouter un invité'} size="lg" showCloseButton>
            <form onSubmit={submit} className="space-y-5">
                <InputError message={formErrors.agreement} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="invitee_first_name">Prénom</InputLabel>
                        <TextInput id="invitee_first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} autoFocus />
                        <InputError message={errors.first_name} />
                    </div>
                    <div>
                        <InputLabel htmlFor="invitee_last_name">Nom</InputLabel>
                        <TextInput id="invitee_last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                        <InputError message={errors.last_name} />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="invitee_email">E-mail</InputLabel>
                        <TextInput id="invitee_email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        <InputError message={errors.email} />
                    </div>
                    <div>
                        <InputLabel htmlFor="invitee_phone">Téléphone (WhatsApp)</InputLabel>
                        <TextInput
                            id="invitee_phone"
                            type="tel"
                            value={data.phone}
                            placeholder="+243 81 234 5678"
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                        <InputError message={errors.phone} />
                    </div>
                </div>
                <p className="-mt-2 text-xs text-ink-soft">Un nom complet et/ou une adresse e-mail suffisent.</p>

                <label className={`flex items-start gap-3 rounded-control bg-bg-alt px-4 py-3 ${data.phone.trim() === '' ? 'opacity-60' : 'cursor-pointer'}`}>
                    <input
                        type="checkbox"
                        checked={data.whatsapp_consent}
                        disabled={data.phone.trim() === ''}
                        onChange={(e) => setData('whatsapp_consent', e.target.checked)}
                        className="mt-0.5"
                    />
                    <span className="text-sm">
                        <span className="block text-ink">Cet invité accepte de recevoir des messages WhatsApp</span>
                        <span className="block text-xs text-ink-soft">
                            Sans son accord, aucune invitation ne lui part par WhatsApp. La date de l'accord est conservée.
                        </span>
                    </span>
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="invitee_group">Groupe</InputLabel>
                        <TextInput
                            id="invitee_group"
                            list="invitee_groups"
                            maxLength={50}
                            value={data.group_key}
                            placeholder="Famille Mbuyi, 1…"
                            onChange={(e) => setData('group_key', e.target.value)}
                        />
                        <datalist id="invitee_groups">
                            {groups.map((group) => (
                                <option key={group} value={group} />
                            ))}
                        </datalist>
                        <p className="mt-1.5 text-xs text-ink-soft">Les invités d'un même groupe répondent ensemble.</p>
                        <InputError message={errors.group_key} />
                    </div>
                    <div>
                        <InputLabel htmlFor="invitee_companions">Accompagnants autorisés</InputLabel>
                        <Select id="invitee_companions" value={data.companions} onChange={(e) => setData('companions', e.target.value)}>
                            <option value="">Aucun</option>
                            {Array.from({ length: maxCompanions }, (_, index) => index + 1).map((count) => (
                                <option key={count} value={String(count)}>
                                    +{count}
                                </option>
                            ))}
                            <option value={UNLIMITED}>Illimité</option>
                        </Select>
                        <p className="mt-1.5 text-xs text-ink-soft">Personnes qu'il peut amener en plus de lui.</p>
                        <InputError message={errors.companions} />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="invitee_tags">Tags</InputLabel>
                    {data.tags.length > 0 && (
                        <ul className="mb-2 flex flex-wrap gap-2">
                            {data.tags.map((name) => (
                                <li key={name}>
                                    <button
                                        type="button"
                                        onClick={() => toggleTag(name)}
                                        className="inline-flex items-center gap-1.5 rounded-pill bg-bg-deep px-3 py-1 text-xs text-ink hover:bg-line"
                                        aria-label={`Retirer le tag ${name}`}
                                    >
                                        {name} <span aria-hidden="true">×</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <TextInput
                        id="invitee_tags"
                        value={tagDraft}
                        placeholder="VIP, Dîner… puis Entrée"
                        onChange={(e) => setTagDraft(e.target.value)}
                        onKeyDown={handleTagKey}
                        onBlur={addDraftTags}
                    />
                    {suggestions.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-2">
                            {suggestions.slice(0, 12).map((tag) => (
                                <button
                                    key={tag.name}
                                    type="button"
                                    onClick={() => toggleTag(tag.name)}
                                    className="inline-flex items-center gap-1.5 rounded-pill border border-line px-3 py-1 text-xs text-ink-soft hover:border-ink hover:text-ink"
                                >
                                    <span aria-hidden="true" className="h-2 w-2 rounded-full" style={{ backgroundColor: tag.color }} />
                                    {tag.name}
                                </button>
                            ))}
                        </div>
                    )}
                    <InputError message={formErrors['tags.0'] ?? errors.tags} />
                </div>

                <div>
                    <InputLabel htmlFor="invitee_cc">E-mail en copie</InputLabel>
                    <TextInput
                        id="invitee_cc"
                        type="email"
                        value={data.cc_email}
                        placeholder="assistante@exemple.cd"
                        onChange={(e) => setData('cc_email', e.target.value)}
                    />
                    <p className="mt-1.5 text-xs text-ink-soft">Mise en copie des envois faits à cet invité (une assistante, un conjoint).</p>
                    <InputError message={errors.cc_email} />
                </div>

                <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="secondary" onClick={close} className="sm:w-auto">
                        Annuler
                    </Button>
                    <Button type="submit" disabled={processing} className="sm:w-auto">
                        {invitee ? 'Enregistrer' : "Ajouter l'invité"}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
