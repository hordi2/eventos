import { Head } from '@inertiajs/react';
import { type ChangeEvent, useRef, useState } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import SettingsLayout from '../../Layouts/SettingsLayout';

interface Props {
    branding: {
        logo_url: string | null;
        primary_color: string | null;
    };
}

const DEFAULT_COLOR = '#1b1611';

export default function Branding({ branding }: Props) {
    const [logoUrl, setLogoUrl] = useState(branding.logo_url);
    const [primaryColor, setPrimaryColor] = useState(branding.primary_color ?? DEFAULT_COLOR);
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

    async function handleColorSave() {
        setSaving(true);

        try {
            await window.axios.patch('/organization/branding', { primary_color: primaryColor });
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

            <Button className="w-auto" onClick={() => void handleColorSave()} disabled={saving}>
                {saving ? 'Enregistrement…' : 'Enregistrer'}
            </Button>
        </SettingsLayout>
    );
}
