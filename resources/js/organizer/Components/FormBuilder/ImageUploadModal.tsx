import { useRef, useState } from 'react';
import Modal from '../Modal';
import BuilderIcon from './BuilderIcon';
import ImageLibrary, { type LibraryImage } from './ImageLibrary';
import SoonBadge from './SoonBadge';
import { type ImageKind } from './types';

interface ImageUploadModalProps {
    open: boolean;
    kind: ImageKind;
    formId: number | null;
    currentUrl: string | null | undefined;
    onClose: () => void;
    onChange: (url: string | null) => void;
}

type Tab = 'upload' | 'mine' | 'library';

const COPY: Record<ImageKind, { title: string; hint: string }> = {
    logo: {
        title: 'Logo du formulaire',
        hint: 'PNG ou JPG, 2 Mo maximum. Un PNG à fond transparent d’environ 600 × 200 px rend le mieux. L’image est réduite automatiquement pour rester légère.',
    },
    background: {
        title: 'Image de fond',
        hint: 'PNG ou JPG, 2 Mo maximum. Une image d’environ 1920 × 1080 px, peu chargée, garde le texte lisible. Elle est réduite automatiquement pour rester légère.',
    },
};

const TABS: { value: Tab; label: string }[] = [
    { value: 'upload', label: 'Téléverser' },
    { value: 'mine', label: 'Mes images' },
    { value: 'library', label: 'Bibliothèque' },
];

const MAX_BYTES = 2 * 1024 * 1024;

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

export default function ImageUploadModal({ open, kind, formId, currentUrl, onClose, onChange }: ImageUploadModalProps) {
    const [tab, setTab] = useState<Tab>('upload');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [over, setOver] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    async function pickFromLibrary(image: LibraryImage) {
        if (formId === null) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const response = await window.axios.post<{ url: string }>(`/forms/${formId}/theme/${kind}`, { image_id: image.id });
            onChange(response.data.url);
            onClose();
        } catch (exception) {
            setError(uploadErrorMessage(exception));
        } finally {
            setBusy(false);
        }
    }

    async function upload(file: File) {
        if (formId === null) {
            return;
        }

        if (file.type !== 'image/png' && file.type !== 'image/jpeg') {
            setError('Choisissez une image PNG ou JPG.');

            return;
        }

        if (file.size > MAX_BYTES) {
            setError('Cette image dépasse 2 Mo : réduisez-la puis réessayez.');

            return;
        }

        const data = new FormData();
        data.append('image', file);
        setBusy(true);
        setError(null);

        try {
            const response = await window.axios.post<{ url: string }>(`/forms/${formId}/theme/${kind}`, data);
            onChange(response.data.url);
            onClose();
        } catch (exception) {
            setError(uploadErrorMessage(exception));
        } finally {
            setBusy(false);

            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    }

    async function remove() {
        if (formId === null) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            await window.axios.delete(`/forms/${formId}/theme/${kind}`);
            onChange(null);
        } catch (exception) {
            setError(uploadErrorMessage(exception));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={open} onClose={onClose} title={COPY[kind].title} size="lg" showCloseButton>
            <div className="mb-5 flex flex-wrap gap-2 border-b border-line pb-3">
                {TABS.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        aria-pressed={tab === item.value}
                        onClick={() => setTab(item.value)}
                        className={`flex items-center gap-1.5 rounded-pill px-3 py-1.5 text-sm font-medium ${
                            tab === item.value ? 'bg-ink text-bg' : 'text-ink-soft hover:text-ink'
                        }`}
                    >
                        {item.label}
                        {item.value === 'library' && <SoonBadge />}
                    </button>
                ))}
            </div>

            {tab !== 'library' && formId === null && (
                <p className="rounded-control bg-bg-alt px-4 py-3 text-sm text-ink-soft">
                    Faites d'abord une première modification (une question, un écran) : le formulaire sera créé et vous pourrez ajouter des images.
                </p>
            )}

            {tab === 'upload' && formId !== null && (
                <div className="space-y-4">
                    <label
                        htmlFor={`theme_image_${kind}`}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setOver(true);
                        }}
                        onDragLeave={() => setOver(false)}
                        onDrop={(event) => {
                            event.preventDefault();
                            setOver(false);
                            const file = event.dataTransfer.files[0];

                            if (file) {
                                void upload(file);
                            }
                        }}
                        className={`flex cursor-pointer flex-col items-center justify-center gap-3 rounded-card border-2 border-dashed px-6 py-10 text-center transition ${
                            over ? 'border-ink bg-bg-alt' : 'border-line hover:border-ink'
                        }`}
                    >
                        <BuilderIcon name="upload" className="h-8 w-8 text-ink-soft" />
                        <span className="text-sm font-medium text-ink">{busy ? 'Envoi en cours…' : 'Glissez une image ici ou cliquez pour la choisir'}</span>
                        <span className="max-w-sm text-xs text-ink-soft">{COPY[kind].hint}</span>
                        <input
                            ref={inputRef}
                            id={`theme_image_${kind}`}
                            type="file"
                            accept="image/png,image/jpeg"
                            className="sr-only"
                            disabled={busy}
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    void upload(file);
                                }
                            }}
                        />
                    </label>

                    {error && (
                        <p role="alert" className="text-sm text-danger">
                            {error}
                        </p>
                    )}

                    {currentUrl && (
                        <div className="flex items-center gap-4 rounded-control bg-bg-alt p-3">
                            <img src={currentUrl} alt="Image actuelle" className="h-14 w-24 rounded-control bg-bg object-contain" />
                            <span className="min-w-0 flex-1 text-sm text-ink">Image actuelle</span>
                            <button type="button" onClick={() => void remove()} disabled={busy} className="text-sm font-medium text-danger hover:underline disabled:opacity-50">
                                Retirer
                            </button>
                        </div>
                    )}
                </div>
            )}

            {tab === 'mine' && formId !== null && (
                <>
                    <ImageLibrary onPick={(image) => void pickFromLibrary(image)} currentUrl={currentUrl} busy={busy} />
                    {error && (
                        <p role="alert" className="mt-3 text-sm text-danger">
                            {error}
                        </p>
                    )}
                </>
            )}
            {tab === 'library' && <p className="text-sm text-ink-soft">Bientôt : une bibliothèque de photos libres de droits, prêtes à l'emploi.</p>}
        </Modal>
    );
}
