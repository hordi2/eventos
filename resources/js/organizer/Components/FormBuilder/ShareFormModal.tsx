import { router } from '@inertiajs/react';
import { useState } from 'react';
import EventIcon from '../EventIcon';
import InputError from '../InputError';
import Modal from '../Modal';
import { type FormSharing } from './types';

interface ShareFormModalProps {
    open: boolean;
    onClose: () => void;
    eventId: number;
    sharing: FormSharing;
    publishError?: string;
}

const ACTION_CLASSES = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-pill border border-line px-4 py-2 text-sm font-medium text-ink hover:border-ink';

/**
 * Partage d'un formulaire publié : lien à copier ou ouvrir, envoi par
 * WhatsApp ou e-mail, QR code pour une affiche. Tant que l'événement est
 * « Inédit », seul un lien de test existe : il est donné, avec de quoi
 * publier l'événement sans quitter le constructeur.
 */
export default function ShareFormModal({ open, onClose, eventId, sharing, publishError }: ShareFormModalProps) {
    const [copied, setCopied] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const url = sharing.url ?? '';

    async function copy() {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Copiez ce lien :', url);
        }
    }

    function publishEvent() {
        router.post(
            `/events/${eventId}/publish`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setPublishing(true),
                onFinish: () => setPublishing(false),
            },
        );
    }

    return (
        <Modal open={open} onClose={onClose} title="Partager le formulaire" size="lg" showCloseButton>
            <div className="space-y-6">
                {sharing.isPreview && (
                    <div className="rounded-card bg-[#fff1cc] p-4 text-sm text-[#7a4f00]">
                        <p>
                            L'événement est encore « Inédit » : ce lien de test fonctionne {sharing.previewDays} jours, pour relire le formulaire ou le faire relire.
                            Publiez l'événement pour obtenir le lien définitif à envoyer à vos invités.
                        </p>
                        <button
                            type="button"
                            onClick={publishEvent}
                            disabled={publishing}
                            className="mt-3 inline-flex min-h-10 items-center rounded-pill bg-ink px-5 py-2 text-sm font-medium text-bg hover:opacity-90 disabled:opacity-40"
                        >
                            {publishing ? 'Publication…' : "Publier l'événement"}
                        </button>
                        <InputError message={publishError} />
                    </div>
                )}

                <div>
                    <label htmlFor="share_url" className="mb-2 block font-label text-xs tracking-[0.1em] text-ink-soft uppercase">
                        {sharing.isPreview ? 'Lien de test' : 'Lien du formulaire'}
                    </label>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <input
                            id="share_url"
                            type="text"
                            readOnly
                            value={url}
                            onFocus={(focusEvent) => focusEvent.target.select()}
                            className="min-w-0 flex-1 rounded-control border border-line bg-bg-alt px-3 py-2 text-sm text-ink"
                        />
                        <button type="button" onClick={copy} className={ACTION_CLASSES}>
                            <EventIcon name={copied ? 'check' : 'link'} className="h-4 w-4" />
                            {copied ? 'Lien copié' : 'Copier'}
                        </button>
                        <a href={url} target="_blank" rel="noopener" className={ACTION_CLASSES}>
                            <EventIcon name="external" className="h-4 w-4" />
                            Voir le formulaire
                        </a>
                    </div>
                </div>

                <div>
                    <p className="mb-2 font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Envoyer</p>
                    <div className="flex flex-wrap gap-2">
                        {sharing.whatsappUrl && (
                            <a href={sharing.whatsappUrl} target="_blank" rel="noopener" className={ACTION_CLASSES}>
                                <EventIcon name="send" className="h-4 w-4" />
                                Par WhatsApp
                            </a>
                        )}
                        {sharing.mailtoUrl && (
                            <a href={sharing.mailtoUrl} className={ACTION_CLASSES}>
                                <EventIcon name="mail" className="h-4 w-4" />
                                Par e-mail
                            </a>
                        )}
                    </div>
                    <p className="mt-2 text-xs text-ink-soft">
                        Ce lien est commun à tous. Pour une liste d'invités fermée, envoyez plutôt les liens personnels depuis la liste d'invités.
                    </p>
                </div>

                <div>
                    <p className="mb-2 font-label text-xs tracking-[0.1em] text-ink-soft uppercase">QR code</p>
                    {sharing.qrCode ? (
                        <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                            <img src={sharing.qrCode} alt="QR code du lien du formulaire" width={160} height={160} className="rounded-card ring-1 ring-line" />
                            <div className="text-sm text-ink-soft">
                                <p className="mb-3">À imprimer sur une affiche ou un faire-part : un scan ouvre le formulaire.</p>
                                <a href={sharing.qrCode} download="qr-code-formulaire.png" className={ACTION_CLASSES}>
                                    <EventIcon name="download" className="h-4 w-4" />
                                    Télécharger le QR code
                                </a>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-ink-soft">Le QR code sera disponible une fois l'événement publié : un lien de test ne doit pas finir sur une affiche.</p>
                    )}
                </div>
            </div>
        </Modal>
    );
}
