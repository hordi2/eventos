import BuilderIcon from './BuilderIcon';
import { PANEL_SECTION_TITLE } from './logic';
import { type FieldOptionData } from './types';

interface OptionsEditorProps {
    options: FieldOptionData[];
    withQuota: boolean;
    onChange: (options: FieldOptionData[]) => void;
    error?: string;
}

export default function OptionsEditor({ options, withQuota, onChange, error }: OptionsEditorProps) {
    function update(index: number, patch: Partial<FieldOptionData>) {
        onChange(options.map((option, position) => (position === index ? { ...option, ...patch } : option)));
    }

    return (
        <fieldset>
            <legend className={PANEL_SECTION_TITLE}>Options</legend>
            {withQuota && (
                <p className="mb-3 text-xs text-ink-soft">Quota facultatif : l'option se ferme une fois atteint (« 50 places pour l'atelier A »).</p>
            )}
            <ul className="space-y-2">
                {options.map((option, index) => (
                    <li key={index} className="flex items-center gap-2">
                        <input
                            type="text"
                            aria-label={`Option ${index + 1}`}
                            value={option.label}
                            maxLength={255}
                            onChange={(event) => update(index, { label: event.target.value })}
                            className="min-w-0 flex-1 rounded-control border border-line bg-transparent px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
                        />
                        {withQuota && (
                            <input
                                type="number"
                                min={0}
                                aria-label={`Quota de l'option ${index + 1}`}
                                placeholder="Quota"
                                value={option.quota ?? ''}
                                onChange={(event) => update(index, { quota: event.target.value === '' ? null : Number(event.target.value) })}
                                className="w-20 rounded-control border border-line bg-transparent px-2 py-2 text-sm text-ink focus:border-accent focus:outline-none"
                            />
                        )}
                        <button
                            type="button"
                            onClick={() => onChange(options.filter((_, position) => position !== index))}
                            disabled={options.length <= 1}
                            aria-label={`Retirer l'option ${index + 1}`}
                            className="p-1 text-ink-soft hover:text-danger disabled:opacity-30"
                        >
                            <BuilderIcon name="close" className="h-4 w-4" />
                        </button>
                    </li>
                ))}
            </ul>
            <button
                type="button"
                onClick={() => onChange([...options, { value: '', label: `Option ${options.length + 1}`, quota: null }])}
                className="mt-3 flex items-center gap-1.5 text-sm font-medium text-ink hover:underline"
            >
                <BuilderIcon name="plus" className="h-4 w-4" />
                Ajouter une option
            </button>
            {error && <p className="mt-2 text-sm text-danger">{error}</p>}
        </fieldset>
    );
}
