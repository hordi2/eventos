import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect, useRef, useState } from 'react';
import Logo from '../Components/Logo';
import { type NavItem, type SharedProps } from '../types';

interface OrganizerLayoutProps {
    title: string;
    eyebrow?: string;
}

function isActive(href: string, currentUrl: string): boolean {
    return href === '/' ? currentUrl === '/' : currentUrl === href || currentUrl.startsWith(`${href}/`);
}

function NavGroup({ label, items, currentUrl }: { label: string; items: { label: string; href: string }[]; currentUrl: string }) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const active = items.some((item) => isActive(item.href, currentUrl));

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

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

export default function OrganizerLayout({ title, eyebrow, children }: PropsWithChildren<OrganizerLayoutProps>) {
    const { nav } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen bg-bg-alt">
            <header className="border-b border-line bg-bg">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                    <Link href="/dashboard">
                        <Logo />
                    </Link>
                    <nav className="flex items-center gap-6">
                        {nav && <MainNav items={nav} />}
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
