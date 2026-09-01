import { Link } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode } from 'react';

interface OrganizerLayoutProps {
    title: string;
    eyebrow?: string;
    nav?: ReactNode;
}

export default function OrganizerLayout({
    title,
    eyebrow,
    nav,
    children,
}: PropsWithChildren<OrganizerLayoutProps>) {
    return (
        <div className="min-h-screen bg-bg-alt">
            <header className="border-b border-line bg-bg">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                    <Link href="/dashboard">
                        <img src="/images/logo.png" alt="Itaza Invitation" className="h-9 w-auto dark:invert" />
                    </Link>
                    <nav className="flex items-center gap-6">
                        {nav}
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase hover:text-ink"
                        >
                            Se déconnecter
                        </Link>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-6 py-16">
                {eyebrow && <p className="font-label text-xs tracking-[0.28em] text-accent uppercase">{eyebrow}</p>}
                <h1 className="mt-4 font-serif text-3xl font-medium text-ink italic">{title}</h1>
                <div className="mt-8">{children}</div>
            </main>
        </div>
    );
}
