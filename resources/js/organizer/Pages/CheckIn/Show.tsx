import { Head } from '@inertiajs/react';
import QrScanner from 'qr-scanner';
import { useEffect, useMemo, useRef, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import Table from '../../Components/Table';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface Guest {
    guest_type: 'attendee' | 'ticket';
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    checked_in: boolean;
    checked_in_at: string | null;
}

interface Props {
    event: { id: number; title: string };
    guests: Guest[];
}

interface ScanResponse {
    status: 'accepted' | 'conflict';
    guest: Guest | null;
}

// Une douchette USB tape le contenu du QR (le JWT du billet) puis Entrée,
// exactement comme un clavier : ce motif distingue un scan d'une recherche
// texte tapée à la main, sans matériel ni bibliothèque spécifique.
const QR_TOKEN_PATTERN = /^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/;

export default function Show({ event, guests: initialGuests }: Props) {
    const [guests, setGuests] = useState<Guest[]>(initialGuests);
    const [search, setSearch] = useState('');
    const [feedback, setFeedback] = useState<{ type: 'success' | 'conflict' | 'error'; message: string } | null>(null);
    const [webcamActive, setWebcamActive] = useState(false);
    const [webcamError, setWebcamError] = useState<string | null>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const processingRef = useRef(false);

    const checkedInCount = useMemo(() => guests.filter((guest) => guest.checked_in).length, [guests]);

    const filteredGuests = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (term === '') {
            return guests;
        }

        return guests.filter((guest) =>
            [guest.name, guest.email, guest.phone].some((value) => value?.toLowerCase().includes(term)),
        );
    }, [guests, search]);

    function applyGuestUpdate(guest: Guest | null) {
        if (guest === null) {
            return;
        }

        setGuests((current) => current.map((existing) =>
            existing.guest_type === guest.guest_type && existing.id === guest.id ? guest : existing,
        ));
    }

    async function submitScan(token: string) {
        if (processingRef.current) {
            return;
        }

        processingRef.current = true;

        try {
            const response = await window.axios.post<ScanResponse>(`/events/${event.id}/check-in/scan`, { token });
            applyGuestUpdate(response.data.guest);
            setFeedback(
                response.data.status === 'accepted'
                    ? { type: 'success', message: `${response.data.guest?.name ?? 'Invité'} — entrée acceptée.` }
                    : { type: 'conflict', message: `${response.data.guest?.name ?? 'Invité'} — déjà enregistré.` },
            );
        } catch (error) {
            const message =
                (error as { response?: { data?: { error?: string } } }).response?.data?.error ??
                'Billet invalide ou illisible.';
            setFeedback({ type: 'error', message });
        } finally {
            window.setTimeout(() => {
                processingRef.current = false;
            }, 1500);
        }
    }

    async function submitRecord(guest: Guest) {
        try {
            const response = await window.axios.post<ScanResponse>(`/events/${event.id}/check-in/record`, {
                guest_type: guest.guest_type,
                id: guest.id,
            });
            applyGuestUpdate(response.data.guest);
            setFeedback(
                response.data.status === 'accepted'
                    ? { type: 'success', message: `${guest.name} — entrée acceptée.` }
                    : { type: 'conflict', message: `${guest.name} — déjà enregistré.` },
            );
        } catch {
            setFeedback({ type: 'error', message: "Impossible d'enregistrer ce check-in." });
        }
    }

    function handleSearchKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
        if (event.key !== 'Enter') {
            return;
        }

        const value = search.trim();

        if (QR_TOKEN_PATTERN.test(value)) {
            setSearch('');
            void submitScan(value);
        }
    }

    async function startWebcam() {
        setWebcamError(null);

        if (!videoRef.current) {
            return;
        }

        try {
            scannerRef.current = new QrScanner(
                videoRef.current,
                (result) => void submitScan(result.data),
                { highlightScanRegion: true, highlightCodeOutline: true },
            );
            await scannerRef.current.start();
            setWebcamActive(true);
        } catch {
            setWebcamError("Impossible d'accéder à la caméra. Utilisez la recherche ou une douchette USB.");
        }
    }

    function stopWebcam() {
        scannerRef.current?.stop();
        scannerRef.current?.destroy();
        scannerRef.current = null;
        setWebcamActive(false);
    }

    useEffect(() => () => {
        scannerRef.current?.stop();
        scannerRef.current?.destroy();
    }, []);

    return (
        <OrganizerLayout title="Check-in" eyebrow={event.title}>
            <Head title="Check-in" />

            <div className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl">Check-in</h1>
                    <p className="text-ink-soft">
                        {checkedInCount} / {guests.length} invités enregistrés
                    </p>
                </div>
                <Button variant="secondary" className="w-auto" onClick={webcamActive ? stopWebcam : startWebcam}>
                    {webcamActive ? 'Arrêter la caméra' : 'Scanner avec la caméra'}
                </Button>
            </div>

            {webcamActive && (
                <div className="mb-6 max-w-md overflow-hidden rounded-card ring-1 ring-line">
                    <video ref={videoRef} className="w-full" />
                </div>
            )}

            {webcamError && <p className="mb-6 text-sm text-danger">{webcamError}</p>}

            {feedback && (
                <div
                    className={`mb-6 rounded-card px-6 py-4 text-sm ${
                        feedback.type === 'success'
                            ? 'bg-success-bg text-success'
                            : feedback.type === 'conflict'
                              ? 'bg-danger-bg text-danger'
                              : 'bg-bg-deep text-ink-soft'
                    }`}
                >
                    {feedback.message}
                </div>
            )}

            <div className="mb-6 max-w-md">
                <InputLabel htmlFor="search">Recherche (nom, e-mail, téléphone) ou douchette USB</InputLabel>
                <TextInput
                    id="search"
                    autoFocus
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={handleSearchKeyDown}
                    placeholder="Alice Kouassi, alice@example.com..."
                />
            </div>

            <Table
                columns={[
                    { key: 'name', header: 'Invité', render: (guest: Guest) => guest.name },
                    { key: 'contact', header: 'Contact', render: (guest: Guest) => guest.email ?? guest.phone ?? '—' },
                    {
                        key: 'type',
                        header: 'Type',
                        render: (guest: Guest) => (guest.guest_type === 'attendee' ? 'Inscription' : 'Billet'),
                    },
                    {
                        key: 'status',
                        header: 'Statut',
                        render: (guest: Guest) =>
                            guest.checked_in ? <Badge variant="success">Enregistré</Badge> : <Badge>En attente</Badge>,
                    },
                    {
                        key: 'action',
                        header: '',
                        render: (guest: Guest) => (
                            <Button
                                variant="secondary"
                                className="w-auto"
                                disabled={guest.checked_in}
                                onClick={() => void submitRecord(guest)}
                            >
                                Enregistrer
                            </Button>
                        ),
                    },
                ]}
                rows={filteredGuests}
                rowKey={(guest) => `${guest.guest_type}-${guest.id}`}
                emptyMessage="Aucun invité ne correspond à cette recherche."
            />
        </OrganizerLayout>
    );
}
