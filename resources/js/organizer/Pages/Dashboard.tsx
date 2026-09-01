import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedProps } from '../types';

export default function Dashboard({ isOwner }: { isOwner: boolean }) {
    const { auth } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen bg-bg-alt">
            <Head title="Tableau de bord" />

            <header className="border-b border-line bg-bg">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                    <img src="/images/logo.png" alt="Itaza Invitation" className="h-9 w-auto" />
                    <div className="flex items-center gap-6">
                        {isOwner && (
                            <Link href="/audit-log" className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase hover:text-ink">
                                Journal d'audit
                            </Link>
                        )}
                        <Link href="/logout" method="post" as="button" className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase hover:text-ink">
                            Se déconnecter
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-6 py-16">
                <p className="font-label text-xs tracking-[0.28em] text-accent uppercase">Tableau de bord</p>
                <h1 className="mt-4 font-serif text-3xl font-medium text-ink italic">Bienvenue, {auth.user?.name}</h1>
                <p className="mt-4 max-w-md text-ink-soft">
                    La gestion de tes événements arrive dans les prochains tickets — création, invitations, billetterie,
                    check-in.
                </p>
            </main>
        </div>
    );
}
