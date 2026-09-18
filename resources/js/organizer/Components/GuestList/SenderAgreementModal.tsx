import { router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../Button';
import Modal from '../Modal';

interface SenderAgreementModalProps {
    open: boolean;
    onClose: () => void;
}

const COMMITMENTS: { title: string; text: string }[] = [
    {
        title: 'Des personnes qui ont dit oui',
        text: "Je n'invite que des personnes qui ont accepté de recevoir mes messages : une relation existante, une inscription, une demande de leur part.",
    },
    {
        title: 'Aucune liste achetée',
        text: "Je n'utilise aucune liste d'adresses achetée, louée ou récupérée auprès d'un tiers.",
    },
    {
        title: 'Des coordonnées à jour',
        text: 'Je retire les adresses et numéros invalides, et les personnes qui ne souhaitent plus rien recevoir.',
    },
    {
        title: 'Le respect de la loi',
        text: 'Je respecte les lois sur les messages électroniques et la protection des données des pays de mes invités.',
    },
    {
        title: 'Ma responsabilité',
        text: "Je sais qu'un usage abusif peut conduire Itaza à suspendre les envois de mon organisation.",
    },
];

/**
 * Accord d'envoi (une fois par organisation) : demandé à l'ouverture de la
 * liste d'invités, avant d'y ajouter quiconque. Le serveur le date, retient
 * qui l'a accepté et l'inscrit au journal d'audit.
 */
export default function SenderAgreementModal({ open, onClose }: SenderAgreementModalProps) {
    const [checked, setChecked] = useState(false);
    const [processing, setProcessing] = useState(false);

    function accept() {
        router.post(
            '/sender-agreement',
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onClose,
            },
        );
    }

    return (
        <Modal open={open} onClose={onClose} title="Avant d'inviter : nos engagements d'envoi" size="lg" showCloseButton>
            <p className="text-sm text-ink-soft">
                Vos invitations partent au nom de votre organisation. Pour qu'elles arrivent bien, et pour protéger vos invités, votre organisation
                s'engage une fois pour toutes :
            </p>

            <ul className="mt-5 space-y-4">
                {COMMITMENTS.map((commitment) => (
                    <li key={commitment.title} className="flex gap-3">
                        <span aria-hidden="true" className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-accent" />
                        <span className="text-sm text-ink">
                            <span className="font-medium">{commitment.title}.</span> <span className="text-ink-soft">{commitment.text}</span>
                        </span>
                    </li>
                ))}
            </ul>

            <label className="mt-6 flex cursor-pointer items-start gap-3 rounded-control bg-bg-alt px-4 py-3">
                <input type="checkbox" checked={checked} onChange={(event) => setChecked(event.target.checked)} className="mt-0.5" />
                <span className="text-sm text-ink">J'ai lu ces engagements et je les accepte au nom de mon organisation.</span>
            </label>

            <p className="mt-3 text-xs text-ink-soft">L'acceptation vaut pour toute l'organisation ; sa date et votre nom sont conservés.</p>

            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="secondary" onClick={onClose} className="sm:w-auto">
                    Plus tard
                </Button>
                <Button type="button" onClick={accept} disabled={!checked || processing} className="sm:w-auto">
                    J'accepte
                </Button>
            </div>
        </Modal>
    );
}
