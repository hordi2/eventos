import Checkbox from '../Checkbox';
import InputLabel from '../InputLabel';
import TextInput from '../TextInput';
import { PANEL_SECTION_TITLE } from './logic';
import { type FieldConfig, type FileTypeOption } from './types';

interface FileUploadSettingsProps {
    config: FieldConfig;
    types: FileTypeOption[];
    maxSizeMb: number;
    onChange: (patch: Partial<FieldConfig>) => void;
}

// Mêmes formats par défaut que FileUploadAnswer::DEFAULT_TYPES côté serveur.
export const DEFAULT_FILE_TYPES = ['images', 'pdf'];

/**
 * Réglages du bloc « Fichier joint » : formats acceptés et taille maximale.
 */
export default function FileUploadSettings({ config, types, maxSizeMb, onChange }: FileUploadSettingsProps) {
    const chosen = config.file_types ?? DEFAULT_FILE_TYPES;

    function toggle(value: string) {
        onChange({ file_types: chosen.includes(value) ? chosen.filter((type) => type !== value) : [...chosen, value] });
    }

    return (
        <fieldset className="space-y-4">
            <legend className={PANEL_SECTION_TITLE}>Fichier joint</legend>

            <div className="space-y-2">
                <p className="text-sm font-medium text-ink">Formats acceptés</p>
                {types.map((type) => (
                    <label key={type.value} className="flex items-center gap-2 text-sm text-ink">
                        <Checkbox checked={chosen.includes(type.value)} onChange={() => toggle(type.value)} />
                        {type.label}
                    </label>
                ))}
            </div>

            <div>
                <InputLabel htmlFor="file_max_size">Taille maximale par fichier (Mo)</InputLabel>
                <TextInput
                    id="file_max_size"
                    type="number"
                    min={1}
                    max={maxSizeMb}
                    value={config.max_size_mb ?? maxSizeMb}
                    onChange={(event) =>
                        onChange({ max_size_mb: event.target.value === '' ? undefined : Math.min(maxSizeMb, Math.max(1, Number(event.target.value))) })
                    }
                />
                <p className="mt-1 text-xs text-ink-soft">Jusqu'à {maxSizeMb} Mo.</p>
            </div>

            <p className="text-xs text-ink-soft">
                Chaque fichier est vérifié par un antivirus. Seuls les fichiers sains se téléchargent, depuis la page « Fichiers reçus » de l'événement.
            </p>
        </fieldset>
    );
}
