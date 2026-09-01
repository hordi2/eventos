import { Head, Link, usePage } from '@inertiajs/react';
import OrganizerLayout from '../Layouts/OrganizerLayout';
import { type SharedProps } from '../types';

export default function Dashboard({ isOwner }: { isOwner: boolean }) {
    const { auth } = usePage<SharedProps>().props;

    return (
        <OrganizerLayout
            title={`Bienvenue, ${auth.user?.name}`}
            eyebrow="Tableau de bord"
            nav={
                isOwner && (
                    <Link href="/audit-log" className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase hover:text-ink">
                        Journal d'audit
                    </Link>
                )
            }
        >
            <Head title="Tableau de bord" />

            <p className="max-w-md text-ink-soft">
                La gestion de tes événements arrive dans les prochains tickets — création, invitations, billetterie,
                check-in.
            </p>
        </OrganizerLayout>
    );
}
