import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useState } from 'react';
import EventIcon from '../Components/EventIcon';
import EventSidebar from '../Components/EventSidebar';
import EventStatusMenu from '../Components/EventStatusMenu';
import Footer from '../Components/Footer';
import UserMenu from '../Components/UserMenu';
import { type SharedProps } from '../types';
import OrganizerLayout from './OrganizerLayout';

interface EventLayoutProps {
    title: string;
    // Titre de l'événement, affiché par le fil d'Ariane : conservé pour que
    // les pages existantes gardent la même signature qu'OrganizerLayout.
    eyebrow?: string;
}

const STATUS_MESSAGES: Record<string, string> = {
    'event-published': 'Événement publié : son lien public accepte les inscriptions.',
    'event-unpublished': "Événement repassé en « Inédit » : sa page publique et les inscriptions sont fermées.",
};

/**
 * Mise en page de toutes les pages d'un événement : menu latéral, fil
 * d'Ariane, menu de statut et aperçu. Hors d'une page d'événement (aucune
 * navigation partagée), elle retombe sur la mise en page organisateur.
 */
export default function EventLayout({ title, eyebrow, children }: PropsWithChildren<EventLayoutProps>) {
    const { eventNav, auth, settingsAccess, organizations, flash, errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    if (eventNav === null) {
        return (
            <OrganizerLayout title={title} eyebrow={eyebrow}>
                {children}
            </OrganizerLayout>
        );
    }

    const previewHref = eventNav.publicUrl ?? eventNav.previewUrl;

    return (
        <div className="flex min-h-screen bg-bg-alt">
            <EventSidebar nav={eventNav} open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 border-b border-line bg-bg">
                    <div className="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Ouvrir le menu de l'événement"
                            className="text-ink-soft hover:text-ink lg:hidden"
                        >
                            <EventIcon name="menu" />
                        </button>

                        <nav aria-label="Fil d'Ariane" className="flex min-w-0 flex-1 items-center gap-2 text-sm text-ink-soft">
                            <Link href="/dashboard" aria-label="Tableau de bord" className="shrink-0 hover:text-ink">
                                <EventIcon name="home" className="h-4 w-4" />
                            </Link>
                            <span aria-hidden="true">/</span>
                            <span className="hidden truncate md:inline">{eventNav.organizationName}</span>
                            <span aria-hidden="true" className="hidden md:inline">
                                /
                            </span>
                            {eventNav.links.checklist ? (
                                <Link href={eventNav.links.checklist} className="max-w-[12rem] truncate hover:text-ink">
                                    {eventNav.title}
                                </Link>
                            ) : (
                                <span className="max-w-[12rem] truncate">{eventNav.title}</span>
                            )}
                            <span aria-hidden="true">/</span>
                            <span className="truncate text-ink">{title}</span>
                        </nav>

                        <div className="flex items-center gap-3">
                            <EventStatusMenu nav={eventNav} />
                            {previewHref && (
                                <a
                                    href={previewHref}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="hidden items-center gap-2 rounded-pill border border-line bg-bg px-4 py-2 text-sm text-ink hover:border-ink sm:inline-flex"
                                >
                                    <EventIcon name="eye" className="h-4 w-4" />
                                    Aperçu
                                </a>
                            )}
                            {auth.user && (
                                <UserMenu
                                    name={auth.user.name}
                                    canManageBilling={settingsAccess.billing}
                                    canShareEvents={settingsAccess.eventSharing}
                                    organizations={organizations}
                                />
                            )}
                        </div>
                    </div>
                </header>

                <main className="flex-1 px-4 py-10 sm:px-8">
                    <div className="mx-auto max-w-5xl">
                        {flash.status && STATUS_MESSAGES[flash.status] && (
                            <div className="mb-6 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                                {STATUS_MESSAGES[flash.status]}
                            </div>
                        )}
                        {errors.status && (
                            <div className="mb-6 rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">{errors.status}</div>
                        )}

                        <h1 className="font-serif text-3xl font-medium text-ink italic">{title}</h1>
                        <div className="mt-8">{children}</div>
                    </div>
                </main>

                <Footer />
            </div>
        </div>
    );
}
