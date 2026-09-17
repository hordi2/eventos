import { useRef, useState } from 'react';
import InputError from '../InputError';
import InputLabel from '../InputLabel';
import TextInput from '../TextInput';
import BuilderIcon from './BuilderIcon';
import { PANEL_SECTION_TITLE } from './logic';
import { type FieldConfig } from './types';

interface BlockMediaFieldsProps {
    config: FieldConfig;
    formId: number | null;
    onChange: (patch: Partial<FieldConfig>) => void;
}

interface UploadedImage {
    path: string;
    url: string;
    width: number;
    height: number;
}

// Le serveur redimensionne l'image reçue : cette limite ne porte que sur
// l'envoi, pour ne pas faire patienter l'organisateur sur un fichier énorme.
const MAX_BYTES = 8 * 1024 * 1024;

function uploadErrorMessage(exception: unknown): string {
    if (window.axios.isAxiosError(exception)) {
        const data = exception.response?.data as { errors?: Record<string, string[]> } | undefined;
        const first = data?.errors?.image?.[0];

        if (first) {
            return first;
        }

        if (exception.response?.status === 403) {
            return "Vous n'avez pas le droit de modifier ce formulaire.";
        }
    }

    return "L'envoi a échoué. Vérifiez votre connexion puis réessayez.";
}

/**
 * Image et vidéo d'un bloc « Texte, image, vidéo ». L'image part au serveur
 * dès qu'elle est choisie, qui la redimensionne : la configuration du bloc
 * n'en garde que le chemin. La vidéo n'est qu'un lien YouTube ou Vimeo,
 * chargé seulement au clic de l'invité.
 */
export default function BlockMediaFields({ config, formId, onChange }: BlockMediaFieldsProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function forget(path: string | undefined) {
        if (formId !== null && path) {
            void window.axios.delete(`/forms/${formId}/block-image`, { data: { path } });
        }
    }

    async function upload(file: File) {
        if (formId === null) {
            setError("Enregistrez d'abord ce bloc : le formulaire n'existe pas encore.");

            return;
        }

        if (file.size > MAX_BYTES) {
            setError('Cette image dépasse 8 Mo.');

            return;
        }

        const body = new FormData();
        body.append('image', file);
        setBusy(true);
        setError(null);

        try {
            const response = await window.axios.post<UploadedImage>(`/forms/${formId}/block-image`, body);
            forget(config.image_path);
            onChange({
                image_path: response.data.path,
                image_url: response.data.url,
                image_width: response.data.width,
                image_height: response.data.height,
            });
        } catch (exception) {
            setError(uploadErrorMessage(exception));
        } finally {
            setBusy(false);

            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    }

    function removeImage() {
        forget(config.image_path);
        setError(null);
        onChange({ image_path: undefined, image_url: undefined, image_width: undefined, image_height: undefined, image_alt: undefined });
    }

    return (
        <>
            <fieldset className="space-y-3">
                <legend className={PANEL_SECTION_TITLE}>Image</legend>

                {config.image_url ? (
                    <>
                        <img src={config.image_url} alt="" className="h-auto w-full rounded-control border border-line" />
                        <div className="flex flex-wrap items-center gap-3">
                            <button type="button" onClick={() => inputRef.current?.click()} disabled={busy} className="text-sm font-medium text-ink hover:underline disabled:opacity-50">
                                Remplacer
                            </button>
                            <button type="button" onClick={removeImage} disabled={busy} className="text-sm font-medium text-danger hover:underline disabled:opacity-50">
                                Retirer
                            </button>
                        </div>
                        <div>
                            <InputLabel htmlFor="block_image_alt">Description pour les lecteurs d'écran</InputLabel>
                            <TextInput
                                id="block_image_alt"
                                type="text"
                                maxLength={255}
                                value={config.image_alt ?? ''}
                                placeholder="Ce que montre l'image"
                                onChange={(event) => onChange({ image_alt: event.target.value === '' ? undefined : event.target.value })}
                            />
                        </div>
                    </>
                ) : (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        disabled={busy || formId === null}
                        className="flex w-full items-center justify-center gap-2 rounded-control border border-dashed border-line px-4 py-6 text-sm font-medium text-ink hover:border-ink disabled:opacity-50"
                    >
                        <BuilderIcon name="media" className="h-5 w-5 text-ink-soft" />
                        {busy ? 'Envoi en cours…' : 'Choisir une image'}
                    </button>
                )}

                <input
                    ref={inputRef}
                    type="file"
                    accept=".png,.jpg,.jpeg,.webp"
                    className="sr-only"
                    onChange={(event) => {
                        const file = event.target.files?.[0];

                        if (file) {
                            void upload(file);
                        }
                    }}
                />

                <p className="text-xs text-ink-soft">
                    PNG, JPG ou WEBP. Elle est réduite automatiquement pour rester légère sur le téléphone de vos invités.
                </p>
                <InputError message={error ?? undefined} />
            </fieldset>

            <fieldset>
                <legend className={PANEL_SECTION_TITLE}>Vidéo</legend>
                <TextInput
                    id="block_video_url"
                    type="url"
                    maxLength={2048}
                    value={config.video_url ?? ''}
                    placeholder="https://www.youtube.com/watch?v=…"
                    onChange={(event) => onChange({ video_url: event.target.value === '' ? undefined : event.target.value })}
                />
                <p className="mt-2 text-xs text-ink-soft">
                    Un lien YouTube ou Vimeo. La vidéo ne se charge que si l'invité clique dessus : la page reste rapide et rien n'est envoyé à
                    l'hébergeur avant ce clic.
                </p>
            </fieldset>
        </>
    );
}
