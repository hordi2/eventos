import Logo from './Logo';

const LINKS = [
    { label: 'Soutien', href: '/support' },
    { label: 'Communauté', href: '/community' },
    { label: "Retour d'information", href: '/feedback' },
    { label: 'Statut', href: '/status' },
    { label: "Conditions d'utilisation", href: '/terms' },
    { label: 'Politique de confidentialité', href: '/privacy' },
    { label: "Programme d'affiliation", href: '/affiliates' },
];

// Pas encore de comptes réseaux sociaux réels — liens désactivés (aria-disabled)
// plutôt que pointés vers un compte qui n'existe pas, en attendant que
// l'entreprise en ouvre et nous communique les URL.
const SOCIALS: { label: string; path: string }[] = [
    { label: 'Facebook', path: 'M13 22v-8h2.7l.4-3H13V9c0-.9.2-1.5 1.5-1.5H16V5c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.5v3H9.8v8H13Z' },
    {
        label: 'Twitter',
        path: 'M20 6.4c-.6.3-1.2.5-1.9.6.7-.4 1.2-1.1 1.4-1.8-.6.4-1.4.7-2.1.8a3.3 3.3 0 0 0-5.7 3c-2.7-.1-5.2-1.4-6.8-3.4a3.3 3.3 0 0 0 1 4.4c-.5 0-1-.2-1.5-.4v.1c0 1.6 1.2 3 2.7 3.3-.5.1-1 .1-1.5 0 .4 1.4 1.7 2.3 3.1 2.4A6.6 6.6 0 0 1 4 16.6a9.3 9.3 0 0 0 5 1.5c6 0 9.3-5 9.3-9.3v-.4c.6-.5 1.2-1.1 1.7-1.9Z',
    },
    {
        label: 'Instagram',
        path: 'M12 8.6a3.4 3.4 0 1 0 0 6.8 3.4 3.4 0 0 0 0-6.8ZM12 4c-2.2 0-2.5 0-3.4.1-.9 0-1.5.2-2 .4-.6.2-1 .5-1.5 1a4 4 0 0 0-1 1.5c-.2.5-.3 1.1-.4 2C3.6 9.9 3.6 10.2 3.6 12s0 2.1.1 3c0 .9.2 1.5.4 2 .2.6.5 1 1 1.5.4.4.9.7 1.5 1 .5.1 1.1.3 2 .3.9.1 1.2.1 3.4.1s2.5 0 3.4-.1c.9 0 1.5-.2 2-.4.6-.2 1-.5 1.5-1 .4-.4.7-.9 1-1.5.1-.5.3-1.1.3-2 .1-.9.1-1.2.1-3.4s0-2.5-.1-3.4c0-.9-.2-1.5-.4-2a4 4 0 0 0-1-1.5c-.4-.4-.9-.7-1.5-1-.5-.1-1.1-.3-2-.4C14.5 4 14.2 4 12 4Zm0 1.4c2.1 0 2.4 0 3.3.1.8 0 1.2.2 1.5.3.4.1.6.3.9.6.3.3.4.5.6.9.1.3.2.7.3 1.5.1.9.1 1.2.1 3.3s0 2.4-.1 3.3c0 .8-.2 1.2-.3 1.5-.1.4-.3.6-.6.9-.3.3-.5.4-.9.6-.3.1-.7.2-1.5.3-.9.1-1.2.1-3.3.1s-2.4 0-3.3-.1c-.8 0-1.2-.2-1.5-.3-.4-.1-.6-.3-.9-.6a2.6 2.6 0 0 1-.6-.9c-.1-.3-.2-.7-.3-1.5-.1-.9-.1-1.2-.1-3.3s0-2.4.1-3.3c0-.8.2-1.2.3-1.5.1-.4.3-.6.6-.9.3-.3.5-.4.9-.6.3-.1.7-.2 1.5-.3.9-.1 1.2-.1 3.3-.1ZM16.9 7.5a.8.8 0 1 0 0 1.6.8.8 0 0 0 0-1.6Z',
    },
    {
        label: 'Pinterest',
        path: 'M12 4a8 8 0 0 0-2.9 15.5c0-.6-.1-1.6 0-2.3l1.3-5.4s-.3-.7-.3-1.6c0-1.5.9-2.7 2-2.7.9 0 1.4.7 1.4 1.6 0 1-.6 2.4-1 3.7-.2 1 .5 1.9 1.6 1.9 1.9 0 3.2-2.4 3.2-5.3 0-2.2-1.5-3.8-4.1-3.8-3 0-4.9 2.2-4.9 4.7 0 .8.3 1.4.7 1.9.2.2.2.3.1.6l-.2.9c0 .3-.3.4-.5.2-1.2-.5-1.8-1.9-1.8-3.5 0-2.6 2.2-5.7 6.5-5.7 3.5 0 5.8 2.5 5.8 5.2 0 3.6-2 6.3-4.9 6.3-1 0-1.9-.5-2.2-1.1l-.6 2.4c-.2.8-.6 1.7-1 2.3.9.3 1.8.4 2.7.4A8 8 0 0 0 12 4Z',
    },
];

export default function Footer() {
    return (
        <footer className="border-t border-line bg-bg">
            <div className="mx-auto max-w-5xl px-6 py-12 text-center">
                <div className="mb-6 flex justify-center">
                    <Logo className="h-7 w-auto opacity-80" />
                </div>

                <nav className="mb-6 flex flex-wrap justify-center gap-x-6 gap-y-2">
                    {LINKS.map((link) => (
                        <a key={link.href} href={link.href} className="text-sm text-ink-soft hover:text-ink">
                            {link.label}
                        </a>
                    ))}
                </nav>

                <p className="mb-6 text-xs text-ink-soft">© {new Date().getFullYear()} Itaza Invitation</p>

                <div className="flex justify-center gap-4">
                    {SOCIALS.map((social) => (
                        <span key={social.label} aria-disabled="true" title={`${social.label} — bientôt disponible`}>
                            <svg viewBox="0 0 24 24" className="h-4 w-4 fill-ink-soft/50">
                                <path d={social.path} />
                            </svg>
                        </span>
                    ))}
                </div>
            </div>
        </footer>
    );
}
