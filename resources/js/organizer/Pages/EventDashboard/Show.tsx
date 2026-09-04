import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BarChart from '../../Components/BarChart';
import LineChart from '../../Components/LineChart';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface RegistrationPoint {
    date: string;
    cumulative: number;
}

interface ArrivalPoint {
    hour: string;
    count: number;
}

interface Stats {
    confirmed_count: number;
    present_count: number;
    presence_rate: number;
    registration_curve: RegistrationPoint[];
    arrival_curve: ArrivalPoint[];
}

interface Props {
    event: { id: number; title: string };
    stats: Stats;
}

export default function Show({ event, stats: initialStats }: Props) {
    const [stats, setStats] = useState<Stats>(initialStats);
    const [connected, setConnected] = useState(false);

    useEffect(() => {
        const source = new EventSource(`/events/${event.id}/dashboard/stream`);

        source.addEventListener('open', () => setConnected(true));
        source.addEventListener('error', () => setConnected(false));
        source.addEventListener('stats', (message) => {
            setConnected(true);
            setStats(JSON.parse((message as MessageEvent<string>).data) as Stats);
        });

        return () => source.close();
    }, [event.id]);

    return (
        <OrganizerLayout title="Tableau de bord" eyebrow={event.title}>
            <Head title="Tableau de bord" />

            <div className="mb-8 flex items-center justify-between">
                <h1 className="text-2xl">Tableau de bord</h1>
                <span className={`text-xs font-medium ${connected ? 'text-success' : 'text-ink-soft'}`}>
                    {connected ? '● Mise à jour en direct' : '○ Connexion…'}
                </span>
            </div>

            <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Invités confirmés" value={stats.confirmed_count} />
                <StatCard label="Présents" value={stats.present_count} />
                <StatCard label="Taux de présence" value={`${Math.round(stats.presence_rate * 100)} %`} />
            </div>

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Inscriptions cumulées</h2>
                <LineChart points={stats.registration_curve.map((point) => ({ label: point.date, value: point.cumulative }))} />
            </div>

            <div className="rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Arrivées par tranche horaire</h2>
                <BarChart bars={stats.arrival_curve.map((point) => ({ label: point.hour, value: point.count }))} />
            </div>
        </OrganizerLayout>
    );
}

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-card bg-bg p-6 ring-1 ring-line">
            <p className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">{label}</p>
            <p className="mt-2 text-3xl font-medium text-ink">{value}</p>
        </div>
    );
}
