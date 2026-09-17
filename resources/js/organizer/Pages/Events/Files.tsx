import { Head, router } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import EventLayout from '../../Layouts/EventLayout';

type ScanStatus = 'pending' | 'clean' | 'infected' | 'failed';

interface ReceivedFile {
    id: number;
    guestName: string;
    guestEmail: string;
    question: string;
    fileName: string;
    size: string;
    status: ScanStatus;
    statusLabel: string;
    receivedAt: string;
    downloadUrl: string | null;
    rescanUrl: string | null;
}

interface FilesPageProps {
    event: { id: number; title: string };
    files: ReceivedFile[];
}

const STATUS_VARIANTS: Record<ScanStatus, 'neutral' | 'success' | 'danger'> = {
    pending: 'neutral',
    clean: 'success',
    infected: 'danger',
    failed: 'danger',
};

const HEADER_CELL = 'px-4 py-3 font-normal';

export default function Files({ event, files }: FilesPageProps) {
    return (
        <EventLayout title="Fichiers reçus">
            <Head title={`Fichiers reçus — ${event.title}`} />

            <p className="mb-8 max-w-2xl text-sm text-ink-soft">
                Les fichiers envoyés par vos invités avec la question « Fichier joint ». Chacun est vérifié par un antivirus : seuls les fichiers sains se
                téléchargent, et chaque téléchargement est inscrit au journal d'audit.
            </p>

            {files.length === 0 ? (
                <p className="text-sm text-ink-soft">Aucun fichier reçu pour l'instant.</p>
            ) : (
                <div className="overflow-x-auto rounded-card border border-line bg-bg">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b border-line font-label text-[11px] tracking-[0.12em] text-ink-soft uppercase">
                            <tr>
                                <th scope="col" className={HEADER_CELL}>
                                    Invité
                                </th>
                                <th scope="col" className={HEADER_CELL}>
                                    Question
                                </th>
                                <th scope="col" className={HEADER_CELL}>
                                    Fichier
                                </th>
                                <th scope="col" className={HEADER_CELL}>
                                    Analyse
                                </th>
                                <th scope="col" className={HEADER_CELL}>
                                    Reçu le
                                </th>
                                <th scope="col" className={HEADER_CELL}>
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {files.map((file) => {
                                const { downloadUrl, rescanUrl } = file;

                                return (
                                    <tr key={file.id} className="align-top">
                                        <td className="px-4 py-3">
                                            <span className="block font-medium text-ink">{file.guestName}</span>
                                            <span className="block text-xs text-ink-soft">{file.guestEmail}</span>
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft">{file.question}</td>
                                        <td className="px-4 py-3">
                                            <span className="block break-all text-ink">{file.fileName}</span>
                                            <span className="block text-xs text-ink-soft tabular-nums">{file.size}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={STATUS_VARIANTS[file.status]}>{file.statusLabel}</Badge>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-ink-soft tabular-nums">{file.receivedAt}</td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            {downloadUrl && (
                                                <a href={downloadUrl} className="font-medium text-ink underline underline-offset-2 hover:opacity-80">
                                                    Télécharger
                                                </a>
                                            )}
                                            {rescanUrl && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(rescanUrl, {}, { preserveScroll: true })}
                                                    className="font-medium text-ink underline underline-offset-2 hover:opacity-80"
                                                >
                                                    Relancer l'analyse
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </EventLayout>
    );
}
