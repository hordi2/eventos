import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedProps } from '../types';

export default function Dashboard() {
    const { auth } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <Head title="Tableau de bord" />

            <div className="mx-auto max-w-3xl space-y-4">
                <h1 className="text-2xl font-semibold text-gray-900">Bienvenue, {auth.user?.name}</h1>
                <p className="text-gray-600">Ton tableau de bord EventOS arrive dans les prochains tickets.</p>

                <Link href="/logout" method="post" as="button" className="text-sm text-indigo-600 hover:text-indigo-500">
                    Se déconnecter
                </Link>
            </div>
        </div>
    );
}
