import { useEffect, useState } from 'react';

export interface LibraryImage {
    id: number;
    url: string;
    path: string;
    name: string | null;
    width: number | null;
    height: number | null;
}

interface ImageLibraryProps {
    onPick: (image: LibraryImage) => void;
    currentUrl?: string | null;
    busy?: boolean;
}

interface LibraryResponse {
    images: LibraryImage[];
    total: number;
}

function deleteErrorMessage(exception: unknown): string {
    if (window.axios.isAxiosError(exception)) {
        const data = exception.response?.data as { errors?: Record<string, string[]> } | undefined;
        const first = data?.errors?.image?.[0];

        if (first) {
            return first;
        }

        if (exception.response?.status === 403) {
            return "Vous n'avez pas le droit de gérer les images de cette organisation.";
        }
    }

    return 'La suppression a échoué. Vérifiez votre connexion puis réessayez.';
}

/**
 * Grille de « Mes images » : les images déjà envoyées par l'organisation,
 * réutilisables pour un logo, un fond ou un bloc. La suppression définitive
 * est refusée par le serveur tant qu'une image sert quelque part, et son
 * message nomme les emplacements.
 */
export default function ImageLibrary({ onPick, currentUrl, busy = false }: ImageLibraryProps) {
    const [library, setLibrary] = useState<LibraryResponse | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;

        window.axios
            .get<LibraryResponse>('/images')
            .then((response) => {
                if (!cancelled) {
                    setLibrary(response.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setFailed(true);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    async function remove(image: LibraryImage) {
        setError(null);

        try {
            await window.axios.delete(`/images/${image.id}`);
            setLibrary((current) => (current === null ? current : { images: current.images.filter((item) => item.id !== image.id), total: current.total - 1 }));
        } catch (exception) {
            setError(deleteErrorMessage(exception));
        }
    }

    if (failed) {
        return <p className="text-sm text-danger">Vos images n'ont pas pu être chargées. Réessayez dans un instant.</p>;
    }

    if (library === null) {
        return <p className="text-sm text-ink-soft">Chargement de vos images…</p>;
    }

    if (library.images.length === 0) {
        return (
            <p className="rounded-control bg-bg-alt px-4 py-3 text-sm text-ink-soft">
                Vous n'avez pas encore d'image. Celles que vous envoyez pour un logo, un fond ou un bloc se retrouveront ici, prêtes à être réutilisées.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {error && (
                <p role="alert" className="text-sm text-danger">
                    {error}
                </p>
            )}

            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {library.images.map((image) => (
                    <li key={image.id} className="space-y-1.5">
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => onPick(image)}
                            className={`block w-full overflow-hidden rounded-control border bg-bg-alt disabled:opacity-50 ${
                                image.url === currentUrl ? 'border-ink ring-1 ring-ink' : 'border-line hover:border-ink'
                            }`}
                        >
                            <img src={image.url} alt={image.name ?? ''} loading="lazy" className="h-24 w-full object-contain" />
                        </button>
                        <div className="flex items-baseline justify-between gap-2">
                            <span className="min-w-0 flex-1 truncate text-xs text-ink-soft" title={image.name ?? undefined}>
                                {image.name ?? 'Image'}
                            </span>
                            <button type="button" onClick={() => void remove(image)} className="text-xs font-medium text-danger hover:underline">
                                Supprimer
                            </button>
                        </div>
                    </li>
                ))}
            </ul>

            {library.total > library.images.length && (
                <p className="text-xs text-ink-soft">
                    Vos {library.images.length} images les plus récentes sur {library.total}.
                </p>
            )}
        </div>
    );
}
