import { Head } from '@inertiajs/react';
import { type ChangeEvent, useRef, useState } from 'react';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import Textarea from '../../Components/Textarea';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

interface BlockItem {
    time?: string;
    title?: string;
    description?: string;
    question?: string;
    answer?: string;
    // Photo d'une galerie : son chemin en bibliothèque, et son adresse
    // pour l'aperçu de l'éditeur.
    path?: string;
    url?: string;
    // Illustration d'un moment du programme.
    icon?: string;
}

interface Block {
    id: string;
    type: string;
    title: string | null;
    // Fond du bloc : image de la bibliothèque, couleur, voile et couleur du texte.
    background?: string | null;
    backgroundUrl?: string | null;
    backgroundColor?: string | null;
    backgroundOverlay?: number;
    textTone?: 'light' | 'dark';
    // Programme : les illustrations s'affichent, ou pas.
    showIcons?: boolean;
    // Mot d'accueil déposé dans Itaza : son jeton et son nom de fichier.
    mediaToken?: string | null;
    mediaName?: string | null;
    // Réponse à l'invitation : le texte des trois boutons.
    yesLabel?: string | null;
    noLabel?: string | null;
    laterLabel?: string | null;
    body?: string | null;
    path?: string | null;
    url?: string | null;
    alt?: string | null;
    items?: BlockItem[];
}

interface BlockTypeOption {
    value: string;
    label: string;
    hint: string;
}

interface FontOption {
    value: string;
    label: string;
}

interface Props {
    event: { id: number; title: string; slug: string };
    publicUrl: string;
    page: {
        banner_url: string | null;
        meta_description: string | null;
        cover_eyebrow: string | null;
        cover_script: string | null;
        cover_monogram: string | null;
        cover_overlay: number;
        cover_cta_label: string | null;
        heading_font: string;
        body_font: string;
        script_font: string;
        blocks: Block[];
    };
    blockTypes: BlockTypeOption[];
    fonts: { heading: FontOption[]; body: FontOption[]; script: FontOption[] };
    invitationPdfUrl: string;
}

function emptyBlock(type: string): Block {
    return {
        id: crypto.randomUUID(),
        type,
        title: null,
        body: '',
        path: null,
        url: null,
        alt: null,
        items: type === 'program' || type === 'faq' || type === 'details' || type === 'gallery' ? [{}] : [],
        background: null,
        backgroundColor: null,
        backgroundOverlay: 45,
        textTone: 'dark',
        showIcons: false,
    };
}

/** Les illustrations proposées pour un moment du programme. */
const PROGRAMME_ICONS: { value: string; label: string }[] = [
    { value: 'etoile', label: 'Étoile' },
    { value: 'accueil', label: 'Accueil' },
    { value: 'ceremonie', label: 'Cérémonie' },
    { value: 'couple', label: 'Les mariés' },
    { value: 'danse', label: 'Danse' },
    { value: 'repas', label: 'Dîner' },
    { value: 'cadeau', label: 'Cadeaux' },
    { value: 'musique', label: 'Cocktail' },
    { value: 'discours', label: 'Discours' },
    { value: 'photo', label: 'Photos' },
    { value: 'fin', label: 'Fin de soirée' },
];

const CARD = 'mb-4 rounded-card bg-bg p-5 ring-1 ring-line';
const SMALL_BUTTON = 'inline-flex min-h-9 items-center rounded-pill border border-line px-3 py-1 text-sm text-ink hover:border-ink disabled:opacity-40';

/**
 * Site web de l'événement : la page publique se compose de blocs, que
 * l'organisateur ajoute, déplace et retire. Le haut de page (bannière,
 * titre, date, bouton « S'inscrire ») reste toujours en place.
 */
export default function Edit({ event, publicUrl, page, blockTypes, fonts, invitationPdfUrl }: Props) {
    const [bannerUrl, setBannerUrl] = useState(page.banner_url);
    const [metaDescription, setMetaDescription] = useState(page.meta_description ?? '');
    const [cover, setCover] = useState({
        eyebrow: page.cover_eyebrow ?? '',
        script: page.cover_script ?? '',
        monogram: page.cover_monogram ?? '',
        overlay: page.cover_overlay,
        ctaLabel: page.cover_cta_label ?? '',
    });
    const [typography, setTypography] = useState({
        heading: page.heading_font,
        body: page.body_font,
        script: page.script_font,
    });
    const [blocks, setBlocks] = useState<Block[]>(page.blocks);
    const [newType, setNewType] = useState(blockTypes[0]?.value ?? 'text');
    const [uploading, setUploading] = useState(false);
    const [saving, setSaving] = useState(false);
    // Verdict de l'analyse antivirus d'un mot d'accueil, par bloc.
    const [mediaStatus, setMediaStatus] = useState<Record<string, string>>({});
    const [saved, setSaved] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    async function handleBannerChange(changeEvent: ChangeEvent<HTMLInputElement>) {
        const file = changeEvent.target.files?.[0];

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('banner', file);
        setUploading(true);

        try {
            const response = await window.axios.post<{ banner_url: string }>(`/events/${event.id}/page/banner`, formData);
            setBannerUrl(response.data.banner_url);
        } finally {
            setUploading(false);
        }
    }

    function update(index: number, changes: Partial<Block>) {
        setBlocks((current) => current.map((block, i) => (i === index ? { ...block, ...changes } : block)));
    }

    function move(index: number, direction: -1 | 1) {
        setBlocks((current) => {
            const next = [...current];
            const target = index + direction;

            if (target < 0 || target >= next.length) {
                return current;
            }

            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    }

    function updateItem(blockIndex: number, itemIndex: number, changes: BlockItem) {
        setBlocks((current) =>
            current.map((block, i) =>
                i === blockIndex ? { ...block, items: (block.items ?? []).map((item, j) => (j === itemIndex ? { ...item, ...changes } : item)) } : block,
            ),
        );
    }

    /** Fond d'un bloc : même bibliothèque que les blocs image. */
    async function uploadBackground(index: number, file: File) {
        const formData = new FormData();
        formData.append('image', file);
        setUploading(true);

        try {
            const response = await window.axios.post<{ path: string; url: string }>(`/events/${event.id}/page/images`, formData);
            update(index, { background: response.data.path, backgroundUrl: response.data.url });
        } finally {
            setUploading(false);
        }
    }

    /**
     * Mot d'accueil en audio ou en vidéo, déposé dans Itaza : le fichier
     * passe d'abord par l'analyse antivirus, et ne se joue qu'ensuite.
     */
    async function uploadMedia(index: number, blockId: string, file: File) {
        const formData = new FormData();
        formData.append('media', file);
        setUploading(true);

        try {
            const response = await window.axios.post<{ token: string; name: string; statusLabel: string }>(
                `/events/${event.id}/page/media`,
                formData,
            );
            update(index, { mediaToken: response.data.token, mediaName: response.data.name, url: null });
            setMediaStatus((current) => ({ ...current, [blockId]: response.data.statusLabel }));
        } finally {
            setUploading(false);
        }
    }

    /** Photo d'une galerie : même bibliothèque que les blocs image. */
    async function uploadItemImage(blockIndex: number, itemIndex: number, file: File) {
        const formData = new FormData();
        formData.append('image', file);
        setUploading(true);

        try {
            const response = await window.axios.post<{ path: string; url: string }>(`/events/${event.id}/page/images`, formData);
            updateItem(blockIndex, itemIndex, { path: response.data.path, url: response.data.url });
        } finally {
            setUploading(false);
        }
    }

    async function uploadImage(index: number, file: File) {
        const formData = new FormData();
        formData.append('image', file);
        setUploading(true);

        try {
            const response = await window.axios.post<{ path: string; url: string }>(`/events/${event.id}/page/images`, formData);
            update(index, { path: response.data.path, url: response.data.url });
        } finally {
            setUploading(false);
        }
    }

    async function handleSave() {
        setSaving(true);

        try {
            await window.axios.patch(`/events/${event.id}/page`, {
                meta_description: metaDescription || null,
                cover_eyebrow: cover.eyebrow || null,
                cover_script: cover.script || null,
                cover_monogram: cover.monogram || null,
                cover_overlay: cover.overlay,
                cover_cta_label: cover.ctaLabel || null,
                heading_font: typography.heading,
                body_font: typography.body,
                script_font: typography.script,
                blocks,
            });
            setSaved(true);
            window.setTimeout(() => setSaved(false), 3000);
        } finally {
            setSaving(false);
        }
    }

    return (
        <EventLayout title="Site web de l'événement" eyebrow={event.title}>
            <Head title="Site web de l'événement" />

            <div className="mb-8 flex flex-wrap items-center justify-end gap-5">
                {/* Le faire-part tel que le recevra un invité, sans quitter l'éditeur. */}
                <a href={invitationPdfUrl} className="text-sm text-accent underline underline-offset-2">
                    Télécharger le faire-part en PDF
                </a>
                <a href={publicUrl} target="_blank" rel="noreferrer" className="text-sm text-accent underline underline-offset-2">
                    Voir la page publique
                </a>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Bannière</h2>
                {bannerUrl && <img src={bannerUrl} alt="" className="mb-4 aspect-[2/1] w-full rounded-card object-cover" />}
                <input ref={fileInputRef} type="file" accept="image/png,image/jpeg" onChange={handleBannerChange} className="hidden" />
                <Button type="button" variant="secondary" onClick={() => fileInputRef.current?.click()} disabled={uploading} className="w-auto px-6 py-2">
                    {uploading ? 'Envoi…' : bannerUrl ? 'Remplacer la bannière' : 'Ajouter une bannière'}
                </Button>
            </div>

            {/* La couverture est le premier écran de l'invitation : tout ce
                qui s'y lit se règle ici. */}
            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-1 font-serif text-lg italic">Couverture</h2>
                <p className="mb-5 text-sm text-ink-soft">
                    Le premier écran de votre invitation : votre photo en fond, et ces mots par-dessus.
                </p>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="cover_eyebrow">Phrase d'ouverture</InputLabel>
                        <TextInput
                            id="cover_eyebrow"
                            value={cover.eyebrow}
                            onChange={(changeEvent) => setCover({ ...cover, eyebrow: changeEvent.target.value })}
                            maxLength={120}
                            placeholder="Vous êtes invité"
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="cover_script">Mot manuscrit</InputLabel>
                        <TextInput
                            id="cover_script"
                            value={cover.script}
                            onChange={(changeEvent) => setCover({ ...cover, script: changeEvent.target.value })}
                            maxLength={120}
                            placeholder="Save the date"
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="cover_monogram">Monogramme en filigrane</InputLabel>
                        <TextInput
                            id="cover_monogram"
                            value={cover.monogram}
                            onChange={(changeEvent) => setCover({ ...cover, monogram: changeEvent.target.value })}
                            maxLength={12}
                            placeholder="M & A"
                        />
                        <p className="mt-1.5 text-xs text-ink-soft">Vos initiales, très grandes et très discrètes derrière le titre.</p>
                    </div>
                    <div>
                        <InputLabel htmlFor="cover_cta_label">Libellé du bouton</InputLabel>
                        <TextInput
                            id="cover_cta_label"
                            value={cover.ctaLabel}
                            onChange={(changeEvent) => setCover({ ...cover, ctaLabel: changeEvent.target.value })}
                            maxLength={60}
                            placeholder="Répondre à l'invitation"
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="cover_overlay">Voile sur la photo — {cover.overlay} %</InputLabel>
                        <input
                            id="cover_overlay"
                            type="range"
                            min={0}
                            max={90}
                            step={5}
                            value={cover.overlay}
                            onChange={(changeEvent) => setCover({ ...cover, overlay: Number(changeEvent.target.value) })}
                            className="mt-2 w-full accent-ink"
                        />
                        <p className="mt-1.5 text-xs text-ink-soft">
                            Plus le voile est épais, plus le texte ressort — et moins la photo se voit.
                        </p>
                    </div>
                </div>
            </div>

            {/* Les trois polices de l'invitation. « Police de l'appareil »
                ne charge rien : c'est le choix le plus rapide en 3G. */}
            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-1 font-serif text-lg italic">Polices</h2>
                <p className="mb-5 text-sm text-ink-soft">
                    Elles s'appliquent à la page publique comme au faire-part en PDF.
                </p>

                <div className="grid gap-5 sm:grid-cols-3">
                    <div>
                        <InputLabel htmlFor="heading_font">Titres</InputLabel>
                        <Select
                            id="heading_font"
                            value={typography.heading}
                            onChange={(changeEvent) => setTypography({ ...typography, heading: changeEvent.target.value })}
                        >
                            {fonts.heading.map((font) => (
                                <option key={font.value} value={font.value}>
                                    {font.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <InputLabel htmlFor="body_font">Texte</InputLabel>
                        <Select
                            id="body_font"
                            value={typography.body}
                            onChange={(changeEvent) => setTypography({ ...typography, body: changeEvent.target.value })}
                        >
                            {fonts.body.map((font) => (
                                <option key={font.value} value={font.value}>
                                    {font.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <InputLabel htmlFor="script_font">Écriture manuscrite</InputLabel>
                        <Select
                            id="script_font"
                            value={typography.script}
                            onChange={(changeEvent) => setTypography({ ...typography, script: changeEvent.target.value })}
                        >
                            {fonts.script.map((font) => (
                                <option key={font.value} value={font.value}>
                                    {font.label}
                                </option>
                            ))}
                        </Select>
                        <p className="mt-1.5 text-xs text-ink-soft">Pour le mot manuscrit de la couverture et le « Save the date ».</p>
                    </div>
                </div>
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Référencement</h2>
                <InputLabel htmlFor="meta_description">Description pour les moteurs de recherche</InputLabel>
                <Textarea
                    id="meta_description"
                    value={metaDescription}
                    onChange={(changeEvent) => setMetaDescription(changeEvent.target.value)}
                    maxLength={160}
                    rows={2}
                />
                <p className="mt-1.5 text-xs text-ink-soft">{metaDescription.length}/160 caractères. Vide : la description de l'événement est reprise.</p>
            </div>

            <div className="mb-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 className="font-serif text-lg italic">Contenu de la page</h2>
                    <p className="text-sm text-ink-soft">
                        Les blocs s'affichent dans cet ordre, sous le titre et le bouton « S'inscrire ».
                    </p>
                </div>
                <div className="flex flex-wrap items-end gap-2">
                    <div className="w-56">
                        <InputLabel htmlFor="new_block">Ajouter un bloc</InputLabel>
                        <Select id="new_block" value={newType} onChange={(changeEvent) => setNewType(changeEvent.target.value)}>
                            {blockTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <Button type="button" onClick={() => setBlocks((current) => [...current, emptyBlock(newType)])} className="w-auto px-6 py-2">
                        Ajouter
                    </Button>
                </div>
            </div>

            {blocks.length === 0 ? (
                <div className="mb-8 rounded-card border border-dashed border-line bg-bg px-6 py-10 text-center">
                    <p className="mb-1 font-serif text-xl text-ink italic">Page sans contenu</p>
                    <p className="text-sm text-ink-soft">Ajoutez un premier bloc : un texte, le programme, le lieu…</p>
                </div>
            ) : (
                blocks.map((block, index) => {
                    const type = blockTypes.find((candidate) => candidate.value === block.type);

                    return (
                        <div key={block.id} className={CARD}>
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="font-medium text-ink">{type?.label ?? block.type}</p>
                                    <p className="text-xs text-ink-soft">{type?.hint}</p>
                                </div>
                                <div className="flex gap-2">
                                    <button type="button" onClick={() => move(index, -1)} disabled={index === 0} className={SMALL_BUTTON} aria-label="Monter le bloc">
                                        ↑
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => move(index, 1)}
                                        disabled={index === blocks.length - 1}
                                        className={SMALL_BUTTON}
                                        aria-label="Descendre le bloc"
                                    >
                                        ↓
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setBlocks((current) => current.filter((_, i) => i !== index))}
                                        className="inline-flex min-h-9 items-center rounded-pill px-3 py-1 text-sm text-danger underline hover:no-underline"
                                    >
                                        Retirer
                                    </button>
                                </div>
                            </div>

                            {(block.type === 'entry_qr' || block.type === 'rsvp') && (
                                <p className="mb-4 rounded-control border border-line bg-bg-alt px-4 py-2 text-xs text-ink-soft">
                                    Ce bloc ferme toujours l'invitation, sur la page comme sur le faire-part : le code
                                    d'entrée, puis la confirmation en dernier. Ajoutez-le seulement pour en changer les mots.
                                </p>
                            )}

                            <div className="mb-4">
                                <InputLabel htmlFor={`title_${block.id}`}>Titre du bloc (optionnel)</InputLabel>
                                <TextInput
                                    id={`title_${block.id}`}
                                    value={block.title ?? ''}
                                    onChange={(changeEvent) => update(index, { title: changeEvent.target.value })}
                                    maxLength={255}
                                />
                            </div>

                            {block.type === 'text' && (
                                <Textarea value={block.body ?? ''} onChange={(changeEvent) => update(index, { body: changeEvent.target.value })} rows={5} maxLength={5000} />
                            )}

                            {block.type === 'image' && (
                                <div className="space-y-3">
                                    {block.url && <img src={block.url} alt="" className="w-full rounded-card" />}
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(changeEvent) => {
                                            const file = changeEvent.target.files?.[0];

                                            if (file) {
                                                void uploadImage(index, file);
                                            }
                                        }}
                                        className="block w-full text-sm text-ink-soft file:mr-3 file:min-h-9 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-4 file:py-1.5 file:text-sm file:text-ink"
                                    />
                                    <div>
                                        <InputLabel htmlFor={`alt_${block.id}`}>Description de l'image (accessibilité)</InputLabel>
                                        <TextInput
                                            id={`alt_${block.id}`}
                                            value={block.alt ?? ''}
                                            onChange={(changeEvent) => update(index, { alt: changeEvent.target.value })}
                                            maxLength={255}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Fond du bloc : ce qui se voit derrière, et la
                                couleur du texte posé dessus. */}
                            <details className="mb-4 rounded-control border border-line px-4 py-3">
                                <summary className="cursor-pointer text-sm text-ink-soft">Fond de ce bloc</summary>

                                <div className="mt-4 space-y-4">
                                    {block.backgroundUrl && <img src={block.backgroundUrl} alt="" className="aspect-[3/1] w-full rounded-control object-cover" />}

                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(changeEvent) => {
                                            const file = changeEvent.target.files?.[0];

                                            if (file) {
                                                void uploadBackground(index, file);
                                            }
                                        }}
                                        className="block w-full text-sm text-ink-soft file:mr-3 file:min-h-9 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-4 file:py-1.5 file:text-sm file:text-ink"
                                    />

                                    {block.background && (
                                        <button
                                            type="button"
                                            onClick={() => update(index, { background: null, backgroundUrl: null })}
                                            className="text-sm text-danger underline hover:no-underline"
                                        >
                                            Retirer l'image de fond
                                        </button>
                                    )}

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor={`bgcolor_${block.id}`}>Couleur de fond</InputLabel>
                                            <div className="mt-1 flex items-center gap-3">
                                                <input
                                                    id={`bgcolor_${block.id}`}
                                                    type="color"
                                                    value={block.backgroundColor ?? '#1b1611'}
                                                    onChange={(changeEvent) => update(index, { backgroundColor: changeEvent.target.value })}
                                                    className="h-9 w-14 cursor-pointer rounded-control border border-line"
                                                />
                                                {block.backgroundColor && (
                                                    <button
                                                        type="button"
                                                        onClick={() => update(index, { backgroundColor: null })}
                                                        className="text-sm text-ink-soft underline hover:no-underline"
                                                    >
                                                        Sans couleur
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        <div>
                                            <InputLabel htmlFor={`tone_${block.id}`}>Couleur du texte</InputLabel>
                                            <Select
                                                id={`tone_${block.id}`}
                                                value={block.textTone ?? 'dark'}
                                                onChange={(changeEvent) => update(index, { textTone: changeEvent.target.value as 'light' | 'dark' })}
                                            >
                                                <option value="dark">Texte sombre</option>
                                                <option value="light">Texte clair</option>
                                            </Select>
                                        </div>
                                    </div>

                                    {block.background && (
                                        <div>
                                            <InputLabel htmlFor={`overlay_${block.id}`}>
                                                Voile sur l'image — {block.backgroundOverlay ?? 45} %
                                            </InputLabel>
                                            <input
                                                id={`overlay_${block.id}`}
                                                type="range"
                                                min={0}
                                                max={90}
                                                step={5}
                                                value={block.backgroundOverlay ?? 45}
                                                onChange={(changeEvent) => update(index, { backgroundOverlay: Number(changeEvent.target.value) })}
                                                className="mt-2 w-full accent-ink"
                                            />
                                        </div>
                                    )}
                                </div>
                            </details>

                            {block.type === 'full_photo' && (
                                <div className="space-y-3">
                                    {block.url && <img src={block.url} alt="" className="aspect-[3/2] w-full rounded-card object-cover" />}
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(changeEvent) => {
                                            const file = changeEvent.target.files?.[0];

                                            if (file) {
                                                void uploadImage(index, file);
                                            }
                                        }}
                                        className="block w-full text-sm text-ink-soft file:mr-3 file:min-h-9 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-4 file:py-1.5 file:text-sm file:text-ink"
                                    />
                                    <Textarea
                                        value={block.body ?? ''}
                                        onChange={(e) => update(index, { body: e.target.value })}
                                        rows={2}
                                        placeholder="Un mot par-dessus la photo (optionnel)"
                                    />
                                    <p className="text-xs text-ink-soft">
                                        La photo occupe tout l'écran, recadrée au centre. Une image haute (portrait) tient mieux
                                        sur téléphone ; prévoyez au moins 1600 px de large.
                                    </p>
                                </div>
                            )}

                            {(block.type === 'entry_qr' || block.type === 'rsvp') && (
                                <Textarea
                                    value={block.body ?? ''}
                                    onChange={(e) => update(index, { body: e.target.value })}
                                    rows={2}
                                    placeholder={
                                        block.type === 'entry_qr'
                                            ? "Présentez ce code à l'accueil…"
                                            : 'Merci de nous dire si vous serez des nôtres.'
                                    }
                                />
                            )}

                            {block.type === 'rsvp' && (
                                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <InputLabel htmlFor={`yes_${block.id}`}>Bouton « oui »</InputLabel>
                                        <TextInput
                                            id={`yes_${block.id}`}
                                            value={block.yesLabel ?? ''}
                                            onChange={(changeEvent) => update(index, { yesLabel: changeEvent.target.value })}
                                            maxLength={60}
                                            placeholder="Je confirme ma présence"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor={`later_${block.id}`}>Bouton « plus tard »</InputLabel>
                                        <TextInput
                                            id={`later_${block.id}`}
                                            value={block.laterLabel ?? ''}
                                            onChange={(changeEvent) => update(index, { laterLabel: changeEvent.target.value })}
                                            maxLength={60}
                                            placeholder="Je vais confirmer plus tard"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor={`no_${block.id}`}>Bouton « non »</InputLabel>
                                        <TextInput
                                            id={`no_${block.id}`}
                                            value={block.noLabel ?? ''}
                                            onChange={(changeEvent) => update(index, { noLabel: changeEvent.target.value })}
                                            maxLength={60}
                                            placeholder="Je ne pourrai pas être présent"
                                        />
                                    </div>
                                    <p className="text-xs text-ink-soft sm:col-span-3">
                                        Vides, les trois textes d'origine s'affichent. Le bouton « non » n'apparaît que si le
                                        formulaire d'inscription accepte les réponses négatives.
                                    </p>
                                </div>
                            )}

                            {block.type === 'save_the_date' && (
                                <Textarea
                                    value={block.body ?? ''}
                                    onChange={(e) => update(index, { body: e.target.value })}
                                    rows={4}
                                    placeholder="Les familles X et Y ont l'honneur de vous convier…"
                                />
                            )}

                            {block.type === 'welcome_message' && (
                                <div className="space-y-3">
                                    {block.mediaToken ? (
                                        <div className="rounded-control border border-line px-4 py-3">
                                            <p className="text-sm text-ink">{block.mediaName ?? 'Fichier déposé'}</p>
                                            <p className="text-xs text-ink-soft">
                                                {mediaStatus[block.id] ??
                                                    "Déposé dans Itaza. Il se joue dès que l'analyse antivirus est passée."}
                                            </p>
                                            <button
                                                type="button"
                                                onClick={() => update(index, { mediaToken: null, mediaName: null })}
                                                className="mt-2 text-sm text-danger underline hover:no-underline"
                                            >
                                                Retirer ce fichier
                                            </button>
                                        </div>
                                    ) : (
                                        <>
                                            <input
                                                type="file"
                                                accept="audio/*,video/mp4,video/webm"
                                                aria-label="Déposer le mot d'accueil"
                                                onChange={(changeEvent) => {
                                                    const file = changeEvent.target.files?.[0];

                                                    if (file) {
                                                        void uploadMedia(index, block.id, file);
                                                    }
                                                }}
                                                className="block w-full text-sm text-ink-soft file:mr-3 file:min-h-9 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-4 file:py-1.5 file:text-sm file:text-ink"
                                            />
                                            <TextInput
                                                value={block.url ?? ''}
                                                onChange={(e) => update(index, { url: e.target.value })}
                                                placeholder="https://youtu.be/… ou https://…/mot-accueil.mp3"
                                            />
                                        </>
                                    )}
                                    <Textarea
                                        value={block.body ?? ''}
                                        onChange={(e) => update(index, { body: e.target.value })}
                                        rows={2}
                                        placeholder="Quelques mots avant le bouton (optionnel)"
                                    />
                                    <p className="text-xs text-ink-soft">
                                        Enregistrez votre mot et déposez-le ici (MP3, M4A, WAV, MP4, WebM — 40 Mo au plus), ou
                                        collez une adresse YouTube, Vimeo, ou celle d'un fichier hébergé ailleurs. Rien ne se
                                        charge avant que l'invité ne clique.
                                    </p>
                                </div>
                            )}

                            {block.type === 'video' && (
                                <div>
                                    <InputLabel htmlFor={`url_${block.id}`}>Adresse de la vidéo (YouTube ou Vimeo)</InputLabel>
                                    <TextInput
                                        id={`url_${block.id}`}
                                        value={block.url ?? ''}
                                        onChange={(changeEvent) => update(index, { url: changeEvent.target.value })}
                                        placeholder="https://www.youtube.com/watch?v=…"
                                    />
                                </div>
                            )}

                            {block.type === 'gallery' && (
                                <div className="space-y-3">
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        {(block.items ?? []).map((item, itemIndex) => (
                                            <div key={itemIndex} className="rounded-control border border-line p-3">
                                                {item.url && <img src={item.url} alt="" className="mb-2 aspect-square w-full rounded-control object-cover" />}
                                                <input
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp"
                                                    onChange={(changeEvent) => {
                                                        const file = changeEvent.target.files?.[0];

                                                        if (file) {
                                                            void uploadItemImage(index, itemIndex, file);
                                                        }
                                                    }}
                                                    className="mb-2 block w-full text-xs text-ink-soft file:mr-2 file:min-h-8 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-3 file:py-1 file:text-xs file:text-ink"
                                                />
                                                <TextInput
                                                    value={item.description ?? ''}
                                                    onChange={(changeEvent) => updateItem(index, itemIndex, { description: changeEvent.target.value })}
                                                    placeholder="Légende (optionnel)"
                                                    aria-label="Légende de la photo"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => update(index, { items: (block.items ?? []).filter((_, j) => j !== itemIndex) })}
                                                    className="mt-2 text-xs text-danger underline hover:no-underline"
                                                >
                                                    Retirer
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => update(index, { items: [...(block.items ?? []), {}] })}
                                        className={SMALL_BUTTON}
                                    >
                                        + Ajouter une photo
                                    </button>
                                </div>
                            )}

                            {block.type === 'program' && (
                                <label className="mb-4 flex cursor-pointer items-start gap-3">
                                    <Checkbox
                                        checked={block.showIcons ?? false}
                                        onChange={(changeEvent) => update(index, { showIcons: changeEvent.target.checked })}
                                        className="mt-1"
                                    />
                                    <span>
                                        <span className="block text-sm text-ink">Afficher une illustration par moment</span>
                                        <span className="block text-xs text-ink-soft">
                                            Un dessin au trait au-dessus de chaque heure, sur la page comme sur le PDF.
                                        </span>
                                    </span>
                                </label>
                            )}

                            {(block.type === 'program' || block.type === 'faq' || block.type === 'details') && (
                                <div className="space-y-3">
                                    {(block.items ?? []).map((item, itemIndex) => (
                                        <div key={itemIndex} className="rounded-control border border-line p-3">
                                            {block.type === 'program' ? (
                                                <div className="grid gap-3 sm:grid-cols-[6rem_1fr]">
                                                    <div className="space-y-2">
                                                        <TextInput
                                                            value={item.time ?? ''}
                                                            onChange={(changeEvent) => updateItem(index, itemIndex, { time: changeEvent.target.value })}
                                                            placeholder="18h00"
                                                            aria-label="Heure"
                                                        />
                                                        {block.showIcons && (
                                                            item.path ? (
                                                                // L'illustration déposée remplace le dessin au trait.
                                                                <div className="space-y-1">
                                                                    <img src={item.url} alt="" className="h-10 w-10 object-contain" />
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => updateItem(index, itemIndex, { path: undefined, url: undefined })}
                                                                        className="text-xs text-danger underline hover:no-underline"
                                                                    >
                                                                        Retirer
                                                                    </button>
                                                                </div>
                                                            ) : (
                                                                <div className="space-y-2">
                                                                    <Select
                                                                        value={item.icon ?? 'etoile'}
                                                                        onChange={(changeEvent) => updateItem(index, itemIndex, { icon: changeEvent.target.value })}
                                                                        aria-label="Illustration"
                                                                    >
                                                                        {PROGRAMME_ICONS.map((icon) => (
                                                                            <option key={icon.value} value={icon.value}>
                                                                                {icon.label}
                                                                            </option>
                                                                        ))}
                                                                    </Select>
                                                                    <input
                                                                        type="file"
                                                                        accept="image/png,image/jpeg,image/webp"
                                                                        aria-label="Illustration personnelle"
                                                                        onChange={(changeEvent) => {
                                                                            const file = changeEvent.target.files?.[0];

                                                                            if (file) {
                                                                                void uploadItemImage(index, itemIndex, file);
                                                                            }
                                                                        }}
                                                                        className="block w-full text-xs text-ink-soft file:mr-2 file:min-h-8 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-2 file:py-1 file:text-xs file:text-ink"
                                                                    />
                                                                </div>
                                                            )
                                                        )}
                                                    </div>
                                                    <div className="space-y-2">
                                                        <TextInput
                                                            value={item.title ?? ''}
                                                            onChange={(changeEvent) => updateItem(index, itemIndex, { title: changeEvent.target.value })}
                                                            placeholder="Accueil des invités"
                                                            aria-label="Intitulé"
                                                        />
                                                        <Textarea
                                                            value={item.description ?? ''}
                                                            onChange={(changeEvent) => updateItem(index, itemIndex, { description: changeEvent.target.value })}
                                                            rows={2}
                                                            placeholder="Détail (optionnel)"
                                                            aria-label="Détail"
                                                        />
                                                    </div>
                                                </div>
                                            ) : block.type === 'details' ? (
                                                <div className="space-y-2">
                                                    <TextInput
                                                        value={item.title ?? ''}
                                                        onChange={(changeEvent) => updateItem(index, itemIndex, { title: changeEvent.target.value })}
                                                        placeholder="Thème"
                                                        aria-label="Intitulé de la carte"
                                                    />
                                                    <TextInput
                                                        value={item.time ?? ''}
                                                        onChange={(changeEvent) => updateItem(index, itemIndex, { time: changeEvent.target.value })}
                                                        placeholder="Chic et élégant"
                                                        aria-label="Valeur"
                                                    />
                                                    <Textarea
                                                        value={item.description ?? ''}
                                                        onChange={(changeEvent) => updateItem(index, itemIndex, { description: changeEvent.target.value })}
                                                        rows={2}
                                                        placeholder="Précision (optionnel)"
                                                        aria-label="Précision"
                                                    />
                                                </div>
                                            ) : (
                                                <div className="space-y-2">
                                                    <TextInput
                                                        value={item.question ?? ''}
                                                        onChange={(changeEvent) => updateItem(index, itemIndex, { question: changeEvent.target.value })}
                                                        placeholder="Y a-t-il un parking ?"
                                                        aria-label="Question"
                                                    />
                                                    <Textarea
                                                        value={item.answer ?? ''}
                                                        onChange={(changeEvent) => updateItem(index, itemIndex, { answer: changeEvent.target.value })}
                                                        rows={2}
                                                        placeholder="Oui, à côté de la salle."
                                                        aria-label="Réponse"
                                                    />
                                                </div>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    update(index, { items: (block.items ?? []).filter((_, j) => j !== itemIndex) })
                                                }
                                                className="mt-2 text-sm text-danger underline hover:no-underline"
                                            >
                                                Retirer cette ligne
                                            </button>
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={() => update(index, { items: [...(block.items ?? []), {}] })}
                                        className="text-sm text-accent underline hover:no-underline"
                                    >
                                        + Ajouter une ligne
                                    </button>
                                </div>
                            )}

                            {block.type === 'venue' && (
                                <p className="text-sm text-ink-soft">
                                    Le lieu et son plan viennent des paramètres de l'événement. Rien à saisir ici.
                                </p>
                            )}

                            {block.type === 'countdown' && (
                                <p className="text-sm text-ink-soft">Le décompte se fait jusqu'à la date de début de l'événement.</p>
                            )}
                        </div>
                    );
                })
            )}

            <div className="mt-8 flex flex-wrap items-center gap-4">
                <Button type="button" onClick={handleSave} disabled={saving} className="w-auto px-8 py-2">
                    {saving ? 'Enregistrement…' : 'Enregistrer'}
                </Button>
                {saved && <span className="text-sm text-success">Page enregistrée.</span>}
            </div>
        </EventLayout>
    );
}
