import { Head, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import TextInput from '../../Components/TextInput';
import Textarea from '../../Components/Textarea';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

type BlockType = 'heading' | 'text' | 'image' | 'button' | 'divider' | 'spacer';

interface Block {
    type: BlockType;
    text?: string;
    html?: string;
    url?: string;
    alt?: string;
    height?: number;
}

interface TemplateDraft {
    id: number;
    name: string;
    subject: string;
    blocks: Block[];
}

interface Option {
    id: number;
    label: string;
}

const BLOCK_LABELS: Record<BlockType, string> = {
    heading: 'Titre',
    text: 'Texte',
    image: 'Image',
    button: 'Bouton',
    divider: 'Séparateur',
    spacer: 'Espace',
};

function defaultBlock(type: BlockType): Block {
    return {
        heading: { type, text: 'Bonjour {{first_name}}' },
        text: { type, html: '<p>Votre texte ici, {{first_name}}.</p>' },
        image: { type, url: 'https://', alt: '' },
        button: { type, text: 'Répondre', url: '{{rsvp_link}}' },
        divider: { type },
        spacer: { type, height: 24 },
    }[type];
}

function BlockEditor({ block, onChange }: { block: Block; onChange: (block: Block) => void }) {
    switch (block.type) {
        case 'heading':
            return <TextInput value={block.text ?? ''} onChange={(e) => onChange({ ...block, text: e.target.value })} placeholder="Titre" />;
        case 'text':
            return (
                <Textarea
                    value={block.html ?? ''}
                    onChange={(e) => onChange({ ...block, html: e.target.value })}
                    placeholder="<p>Votre texte…</p>"
                />
            );
        case 'image':
            return (
                <div className="space-y-2">
                    <TextInput
                        value={block.url ?? ''}
                        onChange={(e) => onChange({ ...block, url: e.target.value })}
                        placeholder="URL de l'image"
                    />
                    <TextInput
                        value={block.alt ?? ''}
                        onChange={(e) => onChange({ ...block, alt: e.target.value })}
                        placeholder="Texte alternatif"
                    />
                </div>
            );
        case 'button':
            return (
                <div className="space-y-2">
                    <TextInput
                        value={block.text ?? ''}
                        onChange={(e) => onChange({ ...block, text: e.target.value })}
                        placeholder="Texte du bouton"
                    />
                    <TextInput value={block.url ?? ''} onChange={(e) => onChange({ ...block, url: e.target.value })} placeholder="Lien" />
                </div>
            );
        case 'spacer':
            return (
                <TextInput
                    type="number"
                    min={0}
                    max={400}
                    value={block.height ?? 24}
                    onChange={(e) => onChange({ ...block, height: Number(e.target.value) })}
                />
            );
        case 'divider':
            return <p className="text-sm text-ink-soft">Une ligne de séparation, sans réglage.</p>;
    }
}

export default function Editor({
    template,
    contacts,
    events,
}: {
    template: TemplateDraft | null;
    contacts: Option[];
    events: Option[];
}) {
    const { data, setData, post, patch, processing, errors } = useForm<{ name: string; subject: string; blocks: Block[] }>({
        name: template?.name ?? '',
        subject: template?.subject ?? '',
        blocks: template?.blocks ?? [],
    });

    const [previewContactId, setPreviewContactId] = useState<number | ''>(contacts[0]?.id ?? '');
    const [previewEventId, setPreviewEventId] = useState<number | ''>('');
    const [preview, setPreview] = useState<{ subject: string; html: string } | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [testEmail, setTestEmail] = useState('');
    const [sendingTest, setSendingTest] = useState(false);
    const [testSent, setTestSent] = useState(false);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        if (template) {
            patch(`/email-templates/${template.id}`);
        } else {
            post('/email-templates');
        }
    }

    function addBlock(type: BlockType) {
        setData('blocks', [...data.blocks, defaultBlock(type)]);
    }

    function updateBlock(index: number, block: Block) {
        setData(
            'blocks',
            data.blocks.map((b, i) => (i === index ? block : b)),
        );
    }

    function removeBlock(index: number) {
        setData(
            'blocks',
            data.blocks.filter((_, i) => i !== index),
        );
    }

    function moveBlock(index: number, direction: -1 | 1) {
        const target = index + direction;
        if (target < 0 || target >= data.blocks.length) {
            return;
        }

        const next = [...data.blocks];
        [next[index], next[target]] = [next[target], next[index]];
        setData('blocks', next);
    }

    async function runPreview() {
        if (!template || !previewContactId) {
            return;
        }

        setPreviewing(true);
        try {
            const params = new URLSearchParams({ contact_id: String(previewContactId) });
            if (previewEventId) {
                params.set('event_id', String(previewEventId));
            }
            const response = await window.axios.get(`/email-templates/${template.id}/preview?${params.toString()}`);
            setPreview(response.data);
        } finally {
            setPreviewing(false);
        }
    }

    async function sendTest() {
        if (!template || !previewContactId || !testEmail) {
            return;
        }

        setSendingTest(true);
        setTestSent(false);
        try {
            await window.axios.post(`/email-templates/${template.id}/test-send`, {
                contact_id: previewContactId,
                event_id: previewEventId || null,
                to_email: testEmail,
            });
            setTestSent(true);
        } finally {
            setSendingTest(false);
        }
    }

    return (
        <OrganizerLayout title={template ? template.name : 'Nouveau modèle'} eyebrow="Communications">
            <Head title={template ? 'Modifier le modèle' : 'Nouveau modèle'} />

            <div className="grid gap-10 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <form onSubmit={handleSubmit}>
                    <div className="mb-5">
                        <InputLabel htmlFor="name">Nom du modèle</InputLabel>
                        <TextInput id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="mb-8">
                        <InputLabel htmlFor="subject">Objet</InputLabel>
                        <TextInput id="subject" value={data.subject} onChange={(e) => setData('subject', e.target.value)} />
                        <InputError message={errors.subject} />
                    </div>

                    <h2 className="mb-3 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Ajouter un bloc</h2>
                    <div className="mb-8 flex flex-wrap gap-2">
                        {(Object.keys(BLOCK_LABELS) as BlockType[]).map((type) => (
                            <button
                                key={type}
                                type="button"
                                onClick={() => addBlock(type)}
                                className="rounded-pill border border-line px-4 py-2 text-sm text-ink hover:border-ink"
                            >
                                + {BLOCK_LABELS[type]}
                            </button>
                        ))}
                    </div>

                    <div className="space-y-4">
                        {data.blocks.length === 0 && <p className="text-sm text-ink-soft">Aucun bloc pour l'instant — ajoutez-en un ci-dessus.</p>}
                        {data.blocks.map((block, index) => (
                            <div key={index} className="rounded-card border border-line p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">{BLOCK_LABELS[block.type]}</span>
                                    <div className="flex items-center gap-3 text-sm text-ink-soft">
                                        <button type="button" onClick={() => moveBlock(index, -1)} disabled={index === 0} className="disabled:opacity-30">
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => moveBlock(index, 1)}
                                            disabled={index === data.blocks.length - 1}
                                            className="disabled:opacity-30"
                                        >
                                            ↓
                                        </button>
                                        <button type="button" onClick={() => removeBlock(index)} className="text-danger hover:opacity-80">
                                            Retirer
                                        </button>
                                    </div>
                                </div>
                                <BlockEditor block={block} onChange={(next) => updateBlock(index, next)} />
                            </div>
                        ))}
                    </div>

                    <div className="mt-8">
                        <Button type="submit" disabled={processing}>
                            {template ? 'Enregistrer' : 'Créer le modèle'}
                        </Button>
                    </div>
                </form>

                {template && (
                    <div>
                        <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Aperçu et test</h2>

                        <div className="mb-4">
                            <InputLabel htmlFor="preview_contact">Destinataire (données réelles)</InputLabel>
                            <Select
                                id="preview_contact"
                                value={previewContactId}
                                onChange={(e) => setPreviewContactId(e.target.value ? Number(e.target.value) : '')}
                            >
                                {contacts.map((contact) => (
                                    <option key={contact.id} value={contact.id}>
                                        {contact.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div className="mb-4">
                            <InputLabel htmlFor="preview_event">Événement (optionnel)</InputLabel>
                            <Select
                                id="preview_event"
                                value={previewEventId}
                                onChange={(e) => setPreviewEventId(e.target.value ? Number(e.target.value) : '')}
                            >
                                <option value="">—</option>
                                {events.map((event) => (
                                    <option key={event.id} value={event.id}>
                                        {event.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <Button type="button" variant="secondary" onClick={runPreview} disabled={previewing || !previewContactId} className="mb-6">
                            Aperçu
                        </Button>

                        {preview && (
                            <div className="mb-6 overflow-hidden rounded-card border border-line">
                                <p className="border-b border-line bg-bg-alt px-4 py-2 text-sm text-ink-soft">{preview.subject}</p>
                                <iframe title="Aperçu de l'e-mail" srcDoc={preview.html} className="h-96 w-full" />
                            </div>
                        )}

                        <div className="border-t border-line pt-6">
                            <InputLabel htmlFor="test_email">Envoyer un test à</InputLabel>
                            <TextInput
                                id="test_email"
                                type="email"
                                value={testEmail}
                                onChange={(e) => setTestEmail(e.target.value)}
                                placeholder="toi@example.org"
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={sendTest}
                                disabled={sendingTest || !previewContactId || !testEmail}
                                className="mt-3"
                            >
                                Envoyer un test
                            </Button>
                            {testSent && <p className="mt-2 text-sm text-success">Test envoyé.</p>}
                        </div>
                    </div>
                )}
            </div>
        </OrganizerLayout>
    );
}
