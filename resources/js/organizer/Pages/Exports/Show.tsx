import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface ExportType {
    value: string;
    label: string;
    columns: Record<string, string>;
}

interface Segment {
    value: string;
    label: string;
}

interface ExportRow {
    id: number;
    type: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    row_count: number | null;
    created_at: string;
    expired: boolean;
}

interface Props {
    event: { id: number; title: string };
    types: ExportType[];
    segments: Segment[];
    exports: ExportRow[];
}

const STATUS_LABELS: Record<ExportRow['status'], string> = {
    pending: 'En attente',
    processing: 'En cours',
    completed: 'Prêt',
    failed: 'Échoué',
};

export default function Show({ event, types, segments, exports: initialExports }: Props) {
    const [selectedType, setSelectedType] = useState(types[0]?.value ?? '');
    const [selectedColumns, setSelectedColumns] = useState<string[]>(Object.keys(types[0]?.columns ?? {}));
    const [selectedSegment, setSelectedSegment] = useState('');
    const [exports, setExports] = useState<ExportRow[]>(initialExports);
    const [requesting, setRequesting] = useState(false);
    const pollingRef = useRef<number | null>(null);

    const currentType = types.find((type) => type.value === selectedType);
    const hasPending = exports.some((item) => item.status === 'pending' || item.status === 'processing');

    useEffect(() => {
        if (!hasPending) {
            return;
        }

        pollingRef.current = window.setInterval(async () => {
            const pending = exports.filter((item) => item.status === 'pending' || item.status === 'processing');

            await Promise.all(
                pending.map(async (item) => {
                    const response = await window.axios.get<ExportRow>(`/events/${event.id}/exports/${item.id}`);
                    setExports((current) => current.map((existing) => (existing.id === item.id ? { ...existing, ...response.data } : existing)));
                }),
            );
        }, 3000);

        return () => {
            if (pollingRef.current !== null) {
                window.clearInterval(pollingRef.current);
            }
        };
    }, [hasPending, exports, event.id]);

    function handleTypeChange(value: string) {
        setSelectedType(value);
        const type = types.find((t) => t.value === value);
        setSelectedColumns(Object.keys(type?.columns ?? {}));
        setSelectedSegment('');
    }

    function toggleColumn(key: string) {
        setSelectedColumns((current) => (current.includes(key) ? current.filter((c) => c !== key) : [...current, key]));
    }

    async function handleRequestExport() {
        setRequesting(true);

        try {
            const response = await window.axios.post<ExportRow>(`/events/${event.id}/exports`, {
                type: selectedType,
                columns: selectedColumns,
                segment: selectedType === 'contacts' && selectedSegment !== '' ? selectedSegment : undefined,
            });
            setExports((current) => [response.data, ...current]);
        } finally {
            setRequesting(false);
        }
    }

    return (
        <OrganizerLayout title="Exports" eyebrow={event.title}>
            <Head title="Exports" />

            <div className="mb-8">
                <h1 className="text-2xl">Exports</h1>
                <p className="text-ink-soft">Export CSV des invités, inscriptions, commandes et check-ins, en tâche de fond.</p>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <div className="mb-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="export-type">Type</InputLabel>
                        <Select id="export-type" value={selectedType} onChange={(e) => handleTypeChange(e.target.value)}>
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </Select>
                    </div>

                    {selectedType === 'contacts' && (
                        <div>
                            <InputLabel htmlFor="export-segment">Segment (optionnel)</InputLabel>
                            <Select id="export-segment" value={selectedSegment} onChange={(e) => setSelectedSegment(e.target.value)}>
                                <option value="">Tous les invités</option>
                                {segments.map((segment) => (
                                    <option key={segment.value} value={segment.value}>
                                        {segment.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    )}
                </div>

                <div className="mb-4">
                    <InputLabel htmlFor="export-columns">Colonnes</InputLabel>
                    <div id="export-columns" className="flex flex-wrap gap-3">
                        {Object.entries(currentType?.columns ?? {}).map(([key, label]) => (
                            <label key={key} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={selectedColumns.includes(key)} onChange={() => toggleColumn(key)} />
                                {label}
                            </label>
                        ))}
                    </div>
                </div>

                <Button className="w-auto" onClick={() => void handleRequestExport()} disabled={requesting || selectedColumns.length === 0}>
                    {requesting ? 'Lancement…' : 'Lancer l’export'}
                </Button>
            </div>

            <div className="rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Exports récents</h2>

                {exports.length === 0 && <p className="text-sm text-ink-soft">Aucun export pour l'instant.</p>}

                {exports.length > 0 && (
                    <ul className="space-y-2 text-sm">
                        {exports.map((item) => {
                            const typeLabel = types.find((type) => type.value === item.type)?.label ?? item.type;

                            return (
                                <li key={item.id} className="flex items-center justify-between border-b border-line py-2 last:border-0">
                                    <span>
                                        {new Date(item.created_at).toLocaleString('fr-FR')} — {typeLabel} — {STATUS_LABELS[item.status]}
                                        {item.row_count !== null ? ` (${item.row_count} lignes)` : ''}
                                        {item.expired ? ' — expiré' : ''}
                                    </span>
                                    {item.status === 'completed' && !item.expired && (
                                        <a href={`/events/${event.id}/exports/${item.id}/download`} className="text-accent underline underline-offset-2">
                                            Télécharger le CSV
                                        </a>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </OrganizerLayout>
    );
}
