import { Head } from '@inertiajs/react';
import { type ChangeEvent, useRef, useState } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import Textarea from '../../Components/Textarea';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface ProgramItem {
    time: string;
    title: string;
    description: string;
}

interface FaqItem {
    question: string;
    answer: string;
}

interface Props {
    event: { id: number; title: string; slug: string };
    publicUrl: string;
    page: {
        banner_url: string | null;
        meta_description: string | null;
        program_items: ProgramItem[];
        faq_items: FaqItem[];
    };
}

export default function Edit({ event, publicUrl, page }: Props) {
    const [bannerUrl, setBannerUrl] = useState(page.banner_url);
    const [metaDescription, setMetaDescription] = useState(page.meta_description ?? '');
    const [programItems, setProgramItems] = useState<ProgramItem[]>(page.program_items);
    const [faqItems, setFaqItems] = useState<FaqItem[]>(page.faq_items);
    const [uploading, setUploading] = useState(false);
    const [saving, setSaving] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    async function handleBannerChange(changeEvent: ChangeEvent<HTMLInputElement>) {
        const file = changeEvent.target.files?.[0];

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('banner', file);

        setUploading(true);

        try {
            const response = await window.axios.post<{ banner_url: string }>(`/events/${event.id}/page/banner`, formData);
            setBannerUrl(response.data.banner_url);
        } finally {
            setUploading(false);
        }
    }

    function addProgramItem() {
        setProgramItems((current) => [...current, { time: '', title: '', description: '' }]);
    }

    function updateProgramItem(index: number, field: keyof ProgramItem, value: string) {
        setProgramItems((current) => current.map((item, i) => (i === index ? { ...item, [field]: value } : item)));
    }

    function removeProgramItem(index: number) {
        setProgramItems((current) => current.filter((_, i) => i !== index));
    }

    function addFaqItem() {
        setFaqItems((current) => [...current, { question: '', answer: '' }]);
    }

    function updateFaqItem(index: number, field: keyof FaqItem, value: string) {
        setFaqItems((current) => current.map((item, i) => (i === index ? { ...item, [field]: value } : item)));
    }

    function removeFaqItem(index: number) {
        setFaqItems((current) => current.filter((_, i) => i !== index));
    }

    async function handleSave() {
        setSaving(true);

        try {
            await window.axios.patch(`/events/${event.id}/page`, {
                meta_description: metaDescription || null,
                program_items: programItems,
                faq_items: faqItems,
            });
        } finally {
            setSaving(false);
        }
    }

    return (
        <OrganizerLayout title="Page événement" eyebrow={event.title}>
            <Head title="Page événement" />

            <div className="mb-8 flex items-center justify-between">
                <h1 className="text-2xl">Page événement publique</h1>
                <a href={publicUrl} target="_blank" rel="noreferrer" className="text-sm text-accent underline underline-offset-2">
                    Voir la page publique
                </a>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Bannière</h2>
                {bannerUrl !== null && <img src={bannerUrl} alt="Bannière" className="mb-4 aspect-[2/1] w-full max-w-md rounded-card object-cover" />}
                <InputLabel htmlFor="banner">{bannerUrl !== null ? 'Remplacer la bannière' : 'Téléverser une bannière'}</InputLabel>
                <input
                    id="banner"
                    ref={fileInputRef}
                    type="file"
                    accept="image/png,image/jpeg"
                    onChange={(e) => void handleBannerChange(e)}
                    disabled={uploading}
                    className="text-sm text-ink-soft"
                />
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Référencement</h2>
                <InputLabel htmlFor="meta-description">Méta-description (155 caractères max recommandé)</InputLabel>
                <Textarea
                    id="meta-description"
                    maxLength={160}
                    value={metaDescription}
                    onChange={(e) => setMetaDescription(e.target.value)}
                    placeholder="Décrit l'événement pour les moteurs de recherche et les partages sur les réseaux sociaux."
                />
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-serif text-lg italic">Programme</h2>
                    <Button variant="secondary" className="w-auto" onClick={addProgramItem}>
                        Ajouter une étape
                    </Button>
                </div>
                <div className="space-y-3">
                    {programItems.map((item, index) => (
                        <div key={index} className="flex flex-wrap items-start gap-2 rounded bg-bg-deep p-3">
                            <TextInput
                                className="w-24"
                                placeholder="09h00"
                                value={item.time}
                                onChange={(e) => updateProgramItem(index, 'time', e.target.value)}
                            />
                            <TextInput
                                className="flex-1"
                                placeholder="Titre"
                                value={item.title}
                                onChange={(e) => updateProgramItem(index, 'title', e.target.value)}
                            />
                            <TextInput
                                className="flex-1"
                                placeholder="Description (optionnelle)"
                                value={item.description}
                                onChange={(e) => updateProgramItem(index, 'description', e.target.value)}
                            />
                            <button type="button" onClick={() => removeProgramItem(index)} className="text-sm text-danger">
                                Retirer
                            </button>
                        </div>
                    ))}
                    {programItems.length === 0 && <p className="text-sm text-ink-soft">Aucune étape pour l'instant.</p>}
                </div>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-serif text-lg italic">Questions fréquentes</h2>
                    <Button variant="secondary" className="w-auto" onClick={addFaqItem}>
                        Ajouter une question
                    </Button>
                </div>
                <div className="space-y-3">
                    {faqItems.map((item, index) => (
                        <div key={index} className="space-y-2 rounded bg-bg-deep p-3">
                            <TextInput placeholder="Question" value={item.question} onChange={(e) => updateFaqItem(index, 'question', e.target.value)} />
                            <Textarea placeholder="Réponse" value={item.answer} onChange={(e) => updateFaqItem(index, 'answer', e.target.value)} />
                            <button type="button" onClick={() => removeFaqItem(index)} className="text-sm text-danger">
                                Retirer
                            </button>
                        </div>
                    ))}
                    {faqItems.length === 0 && <p className="text-sm text-ink-soft">Aucune question pour l'instant.</p>}
                </div>
            </div>

            <Button className="w-auto" onClick={() => void handleSave()} disabled={saving}>
                {saving ? 'Enregistrement…' : 'Enregistrer'}
            </Button>
        </OrganizerLayout>
    );
}
