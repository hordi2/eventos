import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { type EventNav, type EventNavLinkKey } from '../types';
import EventIcon, { type EventIconName } from './EventIcon';
import Logo from './Logo';

interface SidebarItem {
    label: string;
    icon?: EventIconName;
    linkKey?: EventNavLinkKey;
    href?: string | null;
    external?: boolean;
    soon?: boolean;
    children?: SidebarItem[];
}

interface SidebarSection {
    title?: string;
    items: SidebarItem[];
}

interface EventSidebarProps {
    nav: EventNav;
    open: boolean;
    onClose: () => void;
}

function buildSections(nav: EventNav): SidebarSection[] {
    return [
        {
            title: 'Tableau de bord',
            items: [
                { label: 'Liste de contrôle', icon: 'checklist', linkKey: 'checklist' },
                {
                    label: 'Réponses des invités',
                    icon: 'chart',
                    children: [
                        { label: 'Toutes les réponses', linkKey: 'responses' },
                        { label: 'Événements secondaires', linkKey: 'subEvents' },
                        { label: 'Questions personnalisées', linkKey: 'form' },
                        { label: 'Fichiers reçus', linkKey: 'files' },
                        { label: 'Préférences alimentaires', soon: true },
                        { label: 'Dons et cadeaux', soon: true },
                    ],
                },
            ],
        },
        {
            title: 'Personnaliser',
            items: [
                { label: "Site web de l'événement", icon: 'website', linkKey: 'website' },
                { label: "Formulaire d'inscription", icon: 'form', linkKey: 'form' },
                { label: "Paramètres de l'événement", icon: 'settings', linkKey: 'settings' },
                {
                    label: 'Liste des invités',
                    icon: 'guests',
                    children: [
                        { label: 'Invités', linkKey: 'guests' },
                        { label: 'Importation', linkKey: 'import' },
                        { label: 'Décors', soon: true },
                    ],
                },
            ],
        },
        {
            title: 'Lancement',
            items: [
                { label: 'Aperçu', icon: 'eye', href: nav.publicUrl ?? nav.previewUrl, external: true },
                {
                    label: 'Publier',
                    icon: 'rocket',
                    href: nav.canChangeStatus && nav.links.checklist ? `${nav.links.checklist}?onglet=lancement` : null,
                },
                { label: 'Partager et inviter', icon: 'megaphone', linkKey: 'communications' },
            ],
        },
        {
            title: 'Organiser',
            items: [
                { label: 'Communications par e-mail', icon: 'send', linkKey: 'communications' },
                { label: 'Inviter un collaborateur', icon: 'team', linkKey: 'collaborators' },
                { label: 'Plan de table', icon: 'seat', linkKey: 'seating' },
                { label: 'Check-in', icon: 'scan', linkKey: 'checkIn' },
                { label: 'Badges', icon: 'badge', linkKey: 'badges' },
                { label: 'Billetterie', icon: 'ticket', linkKey: 'tickets' },
                { label: 'Exports', icon: 'download', linkKey: 'exports' },
            ],
        },
        {
            items: [{ label: 'Parrainage', icon: 'gift', linkKey: 'referral' }],
        },
    ];
}

function pathOf(href: string): string {
    return new URL(href, 'http://itaza.local').pathname;
}

function SoonBadge() {
    return (
        <span className="rounded-pill border border-bg/25 px-2 py-0.5 text-[10px] tracking-[0.08em] text-bg/60 uppercase">Bientôt</span>
    );
}

/**
 * Menu latéral commun à toutes les pages d'un événement. Les rubriques
 * « Bientôt » annoncent ce qu'Itaza ne propose pas encore, sans lien vers
 * une page vide ; un lien que l'utilisateur ne peut pas ouvrir n'apparaît pas.
 */
export default function EventSidebar({ nav, open, onClose }: EventSidebarProps) {
    const { url } = usePage();
    const currentPath = url.split('?')[0];
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const hrefOf = (item: SidebarItem): string | null => {
        if (item.href !== undefined) {
            return item.href;
        }

        return item.linkKey ? nav.links[item.linkKey] : null;
    };

    const isCurrent = (item: SidebarItem): boolean => {
        const href = hrefOf(item);

        return href !== null && !item.external && !href.includes('?') && pathOf(href) === currentPath;
    };

    const visibleChildren = (item: SidebarItem): SidebarItem[] =>
        (item.children ?? []).filter((child) => child.soon || hrefOf(child) !== null);

    const sections = buildSections(nav)
        .map((section) => ({
            ...section,
            items: section.items.filter((item) =>
                item.children ? visibleChildren(item).some((child) => !child.soon) : item.soon || hrefOf(item) !== null,
            ),
        }))
        .filter((section) => section.items.length > 0);

    const itemClass = (active: boolean) =>
        `flex w-full items-center gap-3 rounded-card px-3 py-2.5 text-left text-sm transition-colors ${
            active ? 'bg-bg/15 text-bg' : 'text-bg/75 hover:bg-bg/10 hover:text-bg'
        }`;

    function renderLeaf(item: SidebarItem, nested: boolean) {
        const href = hrefOf(item);
        const padding = nested ? 'pl-11' : '';

        if (item.soon || href === null) {
            return (
                <span aria-disabled="true" className={`flex items-center gap-3 rounded-card px-3 py-2 text-sm text-bg/45 ${padding}`}>
                    {item.icon && <EventIcon name={item.icon} />}
                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                    <SoonBadge />
                </span>
            );
        }

        if (item.external) {
            return (
                <a href={href} target="_blank" rel="noreferrer" className={`${itemClass(false)} ${padding}`}>
                    {item.icon && <EventIcon name={item.icon} />}
                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                    <EventIcon name="external" className="h-4 w-4 text-bg/50" />
                </a>
            );
        }

        return (
            <Link href={href} onClick={onClose} aria-current={isCurrent(item) ? 'page' : undefined} className={`${itemClass(isCurrent(item))} ${padding}`}>
                {item.icon && <EventIcon name={item.icon} />}
                <span className="min-w-0 flex-1 truncate">{item.label}</span>
            </Link>
        );
    }

    return (
        <>
            {open && <button type="button" aria-label="Fermer le menu" onClick={onClose} className="fixed inset-0 z-40 bg-ink/40 lg:hidden" />}

            <aside
                className={`${open ? 'fixed inset-y-0 left-0 z-50 flex' : 'hidden'} w-72 max-w-[85vw] shrink-0 flex-col bg-ink text-bg lg:sticky lg:top-0 lg:flex lg:h-screen`}
            >
                <div className="flex items-center justify-between gap-3 px-5 pt-6 pb-4">
                    <Link href="/dashboard" className="rounded-card bg-bg px-3 py-2">
                        <Logo className="h-7 w-auto" />
                    </Link>
                    <button type="button" onClick={onClose} aria-label="Fermer le menu" className="text-bg/70 hover:text-bg lg:hidden">
                        <EventIcon name="close" />
                    </button>
                </div>

                <nav aria-label="Menu de l'événement" className="flex-1 overflow-y-auto px-3 pb-6">
                    {sections.map((section, index) => (
                        <div key={section.title ?? `section-${index}`} className={`py-4 ${index > 0 ? 'border-t border-bg/10' : ''}`}>
                            {section.title && (
                                <p className="px-3 pb-2 font-label text-[11px] tracking-[0.18em] text-bg/55 uppercase">{section.title}</p>
                            )}
                            <ul className="space-y-0.5">
                                {section.items.map((item) => {
                                    if (!item.children) {
                                        return <li key={item.label}>{renderLeaf(item, false)}</li>;
                                    }

                                    const children = visibleChildren(item);
                                    const hasCurrentChild = children.some(isCurrent);
                                    const isOpen = expanded[item.label] ?? hasCurrentChild;

                                    return (
                                        <li key={item.label}>
                                            <button
                                                type="button"
                                                onClick={() => setExpanded((state) => ({ ...state, [item.label]: !isOpen }))}
                                                aria-expanded={isOpen}
                                                className={itemClass(hasCurrentChild && !isOpen)}
                                            >
                                                {item.icon && <EventIcon name={item.icon} />}
                                                <span className="min-w-0 flex-1 truncate">{item.label}</span>
                                                <EventIcon name="chevronDown" className={`h-4 w-4 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                                            </button>
                                            {isOpen && (
                                                <ul className="mt-0.5 space-y-0.5">
                                                    {children.map((child) => (
                                                        <li key={child.label}>{renderLeaf(child, true)}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </nav>

                <div className="border-t border-bg/10 p-4">
                    <span aria-disabled="true" className="flex items-center gap-3 rounded-card border border-bg/15 px-4 py-3 text-sm text-bg/50">
                        <EventIcon name="switch" />
                        <span className="min-w-0 flex-1 truncate">Changer d'expérience</span>
                        <SoonBadge />
                    </span>
                </div>
            </aside>
        </>
    );
}
