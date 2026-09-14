import { useState } from 'react';
import { HEX_PATTERN } from './logic';

interface ColorFieldProps {
    id: string;
    label: string;
    value: string | null;
    fallback: string;
    onChange: (value: string | null) => void;
}

/**
 * Couleur du thème : sélecteur natif ou saisie #RRGGBB. Vide = couleur par
 * défaut du parcours invité.
 */
export default function ColorField({ id, label, value, fallback, onChange }: ColorFieldProps) {
    const [text, setText] = useState(value ?? '');
    const [syncedValue, setSyncedValue] = useState(value);

    // Resynchronise la saisie quand la couleur change ailleurs (sélecteur,
    // réinitialisation) sans passer par un effet.
    if (value !== syncedValue) {
        setSyncedValue(value);
        setText(value ?? '');
    }

    const invalid = text !== '' && !HEX_PATTERN.test(text);

    return (
        <div className="flex items-center gap-3">
            <input
                type="color"
                aria-label={`${label} : sélecteur de couleur`}
                value={value ?? fallback}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 w-9 shrink-0 cursor-pointer rounded-full border border-line bg-transparent p-0.5"
            />
            <div className="min-w-0 flex-1">
                <label htmlFor={id} className="block text-sm font-medium text-ink">
                    {label}
                </label>
                <input
                    id={id}
                    type="text"
                    value={text}
                    maxLength={7}
                    placeholder={`Par défaut (${fallback})`}
                    aria-invalid={invalid}
                    onChange={(event) => {
                        const next = event.target.value.trim();
                        setText(next);

                        if (next === '') {
                            onChange(null);
                        } else if (HEX_PATTERN.test(next)) {
                            onChange(next.toLowerCase());
                        }
                    }}
                    className="mt-0.5 w-full border-0 border-b border-line bg-transparent py-1 font-mono text-xs text-ink-soft focus:border-accent focus:outline-none"
                />
                {invalid && <p className="mt-1 text-xs text-danger">Format attendu : #RRGGBB</p>}
            </div>
            {value !== null && (
                <button type="button" onClick={() => onChange(null)} className="shrink-0 text-xs text-ink-soft underline hover:text-ink">
                    Par défaut
                </button>
            )}
        </div>
    );
}
