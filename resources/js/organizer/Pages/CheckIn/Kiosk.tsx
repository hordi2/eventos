import { Head, router } from '@inertiajs/react';
import QrScanner from 'qr-scanner';
import { useEffect, useRef, useState, type FormEvent } from 'react';

interface Guest {
    guest_type: string;
    id: number;
    name: string;
    checked_in: boolean;
}

interface ScanResponse {
    status: string;
    guest: { guest_type: string; id: number; name: string; checked_in: boolean } | null;
}

interface KioskPageProps {
    event: { id: number; title: string };
    organizationName: string;
    checkInUrl: string;
}

const BIG_INPUT = 'w-full rounded-control border border-line bg-bg px-5 py-4 text-xl text-ink';

/**
 * Kiosque d'accueil : l'invité se pointe lui-même sur une tablette laissée
 * en libre-service. Il présente son QR code, ou cherche son nom, puis son
 * badge part à l'impression.
 */
export default function Kiosk({ event, organizationName, checkInUrl }: KioskPageProps) {
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<Guest[]>([]);
    const [welcome, setWelcome] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [exiting, setExiting] = useState(false);
    const [exitCode, setExitCode] = useState('');
    const [exitError, setExitError] = useState<string | null>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const printRef = useRef<HTMLIFrameElement>(null);
    const busyRef = useRef(false);

    // Le QR est lu en continu : l'invité n'a rien à toucher.
    useEffect(() => {
        if (videoRef.current === null) {
            return;
        }

        const scanner = new QrScanner(videoRef.current, (result) => void checkIn({ token: result.data }), {
            highlightScanRegion: true,
            maxScansPerSecond: 2,
        });
        scannerRef.current = scanner;
        scanner.start().catch(() => setError("La caméra n'est pas disponible : cherchez votre nom ci-dessous."));

        return () => {
            scanner.stop();
            scanner.destroy();
            scannerRef.current = null;
        };
    }, []);

    // L'écran de bienvenue s'efface tout seul : le suivant trouve le kiosque prêt.
    useEffect(() => {
        if (welcome === null) {
            return;
        }

        const timer = window.setTimeout(() => {
            setWelcome(null);
            setSearch('');
            setResults([]);
        }, 8000);

        return () => window.clearTimeout(timer);
    }, [welcome]);

    async function checkIn(payload: { token: string } | { guest_type: string; id: number }) {
        if (busyRef.current) {
            return;
        }

        busyRef.current = true;
        setError(null);

        const url = 'token' in payload ? `/events/${event.id}/check-in/scan` : `/events/${event.id}/check-in/record`;

        try {
            const response = await window.axios.post<ScanResponse>(url, payload);
            const guest = response.data.guest;

            if (guest === null) {
                setError("Nous n'avons pas trouvé cette personne sur la liste.");

                return;
            }

            setWelcome(guest.name);
            print(guest);
        } catch (failure) {
            const message = (failure as { response?: { data?: { error?: string } } }).response?.data?.error;
            setError(message ?? "Ce QR code n'a pas pu être lu. Demandez de l'aide à l'accueil.");
        } finally {
            busyRef.current = false;
        }
    }

    function print(guest: { guest_type: string; id: number }) {
        const frame = printRef.current;

        if (frame === null) {
            return;
        }

        frame.src = `/events/${event.id}/badges/${guest.guest_type}/${guest.id}`;
        frame.onload = () => frame.contentWindow?.print();
    }

    async function runSearch(term: string) {
        setSearch(term);

        if (term.trim().length < 3) {
            setResults([]);

            return;
        }

        const response = await window.axios.get<{ guests: Guest[] }>(`/events/${event.id}/kiosk/search`, { params: { q: term } });
        setResults(response.data.guests);
    }

    function submitExit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        router.post(`/events/${event.id}/kiosk/exit`, { code: exitCode }, {
            onError: (errors) => setExitError(errors.code ?? 'Ce code ne correspond pas.'),
            onSuccess: () => setExiting(false),
        });
    }

    return (
        <div className="min-h-screen bg-bg-alt px-6 py-10">
            <Head title={`Accueil — ${event.title}`} />

            <iframe ref={printRef} title="Badge" className="hidden" />

            <div className="mx-auto max-w-2xl text-center">
                <p className="mb-1 text-sm text-ink-soft">{organizationName}</p>
                <h1 className="mb-8 font-serif text-3xl italic text-ink">{event.title}</h1>

                {welcome !== null ? (
                    <div className="rounded-card bg-bg px-6 py-16 ring-1 ring-line">
                        <p className="mb-3 font-serif text-4xl italic text-ink">Bienvenue {welcome}</p>
                        <p className="text-ink-soft">Votre badge s'imprime. Bonne soirée !</p>
                    </div>
                ) : (
                    <>
                        <div className="mb-8 overflow-hidden rounded-card bg-bg ring-1 ring-line">
                            <video ref={videoRef} className="aspect-video w-full object-cover" muted playsInline />
                            <p className="px-4 py-3 text-ink-soft">Présentez le QR code de votre invitation à la caméra.</p>
                        </div>

                        <div className="rounded-card bg-bg p-6 text-left ring-1 ring-line">
                            <label htmlFor="kiosk_search" className="mb-2 block text-lg text-ink">
                                Vous n'avez pas votre QR code ? Tapez votre nom.
                            </label>
                            <input
                                id="kiosk_search"
                                type="text"
                                value={search}
                                onChange={(changeEvent) => void runSearch(changeEvent.target.value)}
                                autoComplete="off"
                                className={BIG_INPUT}
                                placeholder="Nom ou prénom"
                            />

                            <ul className="mt-4 space-y-2">
                                {results.map((guest) => (
                                    <li key={`${guest.guest_type}-${guest.id}`}>
                                        <button
                                            type="button"
                                            onClick={() => void checkIn({ guest_type: guest.guest_type, id: guest.id })}
                                            className="flex min-h-14 w-full items-center justify-between rounded-control border border-line px-5 py-3 text-left text-lg text-ink hover:border-ink"
                                        >
                                            {guest.name}
                                            {guest.checked_in && <span className="text-sm text-ink-soft">déjà arrivé</span>}
                                        </button>
                                    </li>
                                ))}
                            </ul>

                            {search.trim().length >= 3 && results.length === 0 && (
                                <p className="mt-4 text-ink-soft">Aucun nom ne correspond. Demandez de l'aide à l'accueil.</p>
                            )}
                        </div>
                    </>
                )}

                {error !== null && (
                    <p role="alert" className="mt-6 rounded-card bg-danger-bg px-5 py-4 text-danger ring-1 ring-danger/30">
                        {error}
                    </p>
                )}

                <div className="mt-10">
                    {exiting ? (
                        <form onSubmit={submitExit} className="mx-auto flex max-w-xs flex-col gap-3">
                            <label htmlFor="exit_code" className="text-sm text-ink-soft">
                                Code à quatre chiffres pour quitter le kiosque
                            </label>
                            <input
                                id="exit_code"
                                type="password"
                                inputMode="numeric"
                                maxLength={4}
                                value={exitCode}
                                onChange={(changeEvent) => setExitCode(changeEvent.target.value)}
                                className="rounded-control border border-line px-4 py-2 text-center text-ink"
                                autoFocus
                            />
                            {exitError !== null && <p className="text-sm text-danger">{exitError}</p>}
                            <div className="flex justify-center gap-4 text-sm">
                                <button type="submit" className="text-ink underline hover:no-underline">
                                    Quitter
                                </button>
                                <button type="button" onClick={() => setExiting(false)} className="text-ink-soft underline hover:no-underline">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    ) : (
                        <button type="button" onClick={() => setExiting(true)} className="text-xs text-ink-soft underline hover:no-underline">
                            Quitter le mode kiosque
                        </button>
                    )}
                    <a href={checkInUrl} className="sr-only">
                        Accueil des invités
                    </a>
                </div>
            </div>
        </div>
    );
}
