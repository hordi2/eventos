import { Head } from '@inertiajs/react';
import { type ChangeEvent, useRef, useState } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import SettingsLayout from '../../Layouts/SettingsLayout';

interface Props {
    branding: {
        logo_url: string | null;
        primary_color: string | null;
        theme_mode: 'light' | 'dark';
        is_agency: boolean;
        brands_client_portals: boolean;
    };
}

const DEFAULT_COLOR = '#1b1611';

const THEME_OPTIONS: { value: 'light' | 'dark'; label: string; description: string }[] = [
    { value: 'light', label: 'Normal', description: 'Fond clair — mode par défaut.' },
    { value: 'dark', label: 'Sombre', description: 'Fond sombre pour toute votre équipe.' },
];

export default function Branding({ branding }: Props) {
    const [logoUrl, setLogoUrl] = useState(branding.logo_url);
    const [primaryColor, setPrimaryColor] = useState(branding.primary_color ?? DEFAULT_COLOR);
    const [themeMode, setThemeMode] = useState<'light' | 'dark'>(branding.theme_mode);
    const [isAgency, setIsAgency] = useState(branding.is_agency);
    const [brandsClientPortals, setBrandsClientPortals] = useState(branding.brands_client_portals);
    const [saving, setSaving] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    async function handleLogoChange(changeEvent: ChangeEvent<HTMLInputElement>) {
        const file = changeEvent.target.files?.[0];

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('logo', file);

        setSaving(true);

        try {
            const response = await window.axios.post<{ logo_url: string }>('/organization/branding/logo', formData);
            setLogoUrl(response.data.logo_url);
        } finally {
            setSaving(false);
        }
    }

    async function handleSave() {
        setSaving(true);
        const themeModeChanged = themeMode !== branding.theme_mode;

        try {
            await window.axios.patch('/organization/branding', {
                primary_color: primaryColor,
                theme_mode: themeMode,
                is_agency: isAgency,
                brands_client_portals: brandsClientPortals,
            });

            // Le portefeuille apparaît ou disparaît du menu : la navigation
            // vient des propriétés partagées, posées au rendu de la page.
            if (isAgency !== branding.is_agency) {
                window.location.reload();

                return;
            }

            // data-theme est posé côté serveur (resources/views/app.blade.php),
            // au premier rendu de la page seulement — une navigation Inertia
            // classique ne le raffraîchirait pas, d'où le rechargement complet
            // ici, seulement quand le mode a réellement changé.
            if (themeModeChanged) {
                window.location.reload();

                return;
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <SettingsLayout title="Personnalisation" active="branding">
            <Head title="Charte graphique" />

            <div className="mb-8">
                <h1 className="text-2xl">Charte graphique</h1>
                <p className="text-ink-soft">
                    Le logo et la couleur principale sont appliqués à la page événement publique et à l'en-tête des e-mails de
                    l'organisation.
                </p>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Logo</h2>
                {logoUrl !== null && <img src={logoUrl} alt="Logo" className="mb-4 h-16 w-auto rounded-card object-contain" />}
                <InputLabel htmlFor="logo">{logoUrl !== null ? 'Remplacer le logo' : 'Téléverser un logo'}</InputLabel>
                <input
                    id="logo"
                    ref={fileInputRef}
                    type="file"
                    accept="image/png,image/jpeg"
                    onChange={(e) => void handleLogoChange(e)}
                    disabled={saving}
                    className="text-sm text-ink-soft"
                />
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Couleur principale</h2>
                <div className="flex items-center gap-3">
                    <input
                        type="color"
                        value={primaryColor}
                        onChange={(e) => setPrimaryColor(e.target.value)}
                        className="h-11 w-16 cursor-pointer rounded-control border border-line bg-transparent"
                    />
                    <span className="text-sm text-ink-soft">{primaryColor}</span>
                </div>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-1 font-serif text-lg italic">Mode d'affichage</h2>
                <p className="mb-4 text-sm text-ink-soft">
                    S'applique à l'espace organisateur pour toute votre équipe — jamais aux pages vues par vos invités.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    {THEME_OPTIONS.map((option) => (
                        <label
                            key={option.value}
                            className={`cursor-pointer rounded-card border p-4 ${themeMode === option.value ? 'border-ink' : 'border-line'}`}
                        >
                            <input
                                type="radio"
                                name="theme_mode"
                                value={option.value}
                                checked={themeMode === option.value}
                                onChange={() => setThemeMode(option.value)}
                                className="sr-only"
                            />
                            <span className="font-medium text-ink">{option.label}</span>
                            <span className="mt-1 block text-xs text-ink-soft">{option.description}</span>
                        </label>
                    ))}
                </div>
            </div>

            {/* Agence événementielle (D10) : ouvre le portefeuille de comptes clients. */}
            <div className="mb-8">
                <h2 className="mb-1 text-lg">Agence</h2>
                <label className="flex cursor-pointer items-start gap-3 rounded-card bg-bg p-5 ring-1 ring-line">
                    <input
                        type="checkbox"
                        checked={isAgency}
                        onChange={(event) => setIsAgency(event.target.checked)}
                        className="mt-1 h-4 w-4 rounded border-line text-ink"
                    />
                    <span>
                        <span className="block font-medium text-ink">Je gère des événements pour des clients</span>
                        <span className="mt-1 block text-sm text-ink-soft">
                            Ouvre « Mes clients » : un compte par client, le total de votre portefeuille, et la possibilité
                            de rendre un compte à son client quand il vous quitte.
                        </span>
                    </span>
                </label>

                {isAgency && (
                    <label className="mt-3 flex cursor-pointer items-start gap-3 rounded-card bg-bg p-5 ring-1 ring-line">
                        <input
                            type="checkbox"
                            checked={brandsClientPortals}
                            onChange={(event) => setBrandsClientPortals(event.target.checked)}
                            className="mt-1 h-4 w-4 rounded border-line text-ink"
                        />
                        <span>
                            <span className="block font-medium text-ink">Mettre ma marque sur les portails de mes clients</span>
                            <span className="mt-1 block text-sm text-ink-soft">
                                Vos comptes clients verront votre logo à la place de celui d'Itaza, en haut de leur
                                espace. {logoUrl ? '' : 'Déposez d’abord votre logo ci-dessus : sans lui, rien ne change.'}
                            </span>
                        </span>
                    </label>
                )}
            </div>

            <Button className="w-auto" onClick={() => void handleSave()} disabled={saving}>
                {saving ? 'Enregistrement…' : 'Enregistrer'}
            </Button>
        </SettingsLayout>
    );
}
