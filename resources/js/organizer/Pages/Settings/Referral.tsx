import { Head } from '@inertiajs/react';
import { useState } from 'react';
import SettingsLayout from '../../Layouts/SettingsLayout';

interface Props {
    referralUrl: string;
    referredCount: number;
}

export default function Referral({ referralUrl, referredCount }: Props) {
    const [copied, setCopied] = useState(false);

    async function copyLink() {
        await navigator.clipboard.writeText(referralUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <SettingsLayout title="Paramètres" active="referral">
            <Head title="Refer-a-Friend" />

            <div className="mb-10 rounded-card bg-bg p-6 ring-1 ring-line">
                <p className="mb-1 font-label text-xs tracking-[0.14em] text-accent uppercase">Offre à durée illimitée</p>
                <h2 className="mb-2 font-serif text-2xl text-ink italic">Offrez un mois, recevez un mois</h2>
                <p className="mb-6 text-sm text-ink-soft">
                    Quand un·e ami·e s'inscrit via votre lien et passe à un forfait payant, votre abonnement et le sien
                    sont tous les deux prolongés de 30 jours.
                </p>

                <div className="mb-2 flex max-w-xl gap-2">
                    <input
                        readOnly
                        value={referralUrl}
                        className="flex-1 rounded-pill border border-line bg-bg-alt px-4 py-2 text-sm text-ink-soft"
                    />
                    <button
                        type="button"
                        onClick={copyLink}
                        className="shrink-0 rounded-pill bg-ink px-6 py-2 text-sm font-medium text-bg hover:opacity-90"
                    >
                        {copied ? 'Lien copié' : 'Copier le lien'}
                    </button>
                </div>

                <p className="text-sm text-ink-soft">
                    {referredCount} organisation{referredCount > 1 ? 's' : ''} inscrite{referredCount > 1 ? 's' : ''} via
                    votre lien.
                </p>
            </div>

            <div>
                <h3 className="mb-4 font-serif text-lg text-ink italic">Comment ça marche</h3>
                <ol className="space-y-4 text-sm text-ink-soft">
                    <li className="flex gap-3">
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent/10 text-xs text-accent">
                            1
                        </span>
                        Partagez votre lien avec un·e autre organisateur·rice.
                    </li>
                    <li className="flex gap-3">
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent/10 text-xs text-accent">
                            2
                        </span>
                        Il ou elle s'inscrit et passe à un forfait payant.
                    </li>
                    <li className="flex gap-3">
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent/10 text-xs text-accent">
                            3
                        </span>
                        Vous recevez chacun·e un mois gratuit, ajouté automatiquement à votre abonnement en cours.
                    </li>
                </ol>
            </div>
        </SettingsLayout>
    );
}
