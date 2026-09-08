interface ToggleProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    disabled?: boolean;
}

/**
 * Interrupteur des pages Paramètres. `label` n'est jamais affiché : il sert
 * de nom accessible quand l'intitulé visible vit ailleurs (en-tête de
 * colonne d'un tableau, texte à côté de l'interrupteur…).
 */
export default function Toggle({ checked, onChange, label, disabled = false }: ToggleProps) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => onChange(!checked)}
            className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-pill transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-50 ${
                checked ? 'bg-accent' : 'bg-line'
            }`}
        >
            <span
                className={`inline-block h-4.5 w-4.5 transform rounded-full bg-bg shadow transition-transform duration-200 ${
                    checked ? 'translate-x-6' : 'translate-x-1'
                }`}
            />
        </button>
    );
}
