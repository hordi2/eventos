import { type ReactNode } from 'react';
import { formatMoney, optionValue, PREVIEW_INPUT } from './logic';
import { type FieldData } from './types';

interface FieldPreviewProps {
    field: FieldData;
    required: boolean;
    value: unknown;
    onChange: (value: unknown) => void;
}

/**
 * Rendu d'une question tel que l'invité le voit (registration/_field.blade.php),
 * avec des réponses simulées qui font jouer la logique conditionnelle.
 */
export default function FieldPreview({ field, required, value, onChange }: FieldPreviewProps) {
    const text = typeof value === 'string' ? value : '';
    const groupName = `apercu_${field.key}`;
    const selected = Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : [];

    if (field.type === 'informational_text') {
        return (
            <div>
                <p className="text-base whitespace-pre-line">{field.label}</p>
                {field.help_text && <p className="mt-2 text-sm opacity-70">{field.help_text}</p>}
            </div>
        );
    }

    function textInput(type: string, placeholder?: string): ReactNode {
        return (
            <input
                type={type}
                aria-label={field.label}
                placeholder={placeholder}
                value={text}
                onChange={(event) => onChange(event.target.value)}
                className={PREVIEW_INPUT}
            />
        );
    }

    function renderInput(): ReactNode {
        switch (field.type) {
            case 'long_text':
                return <textarea aria-label={field.label} rows={3} value={text} onChange={(event) => onChange(event.target.value)} className={PREVIEW_INPUT} />;
            case 'number':
            case 'quantity':
                return (
                    <input
                        type="number"
                        aria-label={field.label}
                        min={field.config.min}
                        max={field.config.max}
                        value={text}
                        onChange={(event) => onChange(event.target.value)}
                        className={`${PREVIEW_INPUT} sm:w-40`}
                    />
                );
            case 'email':
                return textInput('email');
            case 'phone':
                return textInput('tel', '+243 8xx xxx xxx');
            case 'date':
                return textInput('date');
            case 'date_time':
                return textInput('datetime-local');
            case 'url':
                return textInput('url', 'https://');
            case 'social_profile':
                return textInput('url', 'https://www.linkedin.com/in/…');
            case 'yes_no':
                return (
                    <div className="flex gap-6 text-sm">
                        {([['1', 'Oui'], ['0', 'Non']] as const).map(([choice, label]) => (
                            <label key={choice} className="flex cursor-pointer items-center gap-2">
                                <input type="radio" name={groupName} checked={text === choice} onChange={() => onChange(choice)} />
                                {label}
                            </label>
                        ))}
                    </div>
                );
            case 'consent':
                return (
                    <label className="flex cursor-pointer items-start gap-2 text-sm">
                        <input type="checkbox" className="mt-1" checked={value === true} onChange={(event) => onChange(event.target.checked)} />
                        <span>{field.config.legal_text ? field.config.legal_text : "J'accepte"}</span>
                    </label>
                );
            case 'single_choice':
            case 'meal_choice':
                return (
                    <div className="space-y-2 text-sm">
                        {field.options.map((option, index) => (
                            <label key={index} className="flex cursor-pointer items-center gap-2">
                                <input type="radio" name={groupName} checked={text === optionValue(option)} onChange={() => onChange(optionValue(option))} />
                                {option.label}
                            </label>
                        ))}
                    </div>
                );
            case 'multiple_choice':
                return (
                    <div className="space-y-2 text-sm">
                        {field.options.map((option, index) => (
                            <label key={index} className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={selected.includes(optionValue(option))}
                                    onChange={(event) =>
                                        onChange(
                                            event.target.checked
                                                ? [...selected, optionValue(option)]
                                                : selected.filter((item) => item !== optionValue(option)),
                                        )
                                    }
                                />
                                {option.label}
                            </label>
                        ))}
                    </div>
                );
            case 'dropdown':
                return (
                    <select aria-label={field.label} value={text} onChange={(event) => onChange(event.target.value)} className={PREVIEW_INPUT}>
                        <option value="">Choisissez…</option>
                        {field.options.map((option, index) => (
                            <option key={index} value={optionValue(option)}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                );
            case 'sub_events':
                return (
                    <div className="space-y-2 text-sm">
                        {(field.config.sub_events ?? []).map((subEvent) => (
                            <label key={subEvent.id} className="flex cursor-pointer items-center gap-2 rounded-control border border-black/15 px-3 py-2">
                                <input
                                    type="checkbox"
                                    checked={selected.includes(String(subEvent.id))}
                                    onChange={(event) =>
                                        onChange(
                                            event.target.checked
                                                ? [...selected, String(subEvent.id)]
                                                : selected.filter((item) => item !== String(subEvent.id)),
                                        )
                                    }
                                />
                                {subEvent.title}
                            </label>
                        ))}
                        {(field.config.sub_events ?? []).length === 0 && <p className="opacity-60">Aucune session proposée pour l'instant.</p>}
                    </div>
                );
            case 'donation': {
                const currency = field.config.currency ?? 'XAF';
                const choices: [string, string][] = [
                    ...(field.config.amounts ?? []).map((amount): [string, string] => [String(amount), formatMoney(amount, currency)]),
                    ...((field.config.allow_custom ?? true) ? [['autre', 'Autre montant'] as [string, string]] : []),
                    ...(required ? [] : [['', 'Pas de don pour l’instant'] as [string, string]]),
                ];

                return (
                    <div className="space-y-3 text-sm">
                        {field.config.cause && <p className="opacity-70">Au profit de : {field.config.cause}</p>}
                        <div className="flex flex-wrap gap-2">
                            {choices.map(([choice, label]) => (
                                <label key={choice || 'aucun'} className="cursor-pointer">
                                    <input type="radio" name={groupName} className="peer sr-only" checked={text === choice} onChange={() => onChange(choice)} />
                                    <span className="inline-block rounded-pill border border-black/15 px-3 py-1.5 peer-checked:border-current peer-checked:font-semibold">
                                        {label}
                                    </span>
                                </label>
                            ))}
                        </div>
                        {text === 'autre' && (
                            <input type="text" inputMode="decimal" aria-label="Votre montant" placeholder={`Votre montant (${currency})`} className={`${PREVIEW_INPUT} sm:w-48`} />
                        )}
                    </div>
                );
            }
            case 'donor_info':
                return (
                    <div className="space-y-2">
                        <input type="text" aria-label="Nom du donateur" placeholder="Nom du donateur" className={PREVIEW_INPUT} />
                        <input type="text" aria-label="Entreprise ou organisation" placeholder="Entreprise ou organisation (facultatif)" className={PREVIEW_INPUT} />
                        <input type="text" aria-label="Adresse" placeholder="Adresse" className={PREVIEW_INPUT} />
                        <div className="grid grid-cols-2 gap-2">
                            <input type="text" aria-label="Ville" placeholder="Ville" className={PREVIEW_INPUT} />
                            <input type="text" aria-label="Pays" placeholder="Pays" className={PREVIEW_INPUT} />
                        </div>
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" />
                            Je souhaite que mon don reste anonyme
                        </label>
                    </div>
                );
            case 'postal_address':
                return (
                    <div className="space-y-2">
                        <input type="text" aria-label="Adresse" placeholder="Adresse" className={PREVIEW_INPUT} />
                        <input type="text" aria-label="Complément d'adresse" placeholder="Complément d'adresse" className={PREVIEW_INPUT} />
                        <div className="grid grid-cols-2 gap-2">
                            <input type="text" aria-label="Ville" placeholder="Ville" className={PREVIEW_INPUT} />
                            <input type="text" aria-label="Province ou région" placeholder="Province ou région" className={PREVIEW_INPUT} />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <input type="text" aria-label="Code postal" placeholder="Code postal" className={PREVIEW_INPUT} />
                            <input type="text" aria-label="Pays" placeholder="Pays" className={PREVIEW_INPUT} />
                        </div>
                    </div>
                );
            default:
                return textInput('text');
        }
    }

    return (
        <div>
            <p className="mb-1.5 text-sm font-medium">
                {field.label}
                {required ? ' *' : ''}
            </p>
            {field.help_text && <p className="mb-2 text-sm opacity-70">{field.help_text}</p>}
            {renderInput()}
        </div>
    );
}
