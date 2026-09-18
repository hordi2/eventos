import { useEffect, useState, type FormEvent } from 'react';
import TextInput from '../TextInput';
import { type LibraryImage } from './ImageLibrary';

interface StockPhoto {
    id: number;
    thumbnail: string;
    alt: string;
    photographer: string;
    photographer_url: string | null;
    url: string | null;
}

interface SearchResponse {
    photos: StockPhoto[];
    has_more: boolean;
}

interface StockPhotoLibraryProps {
    onPick: (image: LibraryImage) => void;
    busy?: boolean;
}

function serverMessage(exception: unknown, fallback: string): string {
    if (window.axios.isAxiosError(exception)) {
        const data = exception.response?.data as { message?: string } | undefined;

        if (exception.response?.status === 403) {
            return "Vous n'avez pas le droit d'ajouter des images à cette organisation.";
        }

        if (exception.response?.status === 429) {
            return 'Trop de recherches en peu de temps : patientez une minute puis réessayez.';
        }

        if (data?.message) {
            return data.message;
        }
    }

    return fallback;
}

/**
 * Onglet « Bibliothèque » : photos libres de droits de Pexels. La photo
 * choisie est d'abord copiée dans « Mes images » par le serveur, puis
 * choisie comme n'importe quelle image de la bibliothèque — la page invité ne
 * contacte donc jamais Pexels. Le crédit du photographe et le lien vers
 * Pexels restent affichés, comme le demandent leurs conditions.
 */
export default function StockPhotoLibrary({ onPick, busy = false }: StockPhotoLibraryProps) {
    const [query, setQuery] = useState('');
    const [searched, setSearched] = useState('');
    const [page, setPage] = useState(1);
    const [photos, setPhotos] = useState<StockPhoto[]>([]);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(true);
    const [importing, setImporting] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        window.axios
            .get<SearchResponse>('/media-library/stock', { params: { query: searched, page } })
            .then((response) => {
                if (!cancelled) {
                    setPhotos((current) => (page === 1 ? response.data.photos : [...current, ...response.data.photos]));
                    setHasMore(response.data.has_more);
                    setError(null);
                }
            })
            .catch((exception: unknown) => {
                if (!cancelled) {
                    setError(serverMessage(exception, 'La recherche a échoué. Vérifiez votre connexion puis réessayez.'));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [searched, page]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setLoading(true);
        setPage(1);
        setSearched(query.trim());
    }

    async function choose(photo: StockPhoto) {
        setImporting(photo.id);
        setError(null);

        try {
            const response = await window.axios.post<LibraryImage>(`/media-library/stock/${photo.id}`);
            onPick(response.data);
        } catch (exception) {
            setError(serverMessage(exception, "La photo n'a pas pu être ajoutée. Réessayez dans un instant."));
        } finally {
            setImporting(null);
        }
    }

    return (
        <div className="space-y-4">
            <form onSubmit={submit} className="flex gap-2">
                <TextInput
                    type="search"
                    maxLength={100}
                    value={query}
                    placeholder="Mariage, gala, conférence…"
                    aria-label="Rechercher une photo"
                    onChange={(event) => setQuery(event.target.value)}
                />
                <button type="submit" className="shrink-0 rounded-pill bg-ink px-4 py-2 text-sm font-medium text-bg hover:opacity-90">
                    Rechercher
                </button>
            </form>

            {error && (
                <p role="alert" className="text-sm text-danger">
                    {error}
                </p>
            )}

            {loading && photos.length === 0 && <p className="text-sm text-ink-soft">Chargement des photos…</p>}

            {!loading && !error && photos.length === 0 && (
                <p className="rounded-control bg-bg-alt px-4 py-3 text-sm text-ink-soft">Aucune photo pour « {searched} ». Essayez un autre mot.</p>
            )}

            {photos.length > 0 && (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {photos.map((photo) => (
                        <li key={photo.id} className="space-y-1">
                            <button
                                type="button"
                                disabled={busy || importing !== null}
                                onClick={() => void choose(photo)}
                                className="relative block w-full overflow-hidden rounded-control border border-line bg-bg-alt hover:border-ink disabled:opacity-50"
                            >
                                <img src={photo.thumbnail} alt={photo.alt} loading="lazy" className="h-24 w-full object-cover" />
                                {importing === photo.id && (
                                    <span className="absolute inset-0 flex items-center justify-center bg-bg/80 text-xs font-medium text-ink">Ajout…</span>
                                )}
                            </button>
                            <p className="truncate text-xs text-ink-soft">
                                {photo.photographer_url ? (
                                    <a href={photo.photographer_url} target="_blank" rel="noreferrer" className="hover:underline">
                                        {photo.photographer}
                                    </a>
                                ) : (
                                    photo.photographer
                                )}
                            </p>
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <a href="https://www.pexels.com" target="_blank" rel="noreferrer" className="text-xs text-ink-soft hover:underline">
                    Photos fournies par Pexels
                </a>
                {hasMore && (
                    <button
                        type="button"
                        disabled={loading}
                        onClick={() => {
                            setLoading(true);
                            setPage((current) => current + 1);
                        }}
                        className="text-sm font-medium text-ink hover:underline disabled:opacity-50"
                    >
                        {loading ? 'Chargement…' : 'Plus de photos'}
                    </button>
                )}
            </div>

            <p className="text-xs text-ink-soft">La photo choisie est ajoutée à « Mes images », réduite pour rester légère sur le téléphone de vos invités.</p>
        </div>
    );
}
