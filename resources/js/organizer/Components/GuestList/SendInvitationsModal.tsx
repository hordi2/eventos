import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Button from '../Button';
import InputError from '../InputError';
import InputLabel from '../InputLabel';
import Modal from '../Modal';
import Select from '../Select';
import { type TemplateOption } from './types';

type Channel = 'email' | 'whatsapp';
type Audience = 'all' | 'unanswered' | 'selected';
type Mode = 'per_invitee' | 'per_group';

interface SendInvitationsModalProps {
    open: boolean;
    onClose: () => void;
    eventId: number;
    emailTemplates: TemplateOption[];
    whatsappTemplates: TemplateOption[];
    selectedIds: number[];
    hasGroups: boolean;
}

interface Summary {
    recipients: number;
    missing: number;
    excluded: number;
    coveredByGroup: number;
}

function plural(count: number, one: string, many: string): string {
    return `${count} ${count > 1 ? many : one}`;
}

/**
 * Envoi groupé des invitations. L'aperçu vient du serveur (mêmes règles que
 * l'envoi : désabonnés et invités sans accord WhatsApp sont écartés) ; la clé
 * d'envoi, tirée à l'ouverture, empêche une double confirmation d'envoyer
 * deux fois.
 */
export default function SendInvitationsModal({ open, onClose, eventId, emailTemplates, whatsappTemplates, selectedIds, hasGroups }: SendInvitationsModalProps) {
    const [channel, setChannel] = useState<Channel>('email');
    const [templateId, setTemplateId] = useState('');
    const [audience, setAudience] = useState<Audience>(selectedIds.length > 0 ? 'selected' : 'unanswered');
    const [mode, setMode] = useState<Mode>(hasGroups ? 'per_group' : 'per_invitee');
    const [summary, setSummary] = useState<Summary | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [sendKey] = useState(() => crypto.randomUUID());

    const templates = channel === 'email' ? emailTemplates : whatsappTemplates;
    const params = { channel, template_id: templateId, audience, mode, invitee_ids: audience === 'selected' ? selectedIds : [] };

    useEffect(() => {
        if (templateId === '') {
            return;
        }

        let cancelled = false;

        window.axios
            .get<Summary>(`/events/${eventId}/guest-list/send/preview`, { params })
            .then((response) => {
                if (!cancelled) {
                    setSummary(response.data);
                    setPreviewError(null);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setSummary(null);
                    setPreviewError("L'aperçu n'a pas pu être calculé. Vérifiez vos choix puis réessayez.");
                }
            });

        return () => {
            cancelled = true;
        };
        // params est reconstruit à chaque rendu : ses composantes suffisent.
    }, [eventId, channel, templateId, audience, mode, selectedIds]);

    function changeChannel(next: Channel) {
        setChannel(next);
        setTemplateId('');
        setSummary(null);
    }

    function send() {
        router.post(
            `/events/${eventId}/guest-list/send`,
            { ...params, send_key: sendKey },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (formErrors) => setErrors(formErrors as Record<string, string>),
                onSuccess: onClose,
            },
        );
    }

    const recipients = templateId === '' ? null : (summary?.recipients ?? null);

    return (
        <Modal open={open} onClose={onClose} title="Envoyer les invitations" size="lg" showCloseButton>
            <div className="space-y-6">
                <fieldset>
                    <legend className="mb-2 block font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Canal</legend>
                    <div className="flex flex-wrap gap-2">
                        {(
                            [
                                ['email', 'E-mail'],
                                ['whatsapp', 'WhatsApp'],
                            ] as const
                        ).map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                aria-pressed={channel === value}
                                onClick={() => changeChannel(value)}
                                className={`rounded-pill px-4 py-2 text-sm font-medium ${channel === value ? 'bg-ink text-bg' : 'border border-line text-ink hover:border-ink'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </fieldset>

                <div>
                    <InputLabel htmlFor="send_template">Modèle de message</InputLabel>
                    {templates.length === 0 ? (
                        <p className="rounded-control bg-bg-alt px-4 py-3 text-sm text-ink-soft">
                            Aucun modèle {channel === 'email' ? "d'e-mail" : 'WhatsApp'} pour l'instant.{' '}
                            <Link href={channel === 'email' ? '/email-templates/create' : '/whatsapp-templates'} className="text-ink underline hover:no-underline">
                                Créer un modèle
                            </Link>
                        </p>
                    ) : (
                        <Select id="send_template" value={templateId} onChange={(event) => setTemplateId(event.target.value)}>
                            <option value="">Choisir un modèle…</option>
                            {templates.map((template) => (
                                <option key={template.id} value={String(template.id)}>
                                    {template.name}
                                </option>
                            ))}
                        </Select>
                    )}
                    <p className="mt-1.5 text-xs text-ink-soft">
                        Placez <code className="rounded bg-bg-alt px-1">{'{{rsvp_link}}'}</code> dans le modèle : chaque invité y reçoit son lien personnel.
                    </p>
                    <InputError message={errors.template_id} />
                </div>

                <fieldset>
                    <legend className="mb-2 block font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Destinataires</legend>
                    <div className="space-y-2">
                        {(
                            [
                                ['unanswered', 'Les invités qui n’ont pas encore répondu'],
                                ['all', 'Tous les invités de la liste'],
                                ['selected', `Les invités cochés (${selectedIds.length})`],
                            ] as const
                        ).map(([value, label]) => (
                            <label key={value} className={`flex items-center gap-3 rounded-control border px-4 py-3 text-sm ${audience === value ? 'border-ink' : 'border-line'} ${value === 'selected' && selectedIds.length === 0 ? 'opacity-50' : 'cursor-pointer'}`}>
                                <input
                                    type="radio"
                                    name="send_audience"
                                    value={value}
                                    checked={audience === value}
                                    disabled={value === 'selected' && selectedIds.length === 0}
                                    onChange={() => setAudience(value)}
                                />
                                <span className="text-ink">{label}</span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.invitee_ids} />
                </fieldset>

                <fieldset>
                    <legend className="mb-2 block font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Groupes</legend>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {(
                            [
                                ['per_group', 'Un message par groupe', 'Un seul membre du couple ou de la famille le reçoit, et répond pour tous.'],
                                ['per_invitee', 'Un message par invité', 'Chaque membre reçoit son propre message et son propre lien.'],
                            ] as const
                        ).map(([value, label, hint]) => (
                            <label key={value} className={`flex cursor-pointer items-start gap-3 rounded-control border px-4 py-3 ${mode === value ? 'border-ink' : 'border-line'}`}>
                                <input type="radio" name="send_mode" value={value} checked={mode === value} onChange={() => setMode(value)} className="mt-1" />
                                <span>
                                    <span className="block text-sm text-ink">{label}</span>
                                    <span className="block text-xs text-ink-soft">{hint}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                </fieldset>

                {templateId !== '' && (
                    <div className="rounded-card bg-bg-alt px-4 py-3 text-sm" aria-live="polite">
                        {previewError && <p className="text-danger">{previewError}</p>}
                        {summary && (
                            <ul className="space-y-1 text-ink-soft">
                                <li className="text-ink">{plural(summary.recipients, 'message partira', 'messages partiront')}.</li>
                                {summary.coveredByGroup > 0 && (
                                    <li>{plural(summary.coveredByGroup, 'invité est couvert', 'invités sont couverts')} par un autre membre de son groupe.</li>
                                )}
                                {summary.missing > 0 && (
                                    <li>
                                        {plural(summary.missing, 'invité', 'invités')} sans {channel === 'email' ? 'adresse e-mail' : 'numéro WhatsApp'} : rien ne partira pour eux.
                                    </li>
                                )}
                                {summary.excluded > 0 && (
                                    <li>
                                        {plural(summary.excluded, 'invité écarté', 'invités écartés')} :{' '}
                                        {channel === 'email' ? 'désabonnés ou adresse invalide.' : 'sans accord pour recevoir des messages WhatsApp.'}
                                    </li>
                                )}
                            </ul>
                        )}
                    </div>
                )}

                <InputError message={errors.send ?? errors.agreement} />

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button type="button" variant="secondary" onClick={onClose} className="sm:w-auto">
                        Annuler
                    </Button>
                    <Button type="button" onClick={send} disabled={processing || recipients === null || recipients === 0} className="sm:w-auto">
                        {recipients === null ? 'Envoyer' : `Envoyer ${plural(recipients, 'invitation', 'invitations')}`}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
