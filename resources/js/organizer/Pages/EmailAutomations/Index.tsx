import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import Table from '../../Components/Table';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface AutomationRow {
    id: number;
    type: string;
    type_label: string;
    template_name: string;
    segment: string | null;
    status: string;
    scheduled_at: string | null;
    sent_at: string | null;
}

interface TypeOption {
    value: string;
    label: string;
}

interface TemplateOption {
    id: number;
    name: string;
}

const STATUS_LABELS: Record<string, string> = {
    active: 'Active',
    scheduled: 'Planifiée',
    sent: 'Envoyée',
    cancelled: 'Annulée',
};

const SEGMENT_LABELS: Record<string, string> = {
    sans_reponse: 'Sans réponse',
    confirmes: 'Confirmés',
    declines: 'Déclinés',
    presents: 'Présents',
    no_show: 'No-show',
};

// Ces deux types sont les seuls où l'organisateur choisit lui-même
// l'échéance — les autres la calculent depuis les dates de l'événement
// (rappel J-7/J-1, remerciement) ou n'en ont pas (confirmation).
const TYPES_NEEDING_SCHEDULE = ['invitation', 'reminder_unanswered'];

export default function Index({
    event,
    automations,
    types,
    templates,
}: {
    event: { id: number; title: string };
    automations: AutomationRow[];
    types: TypeOption[];
    templates: TemplateOption[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email_template_id: templates[0]?.id ?? '',
        type: types[0]?.value ?? '',
        scheduled_at: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(`/events/${event.id}/automations`, { onSuccess: () => reset('scheduled_at') });
    }

    function cancelAutomation(automation: AutomationRow) {
        if (confirm(`Annuler l'automatisation « ${automation.type_label} » ?`)) {
            router.post(`/email-automations/${automation.id}/cancel`);
        }
    }

    return (
        <OrganizerLayout title="Automatisations" eyebrow={event.title}>
            <Head title="Automatisations" />

            <div className="mb-8 flex justify-end">
                <Link href={`/events/${event.id}/segments`} className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase hover:text-ink">
                    Segments
                </Link>
            </div>

            <form onSubmit={submit} className="mb-10 flex flex-wrap items-end gap-4 rounded-card border border-line bg-bg p-4">
                <div>
                    <InputLabel htmlFor="type">Type</InputLabel>
                    <Select id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        {types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </Select>
                </div>

                <div>
                    <InputLabel htmlFor="email_template_id">Modèle</InputLabel>
                    <Select id="email_template_id" value={data.email_template_id} onChange={(e) => setData('email_template_id', Number(e.target.value))}>
                        {templates.map((template) => (
                            <option key={template.id} value={template.id}>
                                {template.name}
                            </option>
                        ))}
                    </Select>
                </div>

                {TYPES_NEEDING_SCHEDULE.includes(data.type) && (
                    <div>
                        <InputLabel htmlFor="scheduled_at">Envoyer le</InputLabel>
                        <TextInput
                            id="scheduled_at"
                            type="datetime-local"
                            value={data.scheduled_at}
                            onChange={(e) => setData('scheduled_at', e.target.value)}
                        />
                    </div>
                )}

                <Button type="submit" disabled={processing || templates.length === 0} className="w-auto">
                    Ajouter
                </Button>
                {errors.type && <p className="w-full text-sm text-danger">{errors.type}</p>}
            </form>

            {templates.length === 0 && (
                <p className="mb-6 text-sm text-ink-soft">
                    Aucun modèle d'e-mail créé pour l'instant —{' '}
                    <Link href="/email-templates/create" className="underline underline-offset-2">
                        crée un modèle
                    </Link>{' '}
                    avant de configurer une automatisation.
                </p>
            )}

            <Table
                rowKey={(automation) => automation.id}
                emptyMessage="Aucune automatisation configurée pour l'instant."
                columns={[
                    { key: 'type', header: 'Type', render: (automation) => automation.type_label },
                    { key: 'template', header: 'Modèle', render: (automation) => automation.template_name },
                    {
                        key: 'segment',
                        header: 'Cible',
                        render: (automation) => (automation.segment ? (SEGMENT_LABELS[automation.segment] ?? automation.segment) : 'Tous les contacts'),
                    },
                    {
                        key: 'status',
                        header: 'Statut',
                        render: (automation) => (
                            <Badge variant={automation.status === 'cancelled' ? 'danger' : automation.status === 'sent' ? 'success' : 'neutral'}>
                                {STATUS_LABELS[automation.status] ?? automation.status}
                            </Badge>
                        ),
                    },
                    {
                        key: 'when',
                        header: 'Échéance',
                        render: (automation) =>
                            automation.sent_at
                                ? `Envoyée le ${new Date(automation.sent_at).toLocaleString('fr-FR')}`
                                : automation.scheduled_at
                                  ? new Date(automation.scheduled_at).toLocaleString('fr-FR')
                                  : '—',
                    },
                    {
                        key: 'actions',
                        header: '',
                        render: (automation) =>
                            ['scheduled', 'active'].includes(automation.status) ? (
                                <button type="button" onClick={() => cancelAutomation(automation)} className="text-sm text-danger hover:opacity-80">
                                    Annuler
                                </button>
                            ) : null,
                    },
                ]}
                rows={automations}
            />
        </OrganizerLayout>
    );
}
