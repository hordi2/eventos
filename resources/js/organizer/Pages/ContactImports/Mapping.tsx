import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface ImportDraft {
    id: number;
    original_filename: string;
    headers: string[];
    column_mapping: Record<string, string | null>;
}

const STRATEGIES = [
    { value: 'merge', label: 'Fusionner', description: 'Complète les contacts existants sans écraser ce qui est déjà renseigné.' },
    { value: 'skip', label: 'Ignorer', description: 'Laisse les contacts existants inchangés, la ligne est simplement notée.' },
    { value: 'create_new', label: 'Créer', description: 'Crée toujours un nouveau contact, même en cas de doublon probable.' },
];

export default function Mapping({
    import: contactImport,
    preview,
    mappableFields,
}: {
    import: ImportDraft;
    preview: Record<string, string>[];
    mappableFields: Record<string, string>;
}) {
    const { data, setData, post, processing } = useForm({
        mapping: contactImport.column_mapping,
        duplicate_strategy: 'merge',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(`/contact-imports/${contactImport.id}/mapping`);
    }

    return (
        <OrganizerLayout title="Faire correspondre les colonnes" eyebrow={contactImport.original_filename}>
            <Head title="Faire correspondre les colonnes" />

            <form onSubmit={handleSubmit}>
                <div className="mb-10 overflow-x-auto rounded-card ring-1 ring-line">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-line font-label text-xs tracking-[0.1em] text-ink-soft uppercase">
                                {contactImport.headers.map((header) => (
                                    <th key={header} className="min-w-[180px] px-4 py-4 font-medium">
                                        <p className="mb-2 normal-case">{header}</p>
                                        <Select
                                            value={data.mapping[header] ?? ''}
                                            onChange={(e) =>
                                                setData('mapping', { ...data.mapping, [header]: e.target.value || null })
                                            }
                                        >
                                            <option value="">— Ignorer —</option>
                                            {Object.entries(mappableFields).map(([field, label]) => (
                                                <option key={field} value={field}>
                                                    {label}
                                                </option>
                                            ))}
                                        </Select>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {preview.map((row, index) => (
                                <tr key={index} className="border-b border-line last:border-0">
                                    {contactImport.headers.map((header) => (
                                        <td key={header} className="px-4 py-3 text-ink-soft">
                                            {row[header] || '—'}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">En cas de doublon probable</h2>

                <div className="mb-10 space-y-3">
                    {STRATEGIES.map((strategy) => (
                        <label
                            key={strategy.value}
                            className={`flex cursor-pointer items-start gap-3 rounded-card border px-4 py-3 ${
                                data.duplicate_strategy === strategy.value ? 'border-accent' : 'border-line'
                            }`}
                        >
                            <input
                                type="radio"
                                name="duplicate_strategy"
                                value={strategy.value}
                                checked={data.duplicate_strategy === strategy.value}
                                onChange={(e) => setData('duplicate_strategy', e.target.value)}
                                className="mt-1"
                            />
                            <span>
                                <span className="block text-sm text-ink">{strategy.label}</span>
                                <span className="block text-xs text-ink-soft">{strategy.description}</span>
                            </span>
                        </label>
                    ))}
                </div>

                <InputLabel className="mb-4 block normal-case">
                    Un score de similarité (nom complet ou e-mail identique) détecte les doublons probables automatiquement.
                </InputLabel>

                <Button type="submit" disabled={processing}>
                    Lancer l'import
                </Button>
            </form>
        </OrganizerLayout>
    );
}
