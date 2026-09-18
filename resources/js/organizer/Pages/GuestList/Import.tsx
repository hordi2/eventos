import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState, type DragEvent, type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import SenderAgreementModal from '../../Components/GuestList/SenderAgreementModal';
import InputError from '../../Components/InputError';
import EventLayout from '../../Layouts/EventLayout';

interface ImportColumn {
    name: string;
    requirement: string;
    role: string;
}

interface RecentImport {
    id: number;
    filename: string;
    status: string;
    accepted: number;
    rejected: number;
    createdAt: string | null;
}

interface GuestImportPageProps {
    event: { id: number; title: string };
    columns: ImportColumn[];
    needsAgreement: boolean;
    recentImports: RecentImport[];
}

const STATUS_LABELS: Record<string, string> = {
    mapping: 'Colonnes à vérifier',
    queued: 'En attente',
    processing: 'En cours',
    completed: 'Terminé',
    failed: 'Échec',
};

const TIPS = [
    "Gardez les en-têtes sur la première ligne : Itaza reconnaît ceux du modèle, et vous vérifierez la correspondance des colonnes à l'étape suivante.",
    "Chaque invité a besoin d'un nom complet (prénom et nom) et/ou d'une adresse e-mail.",
    "L'e-mail n'est indispensable que pour inviter par e-mail ou permettre à l'invité de se retrouver par son adresse.",
    'Donnez la même valeur de « Groupe » aux personnes qui répondent ensemble : un couple, une famille, une délégation.',
    '« Accompagnants autorisés » accepte un nombre jusqu’à 20, ou « illimité ». Vide ou 0 : aucun.',
    'Réimporter un fichier met les invités déjà présents à jour, sans les ajouter une seconde fois.',
];

const MAX_BYTES = 10 * 1024 * 1024;

function formatDate(iso: string | null): string {
    return iso ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '';
}

export default function Import({ event, columns, needsAgreement, recentImports }: GuestImportPageProps) {
    const [agreementOpen, setAgreementOpen] = useState(needsAgreement);
    const [over, setOver] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors } = useForm<{ file: File | null }>({ file: null });
    const formErrors = errors as Record<string, string | undefined>;
    const listUrl = `/events/${event.id}/guest-list`;

    function choose(file: File | undefined) {
        setLocalError(null);

        if (!file) {
            return;
        }

        if (!/\.(xlsx|csv)$/i.test(file.name)) {
            setLocalError('Choisissez un fichier Excel (.xlsx) ou CSV (.csv).');

            return;
        }

        if (file.size > MAX_BYTES) {
            setLocalError('Ce fichier dépasse 10 Mo : découpez votre liste en plusieurs fichiers.');

            return;
        }

        setData('file', file);
    }

    function handleDrop(dropEvent: DragEvent<HTMLLabelElement>) {
        dropEvent.preventDefault();
        setOver(false);
        choose(dropEvent.dataTransfer.files[0]);
    }

    function submit(formEvent: FormEvent) {
        formEvent.preventDefault();

        if (needsAgreement) {
            setAgreementOpen(true);

            return;
        }

        post(`${listUrl}/import`, { forceFormData: true });
    }

    return (
        <EventLayout title="Importer des invités" wide>
            <Head title={`Importer des invités — ${event.title}`} />

            <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Importez un fichier Excel (.xlsx) ou CSV. Vous vérifierez la correspondance des colonnes à l'étape suivante, avant que quiconque
                    soit ajouté.
                </p>
                <Link href={listUrl} className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase hover:text-ink">
                    Retour à la liste
                </Link>
            </div>

            <div className="mb-10 grid gap-4 lg:grid-cols-2">
                <a
                    href={`${listUrl}/template`}
                    className="group flex gap-4 rounded-card border border-line bg-bg p-6 transition-colors hover:border-ink"
                    download
                >
                    <span aria-hidden="true" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-control bg-bg-alt text-lg text-ink">
                        ⇩
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="flex flex-wrap items-center gap-2">
                            <span className="text-ink">Télécharger le modèle Excel</span>
                            <Badge variant="success">Conseillé</Badge>
                        </span>
                        <span className="mt-1 block text-sm text-ink-soft">
                            Les bonnes colonnes, prêtes à remplir, et une feuille « Mode d'emploi » avec un exemple.
                        </span>
                    </span>
                </a>

                <form onSubmit={submit} className="rounded-card border border-line bg-bg p-6">
                    <label
                        htmlFor="guest_file"
                        onDragOver={(dragEvent) => {
                            dragEvent.preventDefault();
                            setOver(true);
                        }}
                        onDragLeave={() => setOver(false)}
                        onDrop={handleDrop}
                        className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-control border-2 border-dashed px-4 py-6 text-center transition ${
                            over ? 'border-ink bg-bg-alt' : 'border-line hover:border-ink'
                        }`}
                    >
                        <span className="text-sm text-ink">{data.file ? data.file.name : 'Glissez votre fichier ici ou cliquez pour le choisir'}</span>
                        <span className="text-xs text-ink-soft">Excel (.xlsx) ou CSV, 10 Mo au plus</span>
                        <input
                            ref={inputRef}
                            id="guest_file"
                            type="file"
                            accept=".xlsx,.csv"
                            className="sr-only"
                            onChange={(changeEvent) => choose(changeEvent.target.files?.[0])}
                        />
                    </label>
                    <InputError message={localError ?? errors.file ?? formErrors.agreement} />
                    <Button type="submit" disabled={processing || data.file === null} className="mt-4">
                        {processing ? 'Envoi…' : 'Continuer'}
                    </Button>
                </form>
            </div>

            <section className="mb-10">
                <h2 className="mb-1 font-serif text-xl text-ink italic">Colonnes attendues</h2>
                <p className="mb-4 text-sm text-ink-soft">La première ligne de votre fichier porte les en-têtes de colonne.</p>
                <div className="overflow-x-auto rounded-card border border-line bg-bg">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="border-b border-line font-label text-[11px] tracking-[0.12em] text-ink-soft uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-3 font-normal">
                                    Colonne
                                </th>
                                <th scope="col" className="px-4 py-3 font-normal">
                                    Exigence
                                </th>
                                <th scope="col" className="px-4 py-3 font-normal">
                                    À quoi elle sert
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {columns.map((column) => (
                                <tr key={column.name} className="border-b border-line align-top last:border-0">
                                    <td className="px-4 py-3 whitespace-nowrap text-ink">{column.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {column.requirement === 'Facultatif' ? (
                                            <Badge>Facultatif</Badge>
                                        ) : (
                                            <span className="inline-flex rounded-pill bg-[#fff1cc] px-2.5 py-1 font-label text-[11px] tracking-[0.08em] text-[#7a4f00] uppercase">
                                                {column.requirement}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-ink-soft">{column.role}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-10 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-xl text-ink italic">Conseils</h2>
                <ul className="space-y-3">
                    {TIPS.map((tip) => (
                        <li key={tip} className="flex gap-3 text-sm text-ink-soft">
                            <span aria-hidden="true" className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-accent" />
                            {tip}
                        </li>
                    ))}
                </ul>
            </section>

            {recentImports.length > 0 && (
                <section>
                    <h2 className="mb-4 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Imports récents</h2>
                    <ul className="divide-y divide-line rounded-card border border-line bg-bg">
                        {recentImports.map((recent) => (
                            <li key={recent.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                                <span className="min-w-0">
                                    <span className="block truncate text-ink">{recent.filename}</span>
                                    <span className="text-xs text-ink-soft">
                                        {formatDate(recent.createdAt)} · {recent.accepted} ajoutés · {recent.rejected} refusés
                                    </span>
                                </span>
                                <span className="flex items-center gap-3">
                                    <Badge variant={recent.status === 'completed' ? 'success' : recent.status === 'failed' ? 'danger' : 'neutral'}>
                                        {STATUS_LABELS[recent.status] ?? recent.status}
                                    </Badge>
                                    <Link
                                        href={recent.status === 'mapping' ? `/contact-imports/${recent.id}/mapping` : `/contact-imports/${recent.id}`}
                                        className="text-ink hover:underline"
                                    >
                                        {recent.status === 'mapping' ? 'Reprendre' : 'Rapport'}
                                    </Link>
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {needsAgreement && <SenderAgreementModal open={agreementOpen} onClose={() => setAgreementOpen(false)} />}
        </EventLayout>
    );
}
