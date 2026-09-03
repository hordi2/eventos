import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Modal from '../../Components/Modal';
import Select from '../../Components/Select';
import Table from '../../Components/Table';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface TemplateRow {
    id: number;
    name: string;
    provider_template_sid: string;
    language: string;
    category: string | null;
    variable_mapping: string[];
    updated_at: string;
}

interface Option {
    id: number;
    label: string;
}

function TemplateForm({ template, onDone }: { template: TemplateRow | null; onDone: () => void }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: template?.name ?? '',
        provider_template_sid: template?.provider_template_sid ?? '',
        language: template?.language ?? 'fr',
        category: template?.category ?? '',
        variable_mapping: template?.variable_mapping ?? [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (template) {
            patch(`/whatsapp-templates/${template.id}`, { onSuccess: onDone });
        } else {
            post('/whatsapp-templates', { onSuccess: onDone });
        }
    }

    function updateVariable(index: number, value: string) {
        setData(
            'variable_mapping',
            data.variable_mapping.map((v, i) => (i === index ? value : v)),
        );
    }

    return (
        <form onSubmit={submit}>
            <div className="mb-5">
                <InputLabel htmlFor="name">Nom (usage interne)</InputLabel>
                <TextInput id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus />
                <InputError message={errors.name} />
            </div>

            <div className="mb-5">
                <InputLabel htmlFor="provider_template_sid">Content SID Twilio</InputLabel>
                <TextInput
                    id="provider_template_sid"
                    value={data.provider_template_sid}
                    onChange={(e) => setData('provider_template_sid', e.target.value)}
                    placeholder="HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                />
                <InputError message={errors.provider_template_sid} />
                <p className="mt-1.5 text-xs text-ink-soft">Le modèle doit déjà être approuvé côté Twilio — rien n'est créé depuis Itaza.</p>
            </div>

            <div className="mb-5 grid grid-cols-2 gap-4">
                <div>
                    <InputLabel htmlFor="language">Langue</InputLabel>
                    <TextInput id="language" value={data.language} onChange={(e) => setData('language', e.target.value)} />
                </div>
                <div>
                    <InputLabel htmlFor="category">Catégorie (informatif)</InputLabel>
                    <TextInput
                        id="category"
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                        placeholder="marketing, utility…"
                    />
                </div>
            </div>

            <div className="mb-8">
                <InputLabel>Variables du modèle, dans l'ordre ({'{{1}}'}, {'{{2}}'}…)</InputLabel>
                <div className="space-y-2">
                    {data.variable_mapping.map((variable, index) => (
                        <div key={index} className="flex items-center gap-2">
                            <span className="w-6 shrink-0 text-sm text-ink-soft">{`{{${index + 1}}}`}</span>
                            <TextInput
                                value={variable}
                                onChange={(e) => updateVariable(index, e.target.value)}
                                placeholder="first_name, event_date, custom_fields.badge…"
                            />
                            <button
                                type="button"
                                onClick={() => setData('variable_mapping', data.variable_mapping.filter((_, i) => i !== index))}
                                className="shrink-0 text-sm text-danger hover:opacity-80"
                            >
                                Retirer
                            </button>
                        </div>
                    ))}
                </div>
                <button
                    type="button"
                    onClick={() => setData('variable_mapping', [...data.variable_mapping, ''])}
                    className="mt-3 rounded-pill border border-line px-4 py-2 text-sm text-ink hover:border-ink"
                >
                    + Ajouter une variable
                </button>
                <InputError message={errors.variable_mapping} />
            </div>

            <Button type="submit" disabled={processing}>
                {template ? 'Enregistrer' : 'Déclarer le modèle'}
            </Button>
        </form>
    );
}

function PreviewAndTest({ templates, contacts, events }: { templates: TemplateRow[]; contacts: Option[]; events: Option[] }) {
    const [templateId, setTemplateId] = useState<number | ''>(templates[0]?.id ?? '');
    const [contactId, setContactId] = useState<number | ''>(contacts[0]?.id ?? '');
    const [eventId, setEventId] = useState<number | ''>('');
    const [preview, setPreview] = useState<Record<string, string> | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [toPhone, setToPhone] = useState('');
    const [sendingTest, setSendingTest] = useState(false);
    const [testSent, setTestSent] = useState(false);

    async function runPreview() {
        if (!templateId || !contactId) {
            return;
        }

        setPreviewing(true);
        try {
            const params = new URLSearchParams({ contact_id: String(contactId) });
            if (eventId) {
                params.set('event_id', String(eventId));
            }
            const response = await window.axios.get(`/whatsapp-templates/${templateId}/preview?${params.toString()}`);
            setPreview(response.data.content_variables);
        } finally {
            setPreviewing(false);
        }
    }

    async function sendTest() {
        if (!templateId || !contactId || !toPhone) {
            return;
        }

        setSendingTest(true);
        setTestSent(false);
        try {
            await window.axios.post(`/whatsapp-templates/${templateId}/test-send`, {
                contact_id: contactId,
                event_id: eventId || null,
                to_phone_e164: toPhone,
            });
            setTestSent(true);
        } finally {
            setSendingTest(false);
        }
    }

    if (templates.length === 0) {
        return null;
    }

    return (
        <div className="mt-10 rounded-card border border-line bg-bg p-6">
            <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Aperçu et test</h2>

            <div className="mb-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel htmlFor="preview_template">Modèle</InputLabel>
                    <Select id="preview_template" value={templateId} onChange={(e) => setTemplateId(Number(e.target.value))}>
                        {templates.map((template) => (
                            <option key={template.id} value={template.id}>
                                {template.name}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <InputLabel htmlFor="preview_contact">Destinataire (données réelles)</InputLabel>
                    <Select id="preview_contact" value={contactId} onChange={(e) => setContactId(e.target.value ? Number(e.target.value) : '')}>
                        {contacts.map((contact) => (
                            <option key={contact.id} value={contact.id}>
                                {contact.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <InputLabel htmlFor="preview_event">Événement (optionnel)</InputLabel>
                    <Select id="preview_event" value={eventId} onChange={(e) => setEventId(e.target.value ? Number(e.target.value) : '')}>
                        <option value="">—</option>
                        {events.map((event) => (
                            <option key={event.id} value={event.id}>
                                {event.label}
                            </option>
                        ))}
                    </Select>
                </div>
            </div>

            <Button type="button" variant="secondary" onClick={runPreview} disabled={previewing || !templateId || !contactId} className="mb-4 w-auto">
                Aperçu des variables
            </Button>

            {preview && (
                <div className="mb-6 rounded-card border border-line bg-bg-alt p-4 text-sm">
                    {Object.entries(preview).map(([position, value]) => (
                        <p key={position}>
                            <span className="text-ink-soft">{`{{${position}}} = `}</span>
                            <span className="text-ink">{value}</span>
                        </p>
                    ))}
                </div>
            )}

            <div className="border-t border-line pt-6">
                <InputLabel htmlFor="test_phone">Envoyer un test au numéro</InputLabel>
                <TextInput id="test_phone" value={toPhone} onChange={(e) => setToPhone(e.target.value)} placeholder="+243899999999" />
                <Button
                    type="button"
                    variant="secondary"
                    onClick={sendTest}
                    disabled={sendingTest || !templateId || !contactId || !toPhone}
                    className="mt-3 w-auto"
                >
                    Envoyer un test
                </Button>
                {testSent && <p className="mt-2 text-sm text-success">Test envoyé.</p>}
            </div>
        </div>
    );
}

export default function Index({ templates, contacts, events }: { templates: TemplateRow[]; contacts: Option[]; events: Option[] }) {
    const [editing, setEditing] = useState<TemplateRow | null | undefined>(undefined);

    function destroy(template: TemplateRow) {
        if (confirm(`Supprimer le modèle « ${template.name} » ?`)) {
            router.delete(`/whatsapp-templates/${template.id}`);
        }
    }

    return (
        <OrganizerLayout title="Modèles WhatsApp" eyebrow="Communications">
            <Head title="Modèles WhatsApp" />

            <p className="mb-6 max-w-lg text-sm text-ink-soft">
                Un modèle doit déjà être approuvé côté Twilio (Meta) avant d'être déclaré ici — Itaza ne crée ni ne soumet aucun modèle.
            </p>

            <div className="mb-6 flex justify-end">
                <Button className="w-auto" onClick={() => setEditing(null)}>
                    Déclarer un modèle
                </Button>
            </div>

            <Table
                rowKey={(template) => template.id}
                emptyMessage="Aucun modèle WhatsApp pour l'instant."
                columns={[
                    { key: 'name', header: 'Nom', render: (template) => template.name },
                    { key: 'sid', header: 'Content SID', render: (template) => <code className="text-xs">{template.provider_template_sid}</code> },
                    { key: 'language', header: 'Langue', render: (template) => template.language },
                    {
                        key: 'category',
                        header: 'Catégorie',
                        render: (template) => (template.category ? <Badge>{template.category}</Badge> : '—'),
                    },
                    {
                        key: 'actions',
                        header: '',
                        render: (template) => (
                            <div className="flex justify-end gap-4">
                                <button type="button" onClick={() => setEditing(template)} className="text-sm text-ink-soft hover:text-ink">
                                    Modifier
                                </button>
                                <button type="button" onClick={() => destroy(template)} className="text-sm text-danger hover:opacity-80">
                                    Supprimer
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={templates}
            />

            <PreviewAndTest templates={templates} contacts={contacts} events={events} />

            <Modal open={editing !== undefined} onClose={() => setEditing(undefined)} title={editing ? 'Modifier le modèle' : 'Déclarer un modèle'}>
                <TemplateForm template={editing ?? null} onDone={() => setEditing(undefined)} />
            </Modal>
        </OrganizerLayout>
    );
}
