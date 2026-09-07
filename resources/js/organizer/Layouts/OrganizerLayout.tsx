import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect, useRef, useState } from 'react';
import Footer from '../Components/Footer';
import Logo from '../Components/Logo';
import { type NavItem, type SharedProps } from '../types';

interface OrganizerLayoutProps {
    title: string;
    eyebrow?: string;
}

function isActive(href: string, currentUrl: string): boolean {
    return href === '/' ? currentUrl === '/' : currentUrl === href || currentUrl.startsWith(`${href}/`);
}

function useCloseOnClickOutside(open: boolean, close: () => void) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        function handleClickOutside(event: MouseEvent) {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                close();
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open, close]);

    return ref;
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

function GiftIcon() {
    return (
        <Link href="/affiliates" title="Programme d'affiliation" className="text-ink-soft hover:text-ink">
            <svg viewBox="0 0 24 24" className="h-5 w-5 stroke-current fill-none" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="8" width="18" height="4" />
                <path d="M5 12h14v9H5z" />
                <path d="M12 8v13" />
                <path d="M12 8c-1.5-3-6-3-6 0s4.5 0 6 0Z" />
                <path d="M12 8c1.5-3 6-3 6 0s-4.5 0-6 0Z" />
            </svg>
        </Link>
    );
}

function UserMenu({ name, canManageBilling }: { name: string; canManageBilling: boolean }) {
    const [open, setOpen] = useState(false);
    const ref = useCloseOnClickOutside(open, () => setOpen(false));

    const items = [
        { label: 'Mon compte', href: '/settings/profile' },
        { label: "Partage d'événements", href: '/partage-evenements' },
        { label: 'Obtenez du soutien', href: '/support' },
        { label: 'Forum communautaire', href: '/community' },
    ];

    return (
        <div ref={ref} className="relative flex shrink-0 items-center gap-3 sm:gap-4">
            {canManageBilling && (
                <Link
                    href="/billing"
                    className="hidden rounded-pill border border-line px-4 py-1.5 font-label text-xs tracking-[0.1em] whitespace-nowrap text-ink uppercase hover:border-ink sm:inline-block"
                >
                    Mise à niveau
                </Link>
            )}

            <GiftIcon />

            <button type="button" onClick={() => setOpen((value) => !value)} aria-expanded={open} className="flex items-center gap-2">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-bg-deep font-serif text-sm text-ink italic">
                    {name.charAt(0).toUpperCase()}
                </span>
                <span className="hidden font-label text-xs tracking-[0.1em] whitespace-nowrap text-ink-soft uppercase md:inline">{name}</span>
                <svg viewBox="0 0 10 6" className={`h-1.5 w-2.5 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} fill="none">
                    <path d="M1 1l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && (
                <div className="absolute top-full right-0 z-20 mt-3 min-w-[220px] rounded-card border border-line bg-bg py-2 shadow-lg">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={() => setOpen(false)}
                            className="block px-4 py-2.5 text-sm text-ink-soft hover:text-ink"
                        >
                            {item.label}
                        </Link>
                    ))}
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        onClick={() => setOpen(false)}
                        className="block w-full px-4 py-2.5 text-left text-sm text-ink-soft hover:text-ink"
                    >
                        Déconnexion
                    </Link>
                </div>
            )}
        </div>
    );
}

export default function OrganizerLayout({ title, eyebrow, children }: PropsWithChildren<OrganizerLayoutProps>) {
    const { nav, auth, settingsAccess } = usePage<SharedProps>().props;

    return (
        <div className="flex min-h-screen flex-col bg-bg-alt">
            <header className="border-b border-line bg-bg">
                <div className="mx-auto flex max-w-5xl items-center gap-4 px-4 py-5 sm:gap-6 sm:px-6">
                    <Link href="/dashboard" className="shrink-0">
                        <Logo />
                    </Link>
                    <nav className="flex min-w-0 flex-1 items-center gap-4 overflow-x-auto sm:gap-6">{nav && <MainNav items={nav} />}</nav>
                    {auth.user && <UserMenu name={auth.user.name} canManageBilling={settingsAccess.billing} />}
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-16">
                {eyebrow && <p className="font-label text-xs tracking-[0.28em] text-accent uppercase">{eyebrow}</p>}
                <h1 className="mt-4 font-serif text-3xl font-medium text-ink italic">{title}</h1>
                <div className="mt-8">{children}</div>
            </main>

            <Footer />
        </div>
    );
}
