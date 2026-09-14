import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useState } from 'react';
import Footer from '../Components/Footer';
import Logo from '../Components/Logo';
import UserMenu from '../Components/UserMenu';
import useCloseOnClickOutside from '../hooks/useCloseOnClickOutside';
import { type NavItem, type SharedProps } from '../types';

interface OrganizerLayoutProps {
    title: string;
    eyebrow?: string;
    subtitle?: string;
    closeHref?: string;
}

function isActive(href: string, currentUrl: string): boolean {
    return href === '/' ? currentUrl === '/' : currentUrl === href || currentUrl.startsWith(`${href}/`);
}

function NavGroup({ label, items, currentUrl }: { label: string; items: { label: string; href: string }[]; currentUrl: string }) {
    const [open, setOpen] = useState(false);
    const ref = useCloseOnClickOutside(open, () => setOpen(false));
    const active = items.some((item) => isActive(item.href, currentUrl));

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                className={`flex items-center gap-1.5 font-label text-xs tracking-[0.14em] uppercase ${
                    active ? 'text-ink' : 'text-ink-soft hover:text-ink'
                }`}
            >
                {label}
                <svg viewBox="0 0 10 6" className={`h-1.5 w-2.5 transition-transform ${open ? 'rotate-180' : ''}`} fill="none">
                    <path d="M1 1l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && (
                <div className="absolute top-full left-0 z-20 mt-3 min-w-[220px] rounded-card border border-line bg-bg py-2 shadow-lg">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={() => setOpen(false)}
                            className={`block px-4 py-2.5 text-sm ${
                                isActive(item.href, currentUrl) ? 'text-ink' : 'text-ink-soft hover:text-ink'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}

function MainNav({ items }: { items: NavItem[] }) {
    const { url } = usePage();

    return (
        <>
            {items.map((item) =>
                'href' in item ? (
                    <Link
                        key={item.label}
                        href={item.href}
                        className={`font-label text-xs tracking-[0.14em] uppercase ${
                            isActive(item.href, url) ? 'text-ink' : 'text-ink-soft hover:text-ink'
                        }`}
                    >
                        {item.label}
                    </Link>
                ) : (
                    <NavGroup key={item.label} label={item.label} items={item.items} currentUrl={url} />
                ),
            )}
        </>
    );
}

export default function OrganizerLayout({ title, eyebrow, subtitle, closeHref, children }: PropsWithChildren<OrganizerLayoutProps>) {
    const { nav, auth, settingsAccess, organizations } = usePage<SharedProps>().props;

    return (
        <div className="flex min-h-screen flex-col bg-bg-alt">
            <header className="border-b border-line bg-bg">
                <div className="mx-auto flex max-w-5xl items-center gap-4 px-4 py-5 sm:gap-6 sm:px-6">
                    <Link href="/dashboard" className="shrink-0">
                        <Logo />
                    </Link>
                    <nav className="flex min-w-0 flex-1 items-center gap-4 overflow-x-auto sm:gap-6">{nav && <MainNav items={nav} />}</nav>
                    {auth.user && (
                        <UserMenu
                            name={auth.user.name}
                            canManageBilling={settingsAccess.billing}
                            canShareEvents={settingsAccess.eventSharing}
                            organizations={organizations}
                        />
                    )}
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-16">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        {eyebrow && <p className="font-label text-xs tracking-[0.28em] text-accent uppercase">{eyebrow}</p>}
                        <h1 className="mt-4 font-serif text-3xl font-medium text-ink italic">{title}</h1>
                        {subtitle && <p className="mt-2 text-ink-soft">{subtitle}</p>}
                    </div>
                    {closeHref && (
                        <Link href={closeHref} aria-label="Fermer" className="mt-4 shrink-0 text-ink-soft hover:text-ink">
                            <svg viewBox="0 0 24 24" className="h-6 w-6 stroke-current" fill="none" strokeWidth="1.6" strokeLinecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </Link>
                    )}
                </div>
                <div className="mt-8">{children}</div>
            </main>

            <Footer />
        </div>
    );
}
