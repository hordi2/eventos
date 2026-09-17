import { useState } from 'react';
import InputLabel from '../InputLabel';
import Select from '../Select';
import TextInput from '../TextInput';
import Toggle from '../Toggle';
import BuilderIcon from './BuilderIcon';
import { PANEL_SECTION_TITLE, toMajorText, toMinorUnits } from './logic';
import { type CurrencyOption, type FieldConfig } from './types';

interface DonationSettingsProps {
    config: FieldConfig;
    currencies: CurrencyOption[];
    onChange: (patch: Partial<FieldConfig>) => void;
}

const MAX_AMOUNTS = 6;

const AMOUNT_INPUT =
    'min-w-0 flex-1 rounded-control border border-line bg-transparent px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none aria-invalid:border-danger';

/**
 * Réglages du bloc « Don » : devise, montants proposés (saisis en unités,
 * enregistrés en unité mineure), montant libre et cause soutenue.
 */
export default function DonationSettings({ config, currencies, onChange }: DonationSettingsProps) {
    const currency = config.currency ?? currencies[0]?.code ?? 'XAF';
    const [texts, setTexts] = useState<string[]>(() => (config.amounts ?? []).map((amount) => toMajorText(amount, currency)));

    function commit(nextTexts: string[], nextCurrency: string) {
        setTexts(nextTexts);
        onChange({
            currency: nextCurrency,
            amounts: nextTexts
                .map((text) => toMinorUnits(text, nextCurrency))
                .filter((amount): amount is number => amount !== null && amount > 0),
        });
    }

    return (
        <fieldset className="space-y-4">
            <legend className={PANEL_SECTION_TITLE}>Don</legend>

            <div>
                <InputLabel htmlFor="donation_currency">Devise</InputLabel>
                <Select id="donation_currency" value={currency} onChange={(event) => commit(texts, event.target.value)}>
                    {currencies.map((option) => (
                        <option key={option.code} value={option.code}>
                            {option.label} ({option.code})
                        </option>
                    ))}
                </Select>
            </div>

            <div>
                <p className="mb-2 text-sm font-medium text-ink">Montants proposés</p>
                <ul className="space-y-2">
                    {texts.map((text, index) => {
                        const invalid = text.trim() !== '' && (toMinorUnits(text, currency) ?? 0) <= 0;

                        return (
                            <li key={index}>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="text"
                                        inputMode="decimal"
                                        aria-label={`Montant ${index + 1}`}
                                        aria-invalid={invalid}
                                        value={text}
                                        onChange={(event) =>
                                            commit(
                                                texts.map((item, position) => (position === index ? event.target.value : item)),
                                                currency,
                                            )
                                        }
                                        className={AMOUNT_INPUT}
                                    />
                                    <span className="w-9 text-xs text-ink-soft">{currency}</span>
                                    <button
                                        type="button"
                                        onClick={() => commit(texts.filter((_, position) => position !== index), currency)}
                                        aria-label={`Retirer le montant ${index + 1}`}
                                        className="p-1 text-ink-soft hover:text-danger"
                                    >
                                        <BuilderIcon name="close" className="h-4 w-4" />
                                    </button>
                                </div>
                                {invalid && <p className="mt-1 text-xs text-danger">Montant illisible dans cette devise.</p>}
                            </li>
                        );
                    })}
                </ul>
                {texts.length < MAX_AMOUNTS && (
                    <button type="button" onClick={() => setTexts([...texts, ''])} className="mt-3 flex items-center gap-1.5 text-sm font-medium text-ink hover:underline">
                        <BuilderIcon name="plus" className="h-4 w-4" />
                        Ajouter un montant
                    </button>
                )}
            </div>

            <div className="flex items-center justify-between gap-4">
                <span className="text-sm text-ink">Laisser l'invité choisir son montant</span>
                <Toggle checked={config.allow_custom ?? true} onChange={(checked) => onChange({ allow_custom: checked })} label="Montant libre" />
            </div>

            <div>
                <InputLabel htmlFor="donation_cause">Cause soutenue</InputLabel>
                <TextInput
                    id="donation_cause"
                    type="text"
                    maxLength={255}
                    value={config.cause ?? ''}
                    placeholder="Par exemple : bourses pour les étudiants"
                    onChange={(event) => onChange({ cause: event.target.value === '' ? null : event.target.value })}
                />
            </div>

            <p className="text-xs text-ink-soft">
                Après son inscription, l'invité règle son don par carte, Mobile Money ou à l'accueil. Son reçu part par e-mail dès le paiement reçu.
            </p>
        </fieldset>
    );
}
