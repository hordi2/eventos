import { Head, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface Props {
    referralUrl: string;
    referredCount: number;
    rewardedCount: number;
}

const STATUS_MESSAGES: Record<string, string> = {
    'referral-invitation-sent': 'Invitation envoyée.',
};

const STEPS = [
    {
        text: 'Partagez votre lien avec un ami ou un organisateur d’événements.',
        icon: 'M13.5 6.5 17 3a3 3 0 0 1 4 4l-3.5 3.5M10.5 17.5 7 21a3 3 0 0 1-4-4l3.5-3.5M8 16l8-8',
    },
    {
        text: 'Il ou elle s’inscrit et passe à un forfait payant.',
        icon: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6',
    },
    {
        text: 'Vous recevez chacun un mois gratuit, ajouté à votre abonnement en cours.',
        icon: 'M3 8h18v4H3zM5 12h14v9H5zM12 8v13M12 8c-1.5-3-6-3-6 0s4.5 0 6 0ZM12 8c1.5-3 6-3 6 0s-4.5 0-6 0Z',
    },
];

export default function Referral({ referralUrl, referredCount, rewardedCount }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [copied, setCopied] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ email: '' });

    async function copyLink() {
        await navigator.clipboard.writeText(referralUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    function submitInvitation(event: FormEvent) {
        event.preventDefault();
        post('/settings/referral/invitations', { preserveScroll: true, onSuccess: () => reset('email') });
    }

    const shareText = encodeURIComponent("J'organise mes événements avec Itaza Invitation, essayez-le :");
    const encodedUrl = encodeURIComponent(referralUrl);

    const socials = [
        { label: 'Facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}` },
        { label: 'Twitter', href: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${shareText}` },
        { label: 'LinkedIn', href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}` },
    ];

    return (
        <SettingsLayout title="Paramètres" active="referral">
            <Head title="Refer-a-Friend" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <div className="grid gap-10 lg:grid-cols-[1fr_320px]">
                <div className="rounded-card border border-line bg-bg p-8">
                    <span className="mb-6 inline-flex items-center gap-2 rounded-pill border border-success/30 bg-success-bg px-3 py-1 text-xs text-success">
                        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 stroke-current fill-none" strokeWidth="2">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 2" strokeLinecap="round" />
                        </svg>
                        Offre permanente
                    </span>

                    <h2 className="mb-2 font-serif text-3xl text-ink italic">Offrez un mois, recevez un mois</h2>
                    <p className="mb-8 text-sm text-ink-soft">
                        Soyez récompensé lorsque votre filleul s'inscrit à Itaza Invitation et passe à un forfait payant.
                    </p>

                    <p className="mb-2 text-sm font-medium text-ink">Copiez et partagez votre lien</p>
                    <div className="mb-4 flex flex-wrap gap-2">
                        <input
                            readOnly
                            value={referralUrl}
                            className="min-w-0 flex-1 rounded-pill border border-line bg-bg-alt px-4 py-2.5 text-sm text-ink-soft"
                        />
                        <button
                            type="button"
                            onClick={copyLink}
                            className="shrink-0 rounded-pill bg-ink px-6 py-2.5 text-sm font-medium text-bg hover:opacity-90"
                        >
                            {copied ? 'Lien copié' : 'Copier le lien'}
                        </button>
                    </div>

                    <p className="mb-8 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-soft">
                        Partagez le lien sur :
                        {socials.map((social) => (
                            <a
                                key={social.label}
                                href={social.href}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-accent underline underline-offset-4 hover:text-ink"
                            >
                                {social.label}
                            </a>
                        ))}
                    </p>

                    <form onSubmit={submitInvitation} className="border-t border-line pt-6">
                        <InputLabel htmlFor="email">Invitez par e-mail</InputLabel>
                        <TextInput
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="Saisissez l'adresse e-mail"
                            required
                        />
                        <InputError message={errors.email} />

                        <div className="mt-4 flex justify-end">
                            <Button type="submit" disabled={processing} className="w-auto">
                                Envoyer un e-mail
                            </Button>
                        </div>
                    </form>

                    <p className="mt-6 border-t border-line pt-6 text-sm text-ink-soft">
                        {referredCount} organisation{referredCount > 1 ? 's' : ''} inscrite{referredCount > 1 ? 's' : ''} via
                        votre lien, dont {rewardedCount} vous {rewardedCount > 1 ? 'ont' : 'a'} déjà fait gagner un mois.
                    </p>
                </div>

                <div>
                    <h3 className="mb-6 font-serif text-2xl text-ink italic">Comment ça marche</h3>
                    <ol className="space-y-6">
                        {STEPS.map((step, index) => (
                            <li key={step.text} className="flex gap-4 border-b border-line pb-6 last:border-0">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-card bg-accent/10 text-accent">
                                    <svg
                                        viewBox="0 0 24 24"
                                        className="h-5 w-5 stroke-current fill-none"
                                        strokeWidth="1.6"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d={step.icon} />
                                    </svg>
                                </span>
                                <p className="text-sm text-ink-soft">
                                    {index + 1}. {step.text}
                                </p>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
        </SettingsLayout>
    );
}
